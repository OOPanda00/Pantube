<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/core/App.php';

App::run();

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $uri);
$parts = array_values(array_diff($parts, ['pantube', 'public']));

$action = $parts[0] ?? 'home';

/**
 * PASS IDS TO CONTROLLERS
 */
if ($action === 'watch' && isset($parts[1])) {
    $_GET['id'] = (int) $parts[1];
}

// profile uses query ?id= or no id
if ($action === 'profile' && isset($_GET['id'])) {
    $_GET['id'] = (int) $_GET['id'];
}

// messages routing: support /messages and /messages/conversation and query params
if ($action === 'messages') {
    // /messages/conversation/{user_id}  OR /messages/conversation?user_id=2
    if (isset($parts[1]) && $parts[1] === 'conversation') {
        // support /messages/conversation/{id}
        if (isset($parts[2]) && is_numeric($parts[2])) {
            $_GET['user_id'] = (int) $parts[2];
        } elseif (isset($_GET['user_id'])) {
            $_GET['user_id'] = (int) $_GET['user_id'];
        }
    } else {
        // just /messages (index) - no additional preparation required
    }
}

switch ($action) {

    case 'home':
        (new VideoController())->index();
        break;

    case 'watch':
        (new VideoController())->watch();
        break;

    case 'upload':
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? (new VideoController())->upload()
            : (new VideoController())->uploadForm();
        break;

    case 'login':
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? (new AuthController())->login()
            : (new AuthController())->loginForm();
        break;

    case 'register':
        $_SERVER['REQUEST_METHOD'] === 'POST'
            ? (new AuthController())->register()
            : (new AuthController())->registerForm();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'comment':
        (new CommentController())->store();
        break;

    case 'comment_delete':
        (new CommentController())->delete();
        break;

    case 'profile':
        (new ProfileController())->show();
        break;

    case 'profile_update':
        (new ProfileController())->update();
        break;

    case 'messages':
        // support /messages -> list, /messages/conversation -> specific conversation
        if (isset($parts[1]) && $parts[1] === 'conversation') {
            (new MessagesController())->conversation();
        } elseif (isset($parts[1]) && $parts[1] === 'start') {
            (new MessagesController())->start();
        } else {
            (new MessagesController())->index();
        }
        break;

    case 'chat_send':
        (new ChatController())->send();
        break;

    case 'chat_fetch':
        (new ChatController())->fetch();
        break;

    case 'like':
        (new LikeController())->toggle();
        break;

    case 'admin':
        (new AdminController())->dashboard();
        break;

    case 'admin_promote':
        (new AdminController())->promote();
        break;

    case 'admin_demote':
        (new AdminController())->demote();
        break;

    case 'delete':
        (new VideoController())->delete();
        break;

    case 'search':
        (new SearchController())->index();
        break;

    case 'notifications':
        (new NotificationController())->index();
        break;

    case 'notifications_read':
        (new NotificationController())->markRead();
        break;

    case 'notifications_check':
        (new NotificationController())->checkNew();
        break;

    case 'notifications_handle_friend_request':
        (new NotificationController())->handleFriendRequest();
        break;

    case 'chat_fetch':
        (new ChatController())->fetch();
        break;

    default:
        http_response_code(404);
        // Create a temporary controller to display 404 view
        $temp = new class extends Controller {
            public function show() { $this->view('404'); }
        };
        $temp->show();
}
