<?php

class NotificationController extends Controller
{
    /**
     * List notifications (page or AJAX)
     */
    public function index(): void
    {
        if (!Auth::check()) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            http_response_code(403);
            exit('Unauthorized');
        }

        $userId = Auth::user()['id'];
        $notifications = $this->getUserNotifications($userId);

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode($notifications);
            exit;
        }

        $this->view('notifications', [
            'notifications' => $notifications,
            'csrf'          => CSRF::generate(),
        ]);
    }

    /**
     * Mark notification(s) as read
     */
    public function markRead(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit('Unauthorized');
        }

        $userId         = Auth::user()['id'];
        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        $db = Database::getInstance();

        if ($notificationId > 0) {
            $stmt = $db->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$notificationId, $userId]);
        } else {
            Notification::markAllRead($userId);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Handle friend request accept / reject
     */
    public function handleFriendRequest(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit('Unauthorized');
        }

        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $action     = $_POST['action'] ?? '';
        $senderId   = (int) ($_POST['sender_id'] ?? 0);
        $receiverId = Auth::user()['id'];

        if ($senderId <= 0 || !in_array($action, ['accept', 'reject'], true)) {
            http_response_code(400);
            exit('Invalid request');
        }

        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT id
             FROM friend_requests
             WHERE sender_id = ?
               AND receiver_id = ?
               AND status = "pending"
             LIMIT 1'
        );
        $stmt->execute([$senderId, $receiverId]);
        $request = $stmt->fetch();

        if (!$request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Friend request not found',
            ]);
            exit;
        }

        if ($action === 'accept') {
            User::acceptFriendRequest((int)$request['id'], $receiverId);
            Notification::updateFriendRequestNotifications(
                $senderId,
                $receiverId,
                'accepted'
            );

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Friend request accepted',
                'action'  => 'accepted',
            ]);
            exit;
        }

        if ($action === 'reject') {
            User::rejectFriendRequest((int)$request['id'], $receiverId);
            Notification::updateFriendRequestNotifications(
                $senderId,
                $receiverId,
                'rejected'
            );

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Friend request rejected',
                'action'  => 'rejected',
            ]);
            exit;
        }
    }

    /**
     * Check new notifications since last timestamp
     */
    public function checkNew(): void
    {
        if (!Auth::check()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userId    = Auth::user()['id'];
        $lastCheck = (int) ($_GET['last_check'] ?? 0);

        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT COUNT(*) AS count
             FROM notifications
             WHERE user_id = ?
               AND created_at > FROM_UNIXTIME(?)'
        );
        $stmt->execute([$userId, $lastCheck]);
        $result = $stmt->fetch();

        header('Content-Type: application/json');
        echo json_encode([
            'new_count' => (int) $result['count'],
            'timestamp' => time(),
        ]);
        exit;
    }

    /* =====================================================
     * Helpers
     * ===================================================== */

    private function isAjaxRequest(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || isset($_GET['ajax']);
    }

    private function getUserNotifications(int $userId): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT n.*,
                    u.username AS source_username,
                    u.avatar   AS source_avatar,
                    v.title    AS video_title
             FROM notifications n
             LEFT JOIN users u ON u.id = n.source_user_id
             LEFT JOIN videos v ON v.id = n.video_id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId]);

        $rows = $stmt->fetchAll();
        $result = [];

        foreach ($rows as $n) {
            $result[] = [
                'id'              => $n['id'],
                'user_id'         => $n['user_id'],
                'type'            => $n['type'],
                'source_user_id'  => $n['source_user_id'],
                'video_id'        => $n['video_id'],
                'comment_id'      => $n['comment_id'],
                'is_read'         => $n['is_read'],
                'created_at'      => $n['created_at'],
                'source_username' => $n['source_username'] ?? 'مستخدم مجهول',
                'source_avatar'   => $n['source_avatar'],
                'video_title'     => $n['video_title'] ?? 'فيديو غير معروف',
                'message'         => $this->generateMessage($n),
                'time_ago'        => $this->timeAgo($n['created_at']),
                'has_actions'     => $this->hasActions($n['type']),
            ];
        }

        return $result;
    }

    private function hasActions(string $type): bool
    {
        return $type === 'friend_request';
    }

    private function generateMessage(array $n): string
    {
        $user  = $n['source_username'] ?? 'مستخدم مجهول';
        $video = $n['video_title'] ?? 'فيديو غير معروف';

        switch ($n['type']) {
            case 'comment':
                return $user . ' علق على فيديوك: "' . $video . '"';
            case 'reply':
                return $user . ' رد على تعليقك في الفيديو: "' . $video . '"';
            case 'like':
                return $user . ' أعجب بفيديوك: "' . $video . '"';
            case 'video_uploaded':
                return 'تم رفع الفيديو بنجاح: "' . $video . '"';
            case 'friend_request':
                return $user . ' أرسل لك طلب صداقة';
            case 'friend_request_sent':
                return 'لقد أرسلت طلب صداقة إلى ' . $user;
            case 'friend_request_accepted':
                return $user . ' قبل طلب الصداقة';
            case 'friend_request_rejected':
                return $user . ' رفض طلب الصداقة';
            default:
                return 'إشعار جديد';
        }
    }

    private function timeAgo(?string $datetime): string
    {
        if (!$datetime) {
            return 'منذ فترة';
        }

        $time = strtotime($datetime);
        if ($time === false) {
            return 'منذ فترة';
        }

        $diff = time() - $time;

        if ($diff < 60) {
            return 'الآن';
        }
        if ($diff < 3600) {
            return 'منذ ' . floor($diff / 60) . ' دقيقة';
        }
        if ($diff < 86400) {
            return 'منذ ' . floor($diff / 3600) . ' ساعة';
        }
        if ($diff < 2592000) {
            return 'منذ ' . floor($diff / 86400) . ' يوم';
        }

        return date('Y-m-d', $time);
    }
}
