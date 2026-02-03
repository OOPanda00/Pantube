<?php
/**
 * Authentication Controller
 * Handles register, login, logout
 */

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function loginForm(): void
    {
        $this->view('login', [
            'csrf' => CSRF::generate()
        ]);
    }

    /**
     * Handle login request
     */
    public function login(): void
    {
        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            exit('Missing credentials');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            exit('Invalid email or password');
        }

        Auth::login($user);
        CSRF::destroy();

        // Redirect to home (relative to project, not server root)
        $this->redirect('');
    }

    /**
     * Show register form
     */
    public function registerForm(): void
    {
        $this->view('register', [
            'csrf' => CSRF::generate()
        ]);
    }

    /**
     * Handle registration
     */
    public function register(): void
    {
        if (!CSRF::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            exit('All fields required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit('Invalid email format');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
        );

        try {
            $stmt->execute([$username, $email, $hash]);
        } catch (PDOException $e) {
            exit('User already exists');
        }

        CSRF::destroy();

        // Redirect to login page
        $this->redirect('login');
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        Auth::logout();

        // Always redirect using controller helper
        $this->redirect('login');
    }
}
