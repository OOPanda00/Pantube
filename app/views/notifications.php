<?php
/**
 * Notifications Page
 */
?>

<div class="notifications-page">

    <div class="notifications-header">
        <h1 class="page-title">🔔 الإشعارات</h1>

        <div class="notifications-actions">
            <?php if (!empty($notifications)): ?>
                <form method="POST" action="notifications_read" class="mark-all-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="notification_id" value="0">
                    <button type="submit" class="mark-all-read-btn">
                        📌 تعليم الكل كمقروء
                    </button>
                </form>
            <?php endif; ?>

            <div class="notifications-stats">
                <span class="total-count"><?= count($notifications) ?> إشعار</span>

                <?php
                $unreadCount = 0;
                foreach ($notifications as $n) {
                    if (empty($n['is_read'])) {
                        $unreadCount++;
                    }
                }
                ?>

                <?php if ($unreadCount > 0): ?>
                    <span class="unread-count">(<?= $unreadCount ?> غير مقروء)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="notifications-container">
        <?php if (empty($notifications)): ?>
            <div class="empty-notifications">
                <div class="empty-icon">🔔</div>
                <h3>لا توجد إشعارات</h3>
                <p>سيظهر هنا إشعارات عندما يقوم شخص بالتفاعل مع محتواك</p>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $n): ?>
                    <div
                        class="notification-item <?= empty($n['is_read']) ? 'unread' : 'read' ?>"
                        data-notification-id="<?= (int) $n['id'] ?>"
                        data-notification-type="<?= htmlspecialchars($n['type']) ?>"
                        data-source-user-id="<?= (int) ($n['source_user_id'] ?? 0) ?>"
                    >
                        <div class="notification-avatar">
                            <?php if (!empty($n['source_avatar'])): ?>
                                <img
                                    src="uploads/avatars/<?= htmlspecialchars($n['source_avatar']) ?>"
                                    alt="<?= htmlspecialchars($n['source_username']) ?>"
                                >
                            <?php else: ?>
                                <div class="default-avatar">👤</div>
                            <?php endif; ?>
                        </div>

                        <div class="notification-content">
                            <div class="notification-message">
                                <?= htmlspecialchars($n['message']) ?>
                            </div>

                            <div class="notification-meta">
                                <span class="notification-time">
                                    <?= htmlspecialchars($n['time_ago']) ?>
                                </span>

                                <?php if (!empty($n['video_id'])): ?>
                                    <a href="watch/<?= (int) $n['video_id'] ?>" class="video-link">
                                        👁️ مشاهدة الفيديو
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($n['has_actions']) && $n['type'] === 'friend_request'): ?>
                                <div class="friend-request-actions">
                                    <form method="POST"
                                          action="notifications_handle_friend_request"
                                          class="friend-request-form accept-form">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="sender_id" value="<?= (int) $n['source_user_id'] ?>">
                                        <button type="submit" class="friend-action-btn accept-btn">
                                            ✓ قبول
                                        </button>
                                    </form>

                                    <form method="POST"
                                          action="notifications_handle_friend_request"
                                          class="friend-request-form reject-form">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="sender_id" value="<?= (int) $n['source_user_id'] ?>">
                                        <button type="submit" class="friend-action-btn reject-btn">
                                            ✗ رفض
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="notification-actions">
                            <?php if (empty($n['is_read'])): ?>
                                <form method="POST" action="notifications_read" class="mark-read-form">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="mark-read-btn" title="تعليم كمقروء">
                                        ✓
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="notifications-footer">
                <p>عرض <?= count($notifications) ?> من آخر الإشعارات</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.friend-request-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.friend-action-btn {
    padding: 6px 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
.accept-btn {
    background: #4CAF50;
    color: #fff;
}
.reject-btn {
    background: #f44336;
    color: #fff;
}
.notification-item.unread {
    background: rgba(0,0,0,0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.friend-request-form').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();

            if (!confirm('تأكيد الإجراء؟')) {
                return;
            }

            const formData = new FormData(form);
            const item = form.closest('.notification-item');

            try {
                const res = await fetch('notifications_handle_friend_request', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    item.querySelector('.friend-request-actions')?.remove();
                }
            } catch (err) {
                console.error(err);
            }
        });
    });

    document.querySelectorAll('.mark-read-form').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();

            const formData = new FormData(form);
            const item = form.closest('.notification-item');

            try {
                const res = await fetch('notifications_read', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    form.remove();
                }
            } catch (err) {
                console.error(err);
            }
        });
    });

});
</script>
