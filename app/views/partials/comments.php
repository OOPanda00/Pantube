<?php
// =========================
// Helper: Time Ago
// =========================
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now  = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $m = floor($diff / 60);
        return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $h = floor($diff / 3600);
        return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $d = floor($diff / 86400);
        return $d . ' day' . ($d > 1 ? 's' : '') . ' ago';
    }

    return date('M d, Y', $time);
}

// =========================
// Render Comment (Recursive)
// =========================
function renderComment($comment, $videoId, $csrf, $depth = 0)
{
    $username = $comment['username'] ?? 'Unknown';
    $avatar   = $comment['avatar']   ?? 'default.png';

    $avatarPath = __DIR__ . '/../../../public/uploads/avatars/' . $avatar;
    if (!file_exists($avatarPath) || empty($avatar)) {
        $avatar = 'default.png';
    }

    $canDelete = false;

    if (Auth::check()) {
        if (
            Auth::user()['id'] == $comment['user_id'] ||
            Auth::isAdmin() ||
            Auth::isOwner()
        ) {
            $canDelete = true;
        }
    }
?>
<div class="comment" data-comment-id="<?= (int)$comment['id'] ?>">

    <div class="comment-header">
        <img
            src="uploads/avatars/<?= htmlspecialchars($avatar) ?>"
            alt="<?= htmlspecialchars($username) ?>"
            class="comment-avatar"
        >
        <div class="comment-author"><?= htmlspecialchars($username) ?></div>
        <div class="comment-time"><?= timeAgo($comment['created_at']) ?></div>
    </div>

    <div class="comment-content">
        <?= nl2br(htmlspecialchars($comment['content'])) ?>
    </div>

    <?php if (Auth::check()): ?>
        <div class="comment-actions">

            <button
                class="comment-action-btn reply-btn"
                data-comment-id="<?= (int)$comment['id'] ?>"
            >
                <span class="icon">↩️</span>
                <span class="text">Reply</span>
            </button>

            <?php if ($canDelete): ?>
                <button
                    class="comment-action-btn delete-btn"
                    data-comment-id="<?= (int)$comment['id'] ?>"
                >
                    <span class="icon">🗑️</span>
                    <span class="text">Delete</span>
                </button>
            <?php endif; ?>

        </div>

        <!-- Reply Form -->
        <div
            class="reply-form-container"
            id="replyForm-<?= (int)$comment['id'] ?>"
            style="display:none;"
        >
            <form method="POST" action="comment" class="reply-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="video_id" value="<?= (int)$videoId ?>">
                <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">

                <textarea
                    name="content"
                    rows="3"
                    required
                    placeholder="Write a reply..."
                ></textarea>

                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="submit" class="submit-reply-btn">
                        💬 Post Reply
                    </button>

                    <button type="button" class="cancel-reply-btn">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Replies -->
    <?php if (!empty($comment['replies'])): ?>
        <div class="replies">
            <?php foreach ($comment['replies'] as $reply): ?>
                <?php renderComment($reply, $videoId, $csrf, $depth + 1); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
<?php
}

// =========================
// Render All Comments
// =========================
if (!empty($comments)) {
    foreach ($comments as $comment) {
        renderComment($comment, $video['id'], $csrf);
    }
} else {
    echo '<div class="no-comments" id="noComments">
            No comments yet. Be the first to comment!
          </div>';
}
?>
