<?php

/**
 * Video Controller
 * Handles home, watch, upload
 */
class VideoController extends Controller
{
    public function index(): void
    {
        $videos = Video::latest(20);

        $this->view('home', [
            'videos' => $videos,
        ]);
    }

    public function watch(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            exit('Video not found');
        }

        $video = Video::find($id);

        if (!$video) {
            http_response_code(404);
            exit('Video not found');
        }

        Video::incrementViews($id);

        $likes      = Like::count($id, 'like');
        $dislikes   = Like::count($id, 'dislike');
        $comments   = Comment::treeForVideo($id);
        $suggested  = Video::suggested($id, 8);
        $userReaction = null;

        if (Auth::check() && method_exists('Like', 'userReaction')) {
            $u = Auth::user();
            $userReaction = Like::userReaction((int) $u['id'], $id);
        }

        $this->view('watch', [
            'video'        => $video,
            'likes'        => $likes,
            'dislikes'     => $dislikes,
            'comments'     => $comments,
            'suggested'    => $suggested,
            'userReaction' => $userReaction,
            'csrf'         => CSRF::generate(),
        ]);
    }

    public function delete(): void
    {
        error_log('[VIDEO_DELETE_TRACE] Delete request received from ' . $_SERVER['REMOTE_ADDR']);
        error_log('[VIDEO_DELETE_TRACE] REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('[VIDEO_DELETE_TRACE] $_GET: ' . json_encode($_GET));
        
        if (!Auth::check()) {
            error_log('[VIDEO_DELETE_TRACE] Auth check failed - user not logged in');
            http_response_code(403);
            exit('Unauthorized');
        }

        error_log('[VIDEO_DELETE_TRACE] User logged in: ' . Auth::user()['id']);
        
        $videoId = (int) ($_GET['id'] ?? 0);

        if ($videoId <= 0) {
            error_log('[VIDEO_DELETE_TRACE] Invalid video ID: ' . $videoId);
            http_response_code(400);
            exit('Invalid video ID');
        }

        $video = Video::find($videoId);

        if (!$video) {
            error_log('[VIDEO_DELETE_TRACE] Video not found: ' . $videoId);
            http_response_code(404);
            exit('Video not found');
        }

        $user = Auth::user();
        error_log('[VIDEO_DELETE_TRACE] Video owner: ' . $video['user_id'] . ', Current user: ' . $user['id'] . ', User role: ' . ($user['role'] ?? 'unknown'));

        if (
            !Auth::isOwner() &&
            !Auth::isAdmin() &&
            $video['user_id'] !== $user['id']
        ) {
            error_log('[VIDEO_DELETE_TRACE] Permission denied - isOwner: ' . (Auth::isOwner() ? 'true' : 'false') . ', isAdmin: ' . (Auth::isAdmin() ? 'true' : 'false'));
            http_response_code(403);
            exit('Permission denied');
        }

        error_log('[VIDEO_DELETE] User ' . $user['id'] . ' attempting to delete video ' . $videoId . ' (' . $video['filename'] . ')');
        
        try {
            $deleted = Video::delete($videoId);
            
            if ($deleted) {
                error_log('[VIDEO_DELETE] SUCCESS - Video ' . $videoId . ' deleted by user ' . $user['id']);
                
                // Clear cache after delete
                Cache::delete('videos_latest_20');
                Cache::delete('videos_all');
                
                // Redirect to home with query param to show success message
                $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                header('Location: ' . ($base ?: '') . '/home?deleted=1');
                exit;
            } else {
                error_log('[VIDEO_DELETE] FAILED - Could not delete video ' . $videoId);
                http_response_code(500);
                exit('Failed to delete video');
            }
        } catch (Exception $e) {
            error_log('[VIDEO_DELETE] EXCEPTION - ' . $e->getMessage() . ' (Video: ' . $videoId . ')');
            http_response_code(500);
            exit('Error deleting video: ' . $e->getMessage());
        }
    }

    public function uploadForm(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
        }

        $this->view('upload', [
            'csrf' => CSRF::generate(),
        ]);
    }

    public function upload(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit('Unauthorized');
        }

        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || empty($_FILES['video']['name'])) {
            exit('Invalid input');
        }

        // Validate file upload errors
        if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            ];
            exit($errors[$_FILES['video']['error']] ?? 'Unknown upload error');
        }

        $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));

        // Validate extension
        $allowedTypes = App::$config['upload']['allowed_video_types'] ?? ['mp4'];
        if (!in_array($ext, $allowedTypes)) {
            exit('Only ' . implode(', ', $allowedTypes) . ' files allowed');
        }

        // Validate file size
        $maxSize = App::$config['upload']['max_video_size'] ?? (500 * 1024 * 1024);
        if ($_FILES['video']['size'] > $maxSize) {
            exit('File exceeds maximum size of ' . round($maxSize / 1024 / 1024) . 'MB');
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['video']['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['video/mp4', 'video/x-msvideo', 'video/quicktime', 'video/x-matroska'];
        if (!in_array($mimeType, $allowedMimes)) {
            exit('Invalid video file type. Detected: ' . $mimeType);
        }

        $filename = uniqid('video_') . '.mp4';
        $target   = App::$config['paths']['uploads'] . '/videos/' . $filename;

        if (!move_uploaded_file($_FILES['video']['tmp_name'], $target)) {
            exit('Upload failed');
        }

        $thumbName = uniqid('thumb_') . '.jpg';
        $thumbPath = App::$config['paths']['uploads'] . '/thumbnails/' . $thumbName;

        // Use configurable FFmpeg path
        $ffmpegPath = env('FFMPEG_PATH', 'ffmpeg');
        $cmd = sprintf(
            '%s -i %s -ss 00:00:03 -vframes 1 %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($target),
            escapeshellarg($thumbPath)
        );
        
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            error_log('FFmpeg error: ' . implode("\n", $output));
            // Continue anyway - thumbnail generation is not critical
        }

        Video::create([
            'user_id'     => Auth::user()['id'],
            'title'       => $title,
            'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
            'filename'    => $filename,
            'thumbnail'   => $thumbName,
        ]);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id FROM videos WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([Auth::user()['id']]);
        $newVideo = $stmt->fetch();

        Notification::create([
            'user_id'        => Auth::user()['id'],
            'type'           => 'video_uploaded',
            'source_user_id' => Auth::user()['id'],
            'video_id'       => $newVideo['id'],
            'comment_id'     => null,
        ]);

        CSRF::destroy();
        $this->redirect('');
    }
}
