<?php

class MessagesController extends Controller
{
    /* =========================
       قائمة المحادثات
    ========================= */
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        $userId = Auth::user()['id'];
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT 
                u.id as other_user_id,
                u.username,
                u.avatar,
                MAX(m.created_at) AS last_message_time,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(m.message ORDER BY m.created_at DESC),
                    ',', 1
                ) AS last_message,
                SUM(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 ELSE 0 END) AS unread_count
            FROM users u
            JOIN chat_messages m
              ON (
                   (m.sender_id = u.id AND m.receiver_id = ?)
                OR (m.receiver_id = u.id AND m.sender_id = ?)
              )
            WHERE u.id != ?
            GROUP BY u.id
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);

        $conversations = $stmt->fetchAll();

        $this->view('messages', [
            'conversations' => $conversations,
            'csrf' => CSRF::generate()
        ]);
    }

    /* =========================
       جلب محادثة (AJAX)
    ========================= */
    public function fetch(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit;
        }

        $withUserId = (int)($_GET['user_id'] ?? 0);
        $currentUserId = Auth::user()['id'];

        if ($withUserId <= 0) {
            http_response_code(400);
            exit;
        }

        if (
            User::isBlocked($withUserId, $currentUserId) ||
            User::isBlocked($currentUserId, $withUserId)
        ) {
            http_response_code(403);
            exit;
        }

        $db = Database::getInstance();

        // mark as read
        $db->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ?
        ")->execute([$withUserId, $currentUserId]);

        // fetch messages
        $stmt = $db->prepare("
            SELECT sender_id, receiver_id, message, created_at
            FROM chat_messages
            WHERE 
                (sender_id = ? AND receiver_id = ?)
             OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
            LIMIT 100
        ");
        $stmt->execute([
            $currentUserId, $withUserId,
            $withUserId, $currentUserId
        ]);

        header('Content-Type: application/json');
        echo json_encode($stmt->fetchAll());
        exit;
    }

    /* =========================
       إرسال رسالة (AJAX)
    ========================= */
    public function send(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit;
        }

        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            exit;
        }

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $senderId = Auth::user()['id'];

        if ($receiverId <= 0 || $message === '') {
            http_response_code(400);
            exit;
        }

        if (
            User::isBlocked($receiverId, $senderId) ||
            User::isBlocked($senderId, $receiverId)
        ) {
            http_response_code(403);
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $senderId,
            $receiverId,
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    /* =========================
       فتح محادثة من البروفايل
    ========================= */
    public function start(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        $withUserId = (int)($_GET['user_id'] ?? 0);
        $currentUserId = Auth::user()['id'];

        if ($withUserId <= 0) {
            $this->redirect('messages');
        }

        if (
            User::isBlocked($withUserId, $currentUserId) ||
            User::isBlocked($currentUserId, $withUserId)
        ) {
            $_SESSION['error'] = 'You cannot message this user';
            $this->redirect('messages');
        }

        $this->redirect('messages');
    }

    /* =========================
       View Specific Conversation
    ========================= */
    public function conversation(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        $withUserId = (int)($_GET['user_id'] ?? 0);
        $currentUserId = Auth::user()['id'];

        if ($withUserId <= 0) {
            $this->redirect('messages');
        }

        // Check if blocked
        if (
            User::isBlocked($withUserId, $currentUserId) ||
            User::isBlocked($currentUserId, $withUserId)
        ) {
            $_SESSION['error'] = 'You cannot message this user';
            $this->redirect('messages');
        }

        // Get user info
        $otherUser = User::find($withUserId);
        if (!$otherUser) {
            $this->redirect('messages');
        }

        // Render conversation view
        $this->view('conversation', [
            'other_user_id' => $withUserId,
            'other_user' => $otherUser,
            'csrf' => CSRF::generate(),
        ]);
    }}