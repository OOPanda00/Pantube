<?php
$currentUser = Auth::check() ? Auth::user() : null;

$avatar = 'default.png';
if ($currentUser && !empty($currentUser['avatar'])) {
    $avatarPath = __DIR__ . '/../../../public/uploads/avatars/' . $currentUser['avatar'];
    if (file_exists($avatarPath)) {
        $avatar = $currentUser['avatar'];
    }
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base = $base === '' ? '' : $base;
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Pantube</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= $base ?>/">

    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

    <script>
        window.PANTUBE_BASE = "<?= $base ?>";
    </script>
</head>

<body>

<a href="#main-content" class="skip-link">Skip to main content</a>

<div class="header">

    <div class="header-left">
        <button id="menuToggle" class="menu-btn">☰</button>

        <a href="<?= $base ?>/home">
            <img src="<?= $base ?>/assets/img/logo.png" class="logo" alt="Pantube">
        </a>
    </div>

    <div class="search-bar">
        <form action="<?= $base ?>/search" method="GET">
            <input type="text" name="q" placeholder="Search">
        </form>
    </div>

    <div class="header-right">

        <a href="https://github.com/OOPanda00" target="_blank" class="github-link">
            <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/github/github-original.svg"
                 alt="GitHub">
        </a>

        <?php if ($currentUser): ?>
            <?php
            $unread = Notification::unread($currentUser['id']);
            $unreadCount = count($unread);
            ?>
            <div class="notifications-wrapper">

                <button class="notifications-btn" id="notificationsToggle">
                    🔔
                    <?php if ($unreadCount > 0): ?>
                        <span class="notifications-badge">
                            <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </button>

                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notifications-header">
                        <h3>الإشعارات</h3>
                        <?php if ($unreadCount > 0): ?>
                            <button class="mark-all-read-btn" id="markAllReadBtn">
                                تعليم الكل كمقروء
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="notifications-list" id="notificationsList">
                        <div class="loading-notifications">جاري تحميل الإشعارات...</div>
                    </div>

                    <div class="notifications-footer">
                        <a href="<?= $base ?>/notifications" class="view-all-link">
                            عرض جميع الإشعارات
                        </a>
                    </div>
                </div>

            </div>
        <?php endif; ?>

        <button id="themeToggle" class="theme-btn">🌙</button>

        <?php if ($currentUser): ?>
            <a href="<?= $base ?>/profile">
                <img src="<?= $base ?>/uploads/avatars/<?= htmlspecialchars($avatar) ?>"
                     class="header-avatar-img">
            </a>
        <?php endif; ?>
    </div>

</div>

<!-- NOTIFICATIONS SLIDE MENU -->
<div class="notifications-overlay" id="notificationsOverlay"></div>
<div class="notifications-slide" id="notificationsSlide">
    <div class="notifications-slide-header">
        <h3 class="notifications-slide-title">Notifications</h3>
        <button class="notifications-slide-close" id="notificationsSlideClose">&times;</button>
    </div>
    <div class="notifications-slide-body" id="notificationsSlideBody">
        <div class="notifications-slide-empty">Loading notifications...</div>
    </div>
</div>

<div class="layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<main id="main-content" class="content" tabindex="-1">

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('notificationsToggle');
    const slide = document.getElementById('notificationsSlide');
    const overlay = document.getElementById('notificationsOverlay');
    const closeBtn = document.getElementById('notificationsSlideClose');
    const body = document.getElementById('notificationsSlideBody');

    if (!toggleBtn || !slide) return;

    // Open slide menu
    toggleBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        slide.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Load notifications
        await loadNotifications();
    });

    // Close slide menu
    const closeSlide = () => {
        slide.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    };

    closeBtn.addEventListener('click', closeSlide);
    overlay.addEventListener('click', closeSlide);

    // Load notifications function
    async function loadNotifications() {
        try {
            const res = await fetch('notifications');
            const data = await res.json();

            if (!Array.isArray(data) || data.length === 0) {
                body.innerHTML = '<div class="notifications-slide-empty">No notifications</div>';
                return;
            }

            let html = '';
            data.forEach(n => {
                html += `
                    <div class="notifications-slide-item ${n.is_read ? 'read' : 'unread'}">
                        <div class="notifications-slide-item-message">${n.message}</div>
                        <div class="notifications-slide-item-time">${n.time_ago}</div>
                    </div>
                `;
            });

            body.innerHTML = html;
        } catch (err) {
            console.error(err);
            body.innerHTML = '<div class="notifications-slide-empty">Error loading notifications</div>';
        }
    }
});
</script>
