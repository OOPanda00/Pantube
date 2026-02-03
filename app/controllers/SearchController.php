<?php
/**
 * Search Controller
 * Supports search by keyword and by user ID (when type=users or type=all).
 */

class SearchController extends Controller
{
    public function index(): void
    {
        $query = trim($_GET['q'] ?? '');
        $type  = $_GET['type'] ?? 'all';
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $minLength = 2;
        $queryNumeric = ctype_digit($query) ? (int)$query : null;

        if (strlen($query) < $minLength && $queryNumeric === null) {
            $this->view('search', [
                'query' => $query,
                'type' => $type,
                'results' => [],
                'pagination' => ['current' => 1, 'total' => 0],
                'csrf' => CSRF::generate(),
            ]);
            return;
        }

        $results = [];
        $total = 0;

        switch ($type) {
            case 'videos':
                $data = $this->searchVideos($query, $limit, $offset);
                $results = $this->markResultType($data['results'], 'video');
                $total = $data['total'];
                break;
            case 'users':
                $data = $this->searchUsers($query, $queryNumeric, $limit, $offset);
                $results = $this->markResultType($data['results'], 'user');
                $total = $data['total'];
                break;
            default:
                $videoData = $this->searchVideos($query, $limit, $offset);
                $userData = $this->searchUsers($query, $queryNumeric, $limit, $offset);
                $results = array_merge(
                    $this->markResultType($videoData['results'], 'video'),
                    $this->markResultType($userData['results'], 'user')
                );
                $total = $videoData['total'] + $userData['total'];
        }

        $paginationTotal = ($type === 'all') ? 1 : (int)max(1, ceil($total / $limit));

        $this->view('search', [
            'query' => htmlspecialchars($query),
            'type' => $type,
            'results' => $results,
            'pagination' => [
                'current' => $page,
                'total' => $paginationTotal,
            ],
            'csrf' => CSRF::generate(),
        ]);
    }

    private function markResultType(array $rows, string $type): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $row['result_type'] = $type;
            $out[] = $row;
        }
        return $out;
    }

    private function searchVideos(string $query, int $limit, int $offset): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT SQL_CALC_FOUND_ROWS *
            FROM videos
            WHERE title LIKE ? OR description LIKE ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $like = "%{$query}%";
        $stmt->execute([$like, $like, $limit, $offset]);

        return [
            'results' => $stmt->fetchAll(),
            'total' => (int)$db->query('SELECT FOUND_ROWS()')->fetchColumn(),
        ];
    }

    private function searchUsers(string $query, ?int $queryNumeric, int $limit, int $offset): array
    {
        $db = Database::getInstance();

        if ($queryNumeric !== null) {
            $stmt = $db->prepare("
                SELECT SQL_CALC_FOUND_ROWS id, username, avatar
                FROM users
                WHERE id = ? OR username LIKE ? OR email LIKE ?
                ORDER BY id = ? DESC, created_at DESC
                LIMIT ? OFFSET ?
            ");
            $like = "%{$query}%";
            $stmt->execute([$queryNumeric, $like, $like, $queryNumeric, $limit, $offset]);
        } else {
            $stmt = $db->prepare("
                SELECT SQL_CALC_FOUND_ROWS id, username, avatar
                FROM users
                WHERE username LIKE ? OR email LIKE ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $like = "%{$query}%";
            $stmt->execute([$like, $like, $limit, $offset]);
        }

        return [
            'results' => $stmt->fetchAll(),
            'total' => (int)$db->query('SELECT FOUND_ROWS()')->fetchColumn(),
        ];
    }
}
