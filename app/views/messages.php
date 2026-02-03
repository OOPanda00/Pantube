<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base = $base === '' ? '' : $base;
?>

<div class="messages-page">

    <div class="messages-header">
        <h1 class="page-title">💬 Messages</h1>

        <div class="messages-search">
            <input type="text" id="searchUser" placeholder="Search users...">
            <div id="searchResults" class="search-results"></div>
        </div>
    </div>

    <div class="messages-container">
        <div class="conversations-list">
            <?php if (empty($conversations)): ?>
                <div class="empty-conversations">
                    <div class="empty-icon">💬</div>
                    <h3>No conversations yet</h3>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <a href="<?= $base ?>/messages/conversation?user_id=<?= (int)($conv['other_user_id'] ?? $conv['id'] ?? 0) ?>"
                       class="conversation-item <?= !empty($conv['unread_count']) ? 'unread' : '' ?>">

                        <div class="conversation-avatar">
                            <?php if (!empty($conv['avatar'])): ?>
                                <img src="<?= $base ?>/uploads/avatars/<?= htmlspecialchars($conv['avatar']) ?>">
                            <?php else: ?>
                                <div class="default-avatar">👤</div>
                            <?php endif; ?>
                        </div>

                        <div class="conversation-info">
                            <div class="conversation-name"><?= htmlspecialchars($conv['username']) ?></div>
                            <div class="conversation-time">
                                <?= date('M d, H:i', strtotime($conv['last_message_time'] ?? date('Y-m-d H:i:s'))) ?>
                            </div>
                        </div>

                        <?php if (!empty($conv['unread_count'])): ?>
                            <span class="unread-badge"><?= min($conv['unread_count'], 9) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
