<?php
/**
 * Video Model
 * Handles videos table operations
 */

class Video
{

public static function suggested(int $excludeId, int $limit = 8): array
{
    $db = Database::getInstance();

    $stmt = $db->prepare("
        SELECT v.*, u.username
        FROM videos v
        JOIN users u ON u.id = v.user_id
        WHERE v.id != ?
        ORDER BY RAND()
        LIMIT ?
    ");

    $stmt->execute([$excludeId, $limit]);
    return $stmt->fetchAll();
}

    /**
     * Create new video record
     *
     * @param array $data
     * @return bool
     */
    public static function create(array $data): bool
    {
        $db = Database::getInstance();

	$stmt = $db->prepare(
	    'INSERT INTO videos (user_id, title, description, filename, thumbnail)
     	VALUES (?, ?, ?, ?, ?)'
	);

	return $stmt->execute([
    	$data['user_id'],
    	$data['title'],
    	$data['description'],
    	$data['filename'],
    	$data['thumbnail']
	]);
    }

    /**
     * Get latest videos
     *
     * @param int $limit
     * @return array
     */
    public static function latest(int $limit = 20): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT v.*, u.username
             FROM videos v
             JOIN users u ON u.id = v.user_id
             ORDER BY v.created_at DESC
             LIMIT ?'
        );

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find video by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT v.*, u.username
             FROM videos v
             JOIN users u ON u.id = v.user_id
             WHERE v.id = ?
             LIMIT 1'
        );

        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Increment view counter
     *
     * @param int $id
     */
    public static function incrementViews(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE videos SET views = views + 1 WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
    /**
     * Delete video by ID
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        $db = Database::getInstance();

        try {
            // Get filename first
            $stmt = $db->prepare('SELECT filename FROM videos WHERE id = ?');
            $stmt->execute([$id]);
            $filename = $stmt->fetchColumn();

            if (!$filename) {
                error_log('[VIDEO::DELETE] No filename found for video ID: ' . $id);
                return false;
            }

            // Delete DB record (will cascade likes & comments)
            $stmt = $db->prepare('DELETE FROM videos WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            if (!$result) {
                error_log('[VIDEO::DELETE] Failed to delete video record from DB: ' . $id);
                return false;
            }

            // Check if any rows were actually deleted
            if ($stmt->rowCount() === 0) {
                error_log('[VIDEO::DELETE] No rows deleted - video ID not found: ' . $id);
                return false;
            }

            // Delete file from disk
            $filePath = App::$config['paths']['uploads'] . '/videos/' . $filename;
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    error_log('[VIDEO::DELETE] Failed to delete file: ' . $filePath);
                }
            }

            error_log('[VIDEO::DELETE] Successfully deleted video ID: ' . $id . ', file: ' . $filename);
            return true;
        } catch (Exception $e) {
            error_log('[VIDEO::DELETE EXCEPTION] ' . $e->getMessage());
            return false;
        }
    }

}
