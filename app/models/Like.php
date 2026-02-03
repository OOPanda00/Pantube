<?php

class Like
{
    public static function toggle(int $userId, int $videoId, string $type): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT * FROM likes WHERE user_id = ? AND video_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $videoId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['type'] === $type) {
                // Remove same reaction
                $db->prepare(
                    'DELETE FROM likes WHERE id = ?'
                )->execute([$existing['id']]);
            } else {
                // Switch reaction
                $db->prepare(
                    'UPDATE likes SET type = ? WHERE id = ?'
                )->execute([$type, $existing['id']]);
            }
        } else {
            $db->prepare(
                'INSERT INTO likes (user_id, video_id, type)
                 VALUES (?, ?, ?)'
            )->execute([$userId, $videoId, $type]);
        }
    }

    public static function count(int $videoId, string $type): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM likes WHERE video_id = ? AND type = ?'
        );
        $stmt->execute([$videoId, $type]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get current user's reaction for a given video.
     * Returns 'like', 'dislike' or null
     *
     * @param int $userId
     * @param int $videoId
     * @return string|null
     */
    public static function userReaction(int $userId, int $videoId): ?string
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT type FROM likes WHERE user_id = ? AND video_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $videoId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['type'])) {
            return $row['type'];
        }
        return null;
    }
}
