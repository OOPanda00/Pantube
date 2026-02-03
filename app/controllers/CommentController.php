<?php

class CommentController extends Controller
{
    public function store(): void
    {
        // التحقق من طلب AJAX مرة واحدة
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );

        if ($isAjax) {
            header('Content-Type: application/json');
        }

        // التحقق من المصادقة
        if (!Auth::check()) {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                exit;
            }

            http_response_code(403);
            exit('Unauthorized');
        }

        // التحقق من CSRF
        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }

            http_response_code(403);
            exit('Invalid CSRF token');
        }

        // التحقق من المدخلات
        $videoId  = (int) ($_POST['video_id'] ?? 0);
        $parentId = $_POST['parent_id'] ?? null;
        $content  = trim($_POST['content'] ?? '');

        if ($videoId <= 0 || $content === '') {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid input'
                ]);
                exit;
            }

            exit('Invalid input');
        }

        try {
            $encodedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $db = Database::getInstance();

            $stmt = $db->prepare(
                'INSERT INTO comments (user_id, video_id, parent_id, content, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );

            $stmt->execute([
                Auth::user()['id'],
                $videoId,
                $parentId ? (int) $parentId : null,
                $encodedContent,
            ]);

            $commentId = $db->lastInsertId();

            $stmt = $db->prepare('SELECT user_id, title FROM videos WHERE id = ?');
            $stmt->execute([$videoId]);
            $video = $stmt->fetch();

            if ($video && $video['user_id'] != Auth::user()['id']) {
                Notification::create([
                    'user_id'        => $video['user_id'],
                    'type'           => $parentId ? 'reply' : 'comment',
                    'source_user_id' => Auth::user()['id'],
                    'video_id'       => $videoId,
                    'comment_id'     => $commentId,
                ]);
            }

            if ($parentId) {
                $stmt = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
                $stmt->execute([$parentId]);
                $parentComment = $stmt->fetch();

                if (
                    $parentComment &&
                    $parentComment['user_id'] != Auth::user()['id'] &&
                    (!$video || $video['user_id'] != $parentComment['user_id'])
                ) {
                    Notification::create([
                        'user_id'        => $parentComment['user_id'],
                        'type'           => 'reply',
                        'source_user_id' => Auth::user()['id'],
                        'video_id'       => $videoId,
                        'comment_id'     => $commentId,
                    ]);
                }
            }

            CSRF::destroy();

            if ($isAjax) {
                echo json_encode([
                    'success'  => true,
                    'message'  => 'Comment added successfully',
                    'video_id' => $videoId,
                    'redirect' => '/pantube/public/watch/' . $videoId,
                ]);
                exit;
            }

            $this->redirect('watch/' . $videoId);
        } catch (Exception $e) {
            error_log('Comment creation error: ' . $e->getMessage());

            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save comment. Please try again.',
                ]);
                exit;
            }

            exit('Failed to save comment. Please try again.');
        }
    }

    public function delete(): void
    {
        // التحقق من طلب AJAX مرة واحدة
        $isAjax = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );

        if ($isAjax) {
            header('Content-Type: application/json');
        }

        if (!Auth::check()) {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                exit;
            }

            http_response_code(403);
            exit('Unauthorized');
        }

        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ]);
                exit;
            }

            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $commentId = (int) ($_POST['comment_id'] ?? 0);

        if ($commentId <= 0) {
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid comment'
                ]);
                exit;
            }

            exit('Invalid comment');
        }

        try {
            $db = Database::getInstance();

            $stmt = $db->prepare(
                'SELECT c.*, v.user_id AS video_owner_id
                 FROM comments c
                 JOIN videos v ON v.id = c.video_id
                 WHERE c.id = ?'
            );

            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();

            if (!$comment) {
                throw new Exception('Comment not found');
            }

            $user = Auth::user();

            $canDelete =
                $user['id'] == $comment['user_id'] ||
                $user['id'] == $comment['video_owner_id'] ||
                Auth::isAdmin() ||
                Auth::isOwner();

            if (!$canDelete) {
                throw new Exception('Permission denied');
            }

            $stmt = $db->prepare('DELETE FROM comments WHERE id = ?');
            $stmt->execute([$commentId]);

            CSRF::destroy();

            if ($isAjax) {
                echo json_encode([
                    'success'  => true,
                    'message'  => 'Comment deleted successfully',
                    'video_id' => $comment['video_id'],
                ]);
                exit;
            }

            $this->redirect('watch/' . $comment['video_id']);
        } catch (Exception $e) {
            error_log('Comment deletion error: ' . $e->getMessage());

            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete comment: ' . $e->getMessage(),
                ]);
                exit;
            }

            exit('Failed to delete comment: ' . $e->getMessage());
        }
    }
}
