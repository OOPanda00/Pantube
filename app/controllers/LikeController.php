<?php

class LikeController extends Controller
{
    public function toggle(): void
    {
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            header('Content-Type: application/json');
        }

        if (!Auth::check()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $videoId = (int) ($_POST['video_id'] ?? 0);
        $type    = $_POST['type'] ?? '';

        if ($videoId <= 0 || !in_array($type, ['like', 'dislike'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT user_id FROM videos WHERE id = ?');
            $stmt->execute([$videoId]);
            $video = $stmt->fetch();

            if (!$video) {
                echo json_encode(['success' => false, 'error' => 'Video not found']);
                exit;
            }

            $currentReaction = Like::userReaction(Auth::user()['id'], $videoId);
            Like::toggle(Auth::user()['id'], $videoId, $type);

            $likes    = Like::count($videoId, 'like');
            $dislikes = Like::count($videoId, 'dislike');
            $newReaction = Like::userReaction(Auth::user()['id'], $videoId);

            if (
                $newReaction &&
                $currentReaction !== $newReaction &&
                $video['user_id'] != Auth::user()['id'] &&
                $newReaction === 'like'
            ) {
                Notification::create([
                    'user_id'        => $video['user_id'],
                    'type'           => 'like',
                    'source_user_id' => Auth::user()['id'],
                    'video_id'       => $videoId,
                    'comment_id'     => null,
                ]);
            }

            echo json_encode([
                'success'      => true,
                'likes'        => $likes,
                'dislikes'     => $dislikes,
                'userReaction' => $newReaction,
            ]);
            exit;
        } catch (Exception $e) {
            error_log('Like toggle error: ' . $e->getMessage());

            echo json_encode([
                'success' => false,
                'error'   => 'An error occurred: ' . $e->getMessage(),
            ]);
            exit;
        }
    }
}
