<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base = $base === '' ? '' : $base;
?>

<h2 class="page-title">Latest Videos</h2>

<?php if (empty($videos)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎬</div>
        <h3>No videos yet</h3>
        <p>Be the first to upload a video and share it with others.</p>
    </div>
<?php else: ?>
    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
            <div class="video-card">

                <a href="watch/<?= (int)$video['id'] ?>" class="thumb">
                    <?php if (!empty($video['thumbnail'])): ?>
                        <img src="uploads/thumbnails/<?= htmlspecialchars($video['thumbnail']) ?>">
                    <?php else: ?>
                        <div class="thumb-inner">🎬</div>
                    <?php endif; ?>
                </a>

                <div class="video-info">
                    <a href="watch/<?= (int)$video['id'] ?>" class="video-title">
                        <?= htmlspecialchars($video['title']) ?>
                    </a>

                    <div class="video-meta">
                        <a href="profile?id=<?= (int)$video['user_id'] ?>" class="channel-name">
                            <?= htmlspecialchars($video['username']) ?>
                        </a>
                        • <span><?= (int)$video['views'] ?> views</span>
                    </div>

                    <?php if (
                        Auth::check() &&
                        (Auth::isOwner() || Auth::isAdmin() || Auth::user()['id'] === $video['user_id'])
                    ): ?>
                        <button class="delete-btn" data-video-id="<?= (int)$video['id'] ?>" onclick="deleteVideo(event, <?= (int)$video['id'] ?>)">
                            Delete
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
