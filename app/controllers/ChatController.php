<?php

/**
 * Chat Controller
 */
class ChatController extends Controller
{
    /**
     * Show messages page
     */
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        $db     = Database::getInstance();
        $userId = Auth::user()['id'];

        // Get all conversations for current user
        $stmt = $db->prepare(
            'SELECT DISTINCT 
                u.id,
                u.username,
                u.avatar,

                (
                    SELECT message 
                    FROM chat_messages 
                    WHERE 
                        (sender_id = ? AND receiver_id = u.id)
                        OR
                        (sender_id = u.id AND receiver_id = ?)
                    ORDER BY created_at DESC
                    LIMIT 1
                ) AS last_message,

                (
                    SELECT created_at 
                    FROM chat_messages 
                    WHERE 
                        (sender_id = ? AND receiver_id = u.id)
                        OR
                        (sender_id = u.id AND receiver_id = ?)
                    ORDER BY created_at DESC
                    LIMIT 1
                ) AS last_message_time,

                (
                    SELECT COUNT(*) 
                    FROM chat_messages 
                    WHERE 
                        sender_id = u.id 
                        AND receiver_id = ? 
                        AND is_read = 0
                ) AS unread_count

            FROM users u
            WHERE u.id IN (
                SELECT DISTINCT sender_id FROM chat_messages WHERE receiver_id = ?
                UNION
                SELECT DISTINCT receiver_id FROM chat_messages WHERE sender_id = ?
            )
            ORDER BY last_message_time DESC'
        );

        $stmt->execute([
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId,
            $userId
        ]);

        $conversations = $stmt->fetchAll();

        $this->view('messages', [
            'conversations' => $conversations,
            'csrf'          => CSRF::generate(),
        ]);
    }

    /**
     * Send message
     */
    public function send(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit('Unauthorized');
        }

        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            exit('Invalid CSRF token');
        }

        $receiverId = (int) ($_POST['receiver_id'] ?? 0);
        $message    = trim($_POST['message'] ?? '');

        if ($receiverId <= 0 || $message === '') {
            exit('Invalid input');
        }

        Chat::send(
            Auth::user()['id'],
            $receiverId,
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );

        echo 'OK';
    }

    /**
     * Fetch messages
     */
    public function fetch(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit('Unauthorized');
        }

        $withUserId = (int) ($_GET['user_id'] ?? 0);

        if ($withUserId <= 0) {
            exit('Invalid user');
        }

        $messages = Chat::fetch(
            Auth::user()['id'],
            $withUserId
        );

        Chat::markRead(Auth::user()['id'], $withUserId);

        header('Content-Type: application/json');
        echo json_encode($messages);
    }
}
