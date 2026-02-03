<?php

/**
 * User Model
 * Handles users, relationships, friends, blocks
 */
class User
{
    /* =========================
       BASIC USER OPERATIONS
    ========================= */

    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password, role, avatar)
             VALUES (:username, :email, :password, :role, :avatar)'
        );

        return $stmt->execute([
            ':username' => $data['username'],
            ':email'    => $data['email'],
            ':password' => $data['password'],
            ':role'     => $data['role'] ?? 'user',
            ':avatar'   => $data['avatar'] ?? 'default.png',
        ]);
    }

    public static function updateAvatar(int $id, string $avatar): bool
    {
        $db = Database::getInstance();
        return $db->prepare(
            'UPDATE users SET avatar = ? WHERE id = ?'
        )->execute([$avatar, $id]);
    }

    public static function updateBio(int $id, string $bio): bool
    {
        $db = Database::getInstance();
        return $db->prepare(
            'UPDATE users SET bio = ? WHERE id = ?'
        )->execute([$bio, $id]);
    }

    public static function updateUsername(int $id, string $username): bool
    {
        $db = Database::getInstance();
        return $db->prepare(
            'UPDATE users SET username = ? WHERE id = ?'
        )->execute([$username, $id]);
    }

    /* =========================
       TABLE SAFETY
    ========================= */

    public static function ensureTablesExist(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $db = Database::getInstance();

        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS followers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    follower_id INT NOT NULL,
                    following_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_follow (follower_id, following_id)
                ) ENGINE=InnoDB
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS friend_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    status ENUM('pending','accepted','rejected','removed') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_request (sender_id, receiver_id)
                ) ENGINE=InnoDB
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS friends (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    friend_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_friend (user_id, friend_id)
                ) ENGINE=InnoDB
            ");

            $db->exec("
                CREATE TABLE IF NOT EXISTS blocks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    blocker_id INT NOT NULL,
                    blocked_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_block (blocker_id, blocked_id)
                ) ENGINE=InnoDB
            ");

            $done = true;
        } catch (Throwable $e) {
            error_log('ensureTablesExist failed: ' . $e->getMessage());
        }
    }

    /* =========================
       FOLLOW SYSTEM
    ========================= */

    public static function follow(int $followerId, int $followingId): bool
    {
        if ($followerId === $followingId) {
            return false;
        }

        self::ensureTablesExist();

        if (self::isBlocked($followingId, $followerId)) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?, ?)'
        );

        return $stmt->execute([$followerId, $followingId]);
    }

    public static function unfollow(int $followerId, int $followingId): bool
    {
        self::ensureTablesExist();

        $db = Database::getInstance();
        return $db->prepare(
            'DELETE FROM followers WHERE follower_id = ? AND following_id = ?'
        )->execute([$followerId, $followingId]);
    }

    public static function isFollowing(int $followerId, int $followingId): bool
    {
        self::ensureTablesExist();

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ? LIMIT 1'
        );
        $stmt->execute([$followerId, $followingId]);

        return (bool)$stmt->fetch();
    }

    /* =========================
       FRIEND SYSTEM
    ========================= */

    public static function sendFriendRequest(int $senderId, int $receiverId): bool
    {
        if ($senderId === $receiverId) {
            return false;
        }

        self::ensureTablesExist();

        if (self::isBlocked($receiverId, $senderId)) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)'
        );

        if ($stmt->execute([$senderId, $receiverId])) {
            Notification::createFriendRequest($senderId, $receiverId, (int)$db->lastInsertId());
            return true;
        }

        return false;
    }

    public static function acceptFriendRequest(int $requestId, int $receiverId): bool
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT sender_id FROM friend_requests
             WHERE id = ? AND receiver_id = ? AND status = "pending"'
        );
        $stmt->execute([$requestId, $receiverId]);
        $request = $stmt->fetch();

        if (!$request) {
            return false;
        }

        $senderId = (int)$request['sender_id'];

        $db->prepare(
            'UPDATE friend_requests SET status = "accepted" WHERE id = ?'
        )->execute([$requestId]);

        $db->prepare(
            'INSERT IGNORE INTO friends (user_id, friend_id) VALUES (?, ?), (?, ?)'
        )->execute([$senderId, $receiverId, $receiverId, $senderId]);

        Notification::updateFriendRequestNotifications($senderId, $receiverId, 'accepted');
        return true;
    }

    public static function rejectFriendRequest(int $requestId, int $receiverId): bool
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT sender_id FROM friend_requests
             WHERE id = ? AND receiver_id = ? AND status = "pending"'
        );
        $stmt->execute([$requestId, $receiverId]);
        $request = $stmt->fetch();

        if (!$request) {
            return false;
        }

        $db->prepare(
            'UPDATE friend_requests SET status = "rejected" WHERE id = ?'
        )->execute([$requestId]);

        Notification::updateFriendRequestNotifications(
            (int)$request['sender_id'],
            $receiverId,
            'rejected'
        );

        return true;
    }

    public static function removeFriend(int $userId, int $friendId): bool
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $db->prepare(
            'DELETE FROM friends WHERE
             (user_id = ? AND friend_id = ?)
             OR (user_id = ? AND friend_id = ?)'
        )->execute([$userId, $friendId, $friendId, $userId]);

        return true;
    }

    public static function getFriendshipStatus(int $userId, int $otherId): ?string
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT status FROM friend_requests
             WHERE ((sender_id = ? AND receiver_id = ?)
             OR (sender_id = ? AND receiver_id = ?))
             AND status IN ("pending","accepted")
             LIMIT 1'
        );
        $stmt->execute([$userId, $otherId, $otherId, $userId]);

        $row = $stmt->fetch();
        return $row['status'] ?? null;
    }

    /** Get friend_requests.id for pending request from sender to receiver */
    public static function getPendingFriendRequestId(int $senderId, int $receiverId): int
    {
        self::ensureTablesExist();
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = "pending" LIMIT 1'
        );
        $stmt->execute([$senderId, $receiverId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    /* =========================
       BLOCK SYSTEM
    ========================= */

    public static function block(int $blockerId, int $blockedId): bool
    {
        if ($blockerId === $blockedId) {
            return false;
        }

        self::ensureTablesExist();

        self::unfollow($blockerId, $blockedId);
        self::unfollow($blockedId, $blockerId);
        self::removeFriend($blockerId, $blockedId);

        $db = Database::getInstance();
        return $db->prepare(
            'INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)'
        )->execute([$blockerId, $blockedId]);
    }

    public static function unblock(int $blockerId, int $blockedId): bool
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        return $db->prepare(
            'DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?'
        )->execute([$blockerId, $blockedId]);
    }

    public static function isBlocked(int $blockerId, int $blockedId): bool
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1'
        );
        $stmt->execute([$blockerId, $blockedId]);

        return (bool)$stmt->fetch();
    }

    /* =========================
       STATS
    ========================= */

    public static function getStats(int $userId): array
    {
        self::ensureTablesExist();
        $db = Database::getInstance();

        return [
            'followers' => (int)$db->query(
                "SELECT COUNT(*) FROM followers WHERE following_id = $userId"
            )->fetchColumn(),

            'following' => (int)$db->query(
                "SELECT COUNT(*) FROM followers WHERE follower_id = $userId"
            )->fetchColumn(),

            'friends' => (int)$db->query(
                "SELECT COUNT(*) FROM friends WHERE user_id = $userId"
            )->fetchColumn(),

            'videos' => (int)$db->query(
                "SELECT COUNT(*) FROM videos WHERE user_id = $userId"
            )->fetchColumn(),
        ];
    }
}
