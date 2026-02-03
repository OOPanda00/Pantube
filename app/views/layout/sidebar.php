<aside class="sidebar">

    <div class="sidebar-user">
        <?php if (Auth::check()): ?>
            <img src="<?= $base ?>/uploads/avatars/<?= htmlspecialchars(Auth::user()['avatar'] ?? 'default.png') ?>" class="sidebar-avatar">
            <span><?= htmlspecialchars(Auth::user()['username']) ?>
                <?php if (Auth::isOwner()): ?>
                    <span class="owner-badge">Owner</span>
                <?php elseif (Auth::isAdmin()): ?>
                    <span class="admin-badge">Admin</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <nav class="sidebar-menu">
        <a href="<?= $base ?>/home" <?= ($current_page ?? '') === 'home' ? 'class="active"' : '' ?>>🏠 Home</a>
        <a href="<?= $base ?>/upload" <?= ($current_page ?? '') === 'upload' ? 'class="active"' : '' ?>>⬆️ Upload</a>
        <a href="<?= $base ?>/messages" <?= ($current_page ?? '') === 'messages' ? 'class="active"' : '' ?>>💬 Messages</a>
        <a href="<?= $base ?>/notifications" <?= ($current_page ?? '') === 'notifications' ? 'class="active"' : '' ?>>🔔 Notifications</a>
        <a href="<?= $base ?>/profile" <?= ($current_page ?? '') === 'profile' ? 'class="active"' : '' ?>>👤 Profile</a>

        <?php if (Auth::isAdmin() || Auth::isOwner()): ?>
            <a href="<?= $base ?>/admin" <?= ($current_page ?? '') === 'admin' ? 'class="active"' : '' ?>>🛠 Admin Panel</a>
        <?php endif; ?>

        <a href="<?= $base ?>/logout">🚪 Logout</a>
    </nav>

</aside>
