<?php

class Chat
{
    public static function send(int $senderId, int $receiverId, string $message): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO chat_messages (sender_id, receiver_id, message)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$senderId, $receiverId, $message]);

        Notification::create([
            'user_id' => $receiverId,
            'type' => 'message',
            'source_user_id' => $senderId
        ]);
    }

    public static function fetch(int $userId, int $withUserId, int $limit = 50): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT *
             FROM chat_messages
             WHERE
                (sender_id = ? AND receiver_id = ?)
                OR
                (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at ASC
             LIMIT ?'
        );

        $stmt->execute([$userId, $withUserId, $withUserId, $userId, $limit]);
        return $stmt->fetchAll();
    }

    public static function markRead(int $userId, int $fromUserId): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE chat_messages
             SET is_read = 1
             WHERE receiver_id = ? AND sender_id = ?'
        );
        $stmt->execute([$userId, $fromUserId]);
    }
}
