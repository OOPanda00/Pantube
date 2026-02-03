<?php
/**
 * Admin Controller
 * Owner/Admin management
 */

class AdminController extends Controller
{
    /**
     * Admin dashboard
     */
    public function dashboard(): void
    {
        if (!Auth::isAdmin() && !Auth::isOwner()) {
            http_response_code(403);
            exit('Access denied');
        }

        $db = Database::getInstance();

        $users = $db->query(
            'SELECT id, username, email, role FROM users ORDER BY id ASC'
        )->fetchAll();

        $this->view('admin_dashboard', [
            'users' => $users
        ]);
    }

    /**
     * Promote user to admin (Owner only)
     */
    public function promote(): void
    {
        if (!Auth::canManageAdmins()) {
            http_response_code(403);
            exit('Only owner can promote admins');
        }

        $userId = (int) ($_GET['id'] ?? 0);

        if ($userId <= 1) {
            exit('Invalid user');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users SET role = 'admin' WHERE id = ?"
        );
        $stmt->execute([$userId]);

        $this->redirect('admin');
    }

    /**
     * Demote admin to user (Owner only)
     */
    public function demote(): void
    {
        if (!Auth::canManageAdmins()) {
            http_response_code(403);
            exit('Only owner can demote admins');
        }

        $userId = (int) ($_GET['id'] ?? 0);

        if ($userId <= 1) {
            exit('Owner role cannot be changed');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users SET role = 'user' WHERE id = ?"
        );
        $stmt->execute([$userId]);

        $this->redirect('admin');
    }
}
