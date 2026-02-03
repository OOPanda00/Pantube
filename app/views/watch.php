<?php
$likes          = $likes ?? 0;
$dislikes       = $dislikes ?? 0;
$userReaction   = $userReaction ?? null;
$comments       = $comments ?? [];
$suggested      = $suggested ?? [];

$totalReactions = $likes + $dislikes;
$likePercentage = $totalReactions > 0 ? round(($likes / $totalReactions) * 100) : 0;

$videoFile  = !empty($video['filename']) ? basename($video['filename']) : '';
$videoPath  = 'uploads/videos/' . $videoFile;
$videoExist = $videoFile && file_exists(__DIR__ . '/../../public/' . $videoPath);

$commentsCount = count($comments);
?>

<div class="watch-page">
    <div class="watch-layout">

        <!-- MAIN -->
        <div class="watch-main">

            <div class="video-wrapper">
                <?php if ($videoExist): ?>
                    <video class="watch-video" controls
                           poster="uploads/thumbnails/<?= htmlspecialchars($video['thumbnail'] ?? '') ?>">
                        <source src="<?= htmlspecialchars($videoPath) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <div class="video-missing">Video not found</div>
                <?php endif; ?>
            </div>

            <h1 class="watch-title"><?= htmlspecialchars($video['title']) ?></h1>

            <div class="watch-meta">
                <span class="views-count"><?= (int)$video['views'] ?> views</span>

                <?php if (Auth::check()): ?>
                    <div class="video-actions"
                         data-video-id="<?= (int)$video['id'] ?>"
                         data-csrf="<?= htmlspecialchars($csrf) ?>">

                        <button class="like-btn <?= $userReaction === 'like' ? 'active' : '' ?>"
                                data-type="like">
                            👍 <span id="likeCount"><?= $likes ?></span>
                        </button>

                        <button class="like-btn <?= $userReaction === 'dislike' ? 'active' : '' ?>"
                                data-type="dislike">
                            👎 <span id="dislikeCount"><?= $dislikes ?></span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalReactions > 0): ?>
                <div class="like-ratio">
                    <div class="like-ratio-bar">
                        <div class="like-ratio-fill"
                             id="likeRatioFill"
                             style="width: <?= $likePercentage ?>%"></div>
                    </div>
                    <div class="like-ratio-text">
                        <span id="likePercentage"><?= $likePercentage ?>%</span> liked
                    </div>
                </div>
            <?php endif; ?>

            <div class="watch-channel">
                <a href="profile?id=<?= (int)$video['user_id'] ?>">
                    <?= htmlspecialchars($video['username']) ?>
                </a>
            </div>

            <?php if (!empty($video['description'])): ?>
                <div class="video-description">
                    <h3>Description</h3>
                    <?= nl2br(htmlspecialchars($video['description'])) ?>
                </div>
            <?php endif; ?>

            <!-- COMMENTS -->
            <div class="comments-section">

                <div class="comments-header">
                    <h3>
                        Comments
                        <span id="commentsCount"><?= $commentsCount ?></span>
                    </h3>

                    <button id="toggleCommentsBtn" class="toggle-comments-btn">
                        <span class="icon">▼</span>
                        <span class="text">Hide Comments</span>
                    </button>
                </div>

                <div class="comments-container" id="commentsContainer">

                    <?php if (Auth::check()): ?>
                        <form method="POST" action="comment" id="commentForm" class="comment-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="video_id" value="<?= (int)$video['id'] ?>">
                            <input type="hidden" name="parent_id" id="parentIdInput">

                            <textarea id="commentTextarea"
                                      name="content"
                                      rows="3"
                                      placeholder="Add a comment..."
                                      required></textarea>

                            <div class="comment-form-actions">
                                <button type="submit">💬 Add Comment</button>
                                <button type="button" id="cancelReplyBtn" style="display:none">
                                    Cancel Reply
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p>Please <a href="login">login</a> to comment</p>
                    <?php endif; ?>

                    <div class="comments-list">
                        <?php require __DIR__ . '/partials/comments.php'; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- SIDEBAR -->
        <aside class="watch-sidebar">
            <h3>Suggested Videos</h3>

            <?php if ($suggested): ?>
                <?php foreach ($suggested as $s): ?>
                    <a class="suggested-card" href="watch/<?= (int)$s['id'] ?>">
                        <div class="suggested-thumbnail">
                            <?php if ($s['thumbnail']): ?>
                                <img src="uploads/thumbnails/<?= htmlspecialchars($s['thumbnail']) ?>">
                            <?php else: ?>
                                <div class="thumbnail-placeholder">🎬</div>
                            <?php endif; ?>
                        </div>
                        <div class="suggested-info">
                            <div class="suggested-title"><?= htmlspecialchars($s['title']) ?></div>
                            <div class="suggested-channel"><?= htmlspecialchars($s['username']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📺</div>
                    <h3>No suggested videos</h3>
                    <p>Check back later for more content.</p>
                </div>
            <?php endif; ?>
        </aside>

    </div>
</div>

<script>
window.PANTUBE_VIDEO_ID = <?= (int)$video['id'] ?>;
window.PANTUBE_CSRF_TOKEN = "<?= htmlspecialchars($csrf) ?>";
</script>
