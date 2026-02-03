<?php

class ProfileController extends Controller
{
    public function show(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        User::ensureTablesExist();
        $db = Database::getInstance();

        if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
            $userId = (int)$_GET['id'];
            $user = User::find($userId);

            if (!$user) {
                http_response_code(404);
                exit('User not found');
            }
        } else {
            $user = Auth::user();
        }

        $currentUser = Auth::user();
        $isOwnProfile = $currentUser['id'] === $user['id'];

        $stats = User::getStats($user['id']);

        $stmt = $db->prepare(
            'SELECT * FROM videos WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$user['id']]);
        $videos = $stmt->fetchAll();

        $relationships = [];

        if (!$isOwnProfile) {
            try {
                $relationships = [
                    'is_following'     => User::isFollowing($currentUser['id'], $user['id']),
                    'is_blocked'       => User::isBlocked($currentUser['id'], $user['id']),
                    'has_blocked_me'   => User::isBlocked($user['id'], $currentUser['id']),
                    'friendship_status'=> User::getFriendshipStatus($currentUser['id'], $user['id']),
                ];
            } catch (Throwable $e) {
                error_log('Profile relationship error: ' . $e->getMessage());
                $relationships = [
                    'is_following'     => false,
                    'is_blocked'       => false,
                    'has_blocked_me'   => false,
                    'friendship_status'=> null,
                ];
            }
        }

        $this->view('profile', [
            'user'            => $user,
            'videos'          => $videos,
            'stats'           => $stats,
            'is_own_profile'  => $isOwnProfile,
            'relationships'   => $relationships,
            'csrf'            => CSRF::generate(),
        ]);
    }

    public function update(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            $this->redirect('profile');
        }

        User::ensureTablesExist();

        $currentUser = Auth::user();
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_avatar':
                $this->updateAvatar($currentUser['id']);
                break;

            case 'update_username':
                $this->updateUsername($currentUser['id']);
                break;

            case 'update_bio':
                $this->updateBio($currentUser['id']);
                break;

            case 'update_password':
                $this->updatePassword($currentUser['id']);
                break;

            case 'follow':
                $this->followUser($currentUser['id']);
                break;

            case 'unfollow':
                $this->unfollowUser($currentUser['id']);
                break;

            case 'block':
                $this->blockUser($currentUser['id']);
                break;

            case 'unblock':
                $this->unblockUser($currentUser['id']);
                break;

            case 'send_friend_request':
                $this->sendFriendRequest($currentUser['id']);
                break;

            case 'accept_friend_request':
                $this->acceptFriendRequest($currentUser['id']);
                break;

            case 'reject_friend_request':
                $this->rejectFriendRequest($currentUser['id']);
                break;

            case 'remove_friend':
                $this->removeFriend($currentUser['id']);
                break;

            default:
                $this->redirect('profile');
        }
    }

    private function updateAvatar(int $userId): void
    {
        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Invalid image file';
            $this->redirect('profile');
        }

        $file = $_FILES['avatar'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Unsupported image format';
            $this->redirect('profile');
        }

        $uploadDir = __DIR__ . '/../../public/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $_SESSION['error'] = 'Upload failed';
            $this->redirect('profile');
        }

        User::updateAvatar($userId, $filename);
        $_SESSION['user']['avatar'] = $filename;
        $_SESSION['success'] = 'Avatar updated';

        $this->redirect('profile');
    }

    private function updateUsername(int $userId): void
    {
        $username = trim($_POST['username'] ?? '');

        if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
            $_SESSION['error'] = 'Invalid username';
            $this->redirect('profile');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $userId]);

        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Username already taken';
            $this->redirect('profile');
        }

        User::updateUsername($userId, $username);
        $_SESSION['user']['username'] = $username;
        $_SESSION['success'] = 'Username updated';

        $this->redirect('profile');
    }

    private function updateBio(int $userId): void
    {
        $bio = trim($_POST['bio'] ?? '');

        if (strlen($bio) > 500) {
            $bio = substr($bio, 0, 500);
        }

        User::updateBio($userId, htmlspecialchars($bio, ENT_QUOTES, 'UTF-8'));
        $_SESSION['user']['bio'] = $bio;
        $_SESSION['success'] = 'Bio updated';

        $this->redirect('profile');
    }

    private function updatePassword(int $userId): void
    {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '' || $confirmPassword === '') {
            $_SESSION['error'] = 'Password fields are required';
            $this->redirect('profile');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'New passwords do not match';
            $this->redirect('profile');
        }

        if (strlen($newPassword) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters';
            $this->redirect('profile');
        }

        // Verify old password
        $user = Auth::user();
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $stored = $stmt->fetch();

        if (!$stored || !password_verify($oldPassword, $stored['password'])) {
            $_SESSION['error'] = 'Current password is incorrect';
            $this->redirect('profile');
        }

        // Update password
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hashed, $userId]);

        $_SESSION['success'] = 'Password updated successfully';
        $this->redirect('profile');
    }

    private function followUser(int $followerId): void
    {
        $targetId = (int)($_POST['user_id'] ?? 0);

        if ($targetId <= 0 || $targetId === $followerId) {
            $this->redirect('profile');
        }

        User::follow($followerId, $targetId);
        $this->redirect('profile?id=' . $targetId);
    }

    private function unfollowUser(int $followerId): void
    {
        $targetId = (int)($_POST['user_id'] ?? 0);

        User::unfollow($followerId, $targetId);
        $this->redirect('profile?id=' . $targetId);
    }

    private function blockUser(int $blockerId): void
    {
        $blockedId = (int)($_POST['user_id'] ?? 0);

        User::block($blockerId, $blockedId);
        $this->redirect('profile?id=' . $blockedId);
    }

    private function unblockUser(int $blockerId): void
    {
        $blockedId = (int)($_POST['user_id'] ?? 0);

        User::unblock($blockerId, $blockedId);
        $this->redirect('profile?id=' . $blockedId);
    }

    private function sendFriendRequest(int $senderId): void
    {
        $receiverId = (int)($_POST['user_id'] ?? 0);

        User::sendFriendRequest($senderId, $receiverId);
        $this->redirect('profile?id=' . $receiverId);
    }

    private function acceptFriendRequest(int $receiverId): void
    {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $senderId = (int)($_POST['user_id'] ?? 0);
            if ($senderId > 0) {
                $requestId = User::getPendingFriendRequestId($senderId, $receiverId);
            }
        }
        if ($requestId > 0) {
            User::acceptFriendRequest($requestId, $receiverId);
        }
        $this->redirect('profile');
    }

    private function rejectFriendRequest(int $receiverId): void
    {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $senderId = (int)($_POST['user_id'] ?? 0);
            if ($senderId > 0) {
                $requestId = User::getPendingFriendRequestId($senderId, $receiverId);
            }
        }
        if ($requestId > 0) {
            User::rejectFriendRequest($requestId, $receiverId);
        }
        $this->redirect('profile');
    }

    private function removeFriend(int $userId): void
    {
        $friendId = (int)($_POST['user_id'] ?? 0);

        User::removeFriend($userId, $friendId);
        $this->redirect('profile?id=' . $friendId);
    }
}
