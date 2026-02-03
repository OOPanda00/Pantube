<?php

class Comment
{
    public static function create(array $data): bool
    {
        $db = Database::getInstance();

        // Use current timestamp for created_at
        $stmt = $db->prepare(
            'INSERT INTO comments (user_id, video_id, parent_id, content, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );

        return $stmt->execute([
            $data['user_id'],
            $data['video_id'],
            $data['parent_id'],
            $data['content'],
        ]);
    }

    public static function treeForVideo(int $videoId): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT c.*, u.username, u.avatar
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.video_id = ?
             ORDER BY c.created_at ASC'
        );

        $stmt->execute([$videoId]);
        $comments = $stmt->fetchAll();

        return self::buildTree($comments);
    }

    private static function buildTree(array $comments, int $parentId = null): array
    {
        $branch = [];

        foreach ($comments as $comment) {
            if ((int) $comment['parent_id'] === (int) $parentId) {
                $children = self::buildTree($comments, (int) $comment['id']);

                if ($children) {
                    $comment['replies'] = $children;
                }

                $branch[] = $comment;
            }
        }

        return $branch;
    }
}
