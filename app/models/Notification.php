<?php

/**
 * Notification Model
 * Handles all notification-related database operations
 */
class Notification
{
    /**
     * Create a generic notification
     */
    public static function create(array $data): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, source_user_id, video_id, comment_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([
            $data['user_id'],
            $data['type'],
            $data['source_user_id'] ?? null,
            $data['video_id'] ?? null,
            $data['comment_id'] ?? null,
        ]);
    }

    /**
     * Create friend request notifications (sender + receiver)
     */
    public static function createFriendRequest(
        int $senderId,
        int $receiverId,
        int $requestId
    ): void {
        $db = Database::getInstance();

        // Receiver notification (accept / reject)
        $stmt = $db->prepare(
            'INSERT INTO notifications
                (user_id, type, source_user_id, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$receiverId, 'friend_request', $senderId]);

        // Sender notification (request sent)
        $stmt->execute([$senderId, 'friend_request_sent', $receiverId]);
    }

    /**
     * Update notifications after friend request action
     */
    public static function updateFriendRequestNotifications(
        int $senderId,
        int $receiverId,
        string $status
    ): void {
        $db = Database::getInstance();

        if ($status === 'accepted') {
            // Receiver notification
            self::create([
                'user_id'        => $receiverId,
                'type'           => 'friend_request_accepted',
                'source_user_id' => $senderId,
            ]);

            // Sender notification
            self::create([
                'user_id'        => $senderId,
                'type'           => 'friend_request_accepted',
                'source_user_id' => $receiverId,
            ]);
        }

        if ($status === 'rejected') {
            // Sender only
            self::create([
                'user_id'        => $senderId,
                'type'           => 'friend_request_rejected',
                'source_user_id' => $receiverId,
            ]);
        }

        // Mark original notifications as read
        $stmt = $db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = ?
               AND source_user_id = ?
               AND type IN ("friend_request","friend_request_sent")
               AND is_read = 0'
        );

        $stmt->execute([$receiverId, $senderId]);
        $stmt->execute([$senderId, $receiverId]);
    }

    /**
     * Get unread notifications
     */
    public static function unread(int $userId): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT *
             FROM notifications
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * Mark all notifications as read
     */
    public static function markAllRead(int $userId): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
    }
}
