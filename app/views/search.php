<?php
$query = $query ?? '';
$type = $type ?? 'all';
$results = $results ?? [];
$pagination = $pagination ?? [];
?>

<div class="search-page">
    <div class="search-header">
        <h1>Search</h1>
        <form action="search" method="GET" class="search-form">
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search videos, users, or user ID...">
            <label for="searchType" class="sr-only">Search in</label>
            <select name="type" id="searchType" class="search-type-select">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All</option>
                <option value="videos" <?= $type === 'videos' ? 'selected' : '' ?>>Videos</option>
                <option value="users" <?= $type === 'users' ? 'selected' : '' ?>>Users</option>
            </select>
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($query !== ''): ?>
        <div class="search-info">
            <p>Results for: <strong><?= htmlspecialchars($query) ?></strong><?= $type !== 'all' ? ' ('. htmlspecialchars($type) . ')' : '' ?></p>
        </div>

        <?php if (empty($results)): ?>
            <div class="no-results">
                <p>No results found for "<?= htmlspecialchars($query) ?>"</p>
                <p>Try different keywords, or search by user ID (e.g. 1, 2) when type is Users.</p>
            </div>
        <?php else: ?>
            <div class="search-results">
                <?php foreach ($results as $result): ?>
                    <?php
                    $isVideo = ($result['result_type'] ?? '') === 'video' || isset($result['filename']);
                    ?>
                    <?php if ($isVideo): ?>
                        <div class="search-result-video">
                            <a href="watch/<?= (int)$result['id'] ?>" class="result-thumbnail">
                                <?php if (!empty($result['thumbnail'])): ?>
                                    <img src="uploads/thumbnails/<?= htmlspecialchars($result['thumbnail']) ?>" alt="<?= htmlspecialchars($result['title']) ?>">
                                <?php else: ?>
                                    <div class="result-placeholder">🎬</div>
                                <?php endif; ?>
                            </a>
                            <div class="result-info">
                                <h3><a href="watch/<?= (int)$result['id'] ?>"><?= htmlspecialchars($result['title']) ?></a></h3>
                                <p class="result-description"><?= htmlspecialchars(substr($result['description'] ?? '', 0, 150)) ?></p>
                                <div class="result-meta">
                                    <span><?= (int)$result['views'] ?> views</span>
                                    <span>•</span>
                                    <span><?= date('M d, Y', strtotime($result['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="search-result-user">
                            <a href="profile?id=<?= (int)$result['id'] ?>" class="result-avatar">
                                <?php if (!empty($result['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($result['avatar']) ?>" alt="<?= htmlspecialchars($result['username']) ?>">
                                <?php else: ?>
                                    <div class="avatar-placeholder">👤</div>
                                <?php endif; ?>
                            </a>
                            <div class="result-info">
                                <h3><a href="profile?id=<?= (int)$result['id'] ?>"><?= htmlspecialchars($result['username'] ?? '') ?></a></h3>
                                <p class="result-user-id">ID: <?= (int)$result['id'] ?></p>
                                <a href="profile?id=<?= (int)$result['id'] ?>" class="btn btn-sm btn-primary">View Profile</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if (isset($pagination['total']) && $pagination['total'] > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $pagination['total']; $i++): ?>
                        <a href="search?q=<?= urlencode($query) ?>&type=<?= urlencode($type) ?>&page=<?= $i ?>" 
                           class="pagination-link <?= $i === $pagination['current'] ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="search-empty">
            <p>Enter a search term to find videos and users</p>
        </div>
    <?php endif; ?>
</div>

<style>
.search-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px 16px;
}

.search-header {
    margin-bottom: 30px;
}

.search-header h1 {
    font-size: 28px;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-form input {
    flex: 1;
    min-width: 120px;
    padding: 10px 16px;
    border: 1px solid var(--border-color);
    border-radius: 24px;
    font-size: 14px;
}

.search-form input:focus {
    outline: none;
    border-color: var(--primary-color);
    background: white;
}

.search-form button {
    padding: 10px 20px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 24px;
    cursor: pointer;
    font-weight: 500;
}

.search-form button:hover {
    background: #ff3838;
}

.search-type-select {
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: 24px;
    font-size: 14px;
    font-family: inherit;
    background: var(--light-bg);
    color: var(--text-dark);
}

.search-type-select:focus {
    outline: none;
    border-color: var(--primary-color);
}

.result-user-id {
    font-size: 12px;
    color: var(--text-light);
    margin: 4px 0 8px;
}

.search-info {
    margin-bottom: 20px;
    padding: 10px 16px;
    background: var(--light-bg);
    border-radius: 8px;
}

.search-results {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.search-result-video,
.search-result-user {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: white;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.search-result-video:hover,
.search-result-user:hover {
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-color);
}

.result-thumbnail,
.result-avatar {
    flex-shrink: 0;
    display: block;
    border-radius: 8px;
    overflow: hidden;
    width: 120px;
    height: 120px;
}

.result-thumbnail img,
.result-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.result-placeholder,
.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    background: var(--light-bg);
}

.result-info {
    flex: 1;
    min-width: 0;
}

.result-info h3 {
    margin: 0 0 8px;
    font-size: 18px;
}

.result-info h3 a {
    color: var(--text-dark);
    text-decoration: none;
}

.result-info h3 a:hover {
    color: var(--primary-color);
}

.result-description {
    margin: 8px 0;
    color: var(--text-light);
    font-size: 14px;
    line-height: 1.4;
}

.result-meta {
    font-size: 12px;
    color: var(--text-light);
    margin-top: 8px;
}

.result-meta span:not(:last-child) {
    margin-right: 6px;
}

.no-results,
.search-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-light);
}

.no-results p,
.search-empty p {
    margin: 10px 0;
    font-size: 16px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination-link {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.2s;
}

.pagination-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.pagination-link.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

@media (max-width: 575px) {
    .search-form {
        flex-wrap: wrap;
    }

    .search-form button {
        width: 100%;
    }

    .search-result-video,
    .search-result-user {
        flex-direction: column;
    }

    .result-thumbnail,
    .result-avatar {
        width: 100%;
        height: 200px;
    }
}
</style>
