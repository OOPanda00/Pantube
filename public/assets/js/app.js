// =========================
// PANTUBE CORE JS - PREMIUM EDITION
// Enhanced with micro-interactions, accessibility, and performance
// =========================

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const base = window.PANTUBE_BASE || '';
    let notificationsCheckInterval = null;
    let scrollTimeout = null;

    // Add scroll class to header for shadow effect
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const header = document.querySelector('.header');
            if (header) {
                if (window.scrollY > 0) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }
        }, 10);
    }, { passive: true });

    // =========================
    // THEME MANAGEMENT
    // =========================
    const initTheme = () => {
        const themeToggle = document.getElementById('themeToggle');
        if (!themeToggle) return;

        const savedTheme = localStorage.getItem('pantube-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = savedTheme ? savedTheme === 'dark' : prefersDark;

        body.classList.toggle('theme-dark', isDark);
        themeToggle.textContent = isDark ? '☀️' : '🌙';

        themeToggle.addEventListener('click', (e) => {
            e.preventDefault();
            const dark = body.classList.toggle('theme-dark');
            localStorage.setItem('pantube-theme', dark ? 'dark' : 'light');
            themeToggle.textContent = dark ? '☀️' : '🌙';
        });
    };

    // =========================
    // SIDEBAR MOBILE TOGGLE
    // =========================
    const initSidebar = () => {
        const menuToggle = document.getElementById('menuToggle');
        if (!menuToggle) return;

        const sidebar = document.querySelector('.sidebar');
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';

        menuToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });

        // Close sidebar on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
                const menuToggle = document.getElementById('menuToggle');
                if (menuToggle) menuToggle.focus();
            }
        });

        document.body.appendChild(overlay);

        // Close sidebar when clicking links (mobile)
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            });
        });
    };

    // =========================
    // NOTIFICATIONS SYSTEM
    // =========================
    const initNotifications = () => {
        const toggleBtn = document.getElementById('notificationsToggle');
        const dropdown = document.getElementById('notificationsDropdown');
        const list = document.getElementById('notificationsList');
        const markAllBtn = document.getElementById('markAllReadBtn');

        if (!toggleBtn || !dropdown || !list) return;

        // Toggle dropdown
        toggleBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('show');
            
            if (!dropdown.classList.contains('show')) return;
            
            await loadNotifications();
            startNotificationsPolling();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (dropdown && !dropdown.contains(e.target) && !toggleBtn.contains(e.target)) {
                dropdown.classList.remove('show');
                stopNotificationsPolling();
            }
        });

        // Mark all as read
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
                try {
                    await fetch(`${base}/notifications_read`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'notification_id=0'
                    });
                    
                    // Update badge
                    document.querySelectorAll('.notifications-badge').forEach(badge => badge.remove());
                    
                    // Reload notifications
                    await loadNotifications();
                } catch (err) {
                    console.error('Failed to mark all as read:', err);
                }
            });
        }

        // Load notifications initially
        loadNotifications();
        startNotificationsPolling();
    };

    const loadNotifications = async () => {
        const list = document.getElementById('notificationsList');
        if (!list) return;

        try {
            const res = await fetch(`${base}/notifications?ajax=1`);
            const data = await res.json();

            if (!Array.isArray(data) || data.length === 0) {
                list.innerHTML = '<div class="no-notifications">لا توجد إشعارات</div>';
                return;
            }

            let html = '';
            data.forEach(notification => {
                const timeAgo = notification.time_ago || getTimeAgo(notification.created_at);
                html += `
                    <div class="notification-item ${notification.is_read ? 'read' : 'unread'}" 
                         data-notification-id="${notification.id}">
                        <div class="notification-avatar">
                            ${notification.source_avatar ? 
                                `<img src="${base}/uploads/avatars/${escapeHtml(notification.source_avatar)}" alt="${escapeHtml(notification.source_username)}">` :
                                '<div class="default-avatar">👤</div>'
                            }
                        </div>
                        <div class="notification-content">
                            <div class="notification-message">${escapeHtml(notification.message)}</div>
                            <div class="notification-meta">
                                <span class="notification-time">${timeAgo}</span>
                                ${notification.video_id ? 
                                    `<a href="${base}/watch/${notification.video_id}" class="video-link">👁️ مشاهدة الفيديو</a>` : 
                                    ''
                                }
                            </div>
                            ${notification.has_actions && notification.type === 'friend_request' ? `
                                <div class="friend-request-actions">
                                    <form method="POST" action="${base}/notifications_handle_friend_request" class="friend-request-form accept-form">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="sender_id" value="${notification.source_user_id}">
                                        <button type="submit" class="friend-action-btn accept-btn">✓ قبول</button>
                                    </form>
                                    <form method="POST" action="${base}/notifications_handle_friend_request" class="friend-request-form reject-form">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="sender_id" value="${notification.source_user_id}">
                                        <button type="submit" class="friend-action-btn reject-btn">✗ رفض</button>
                                    </form>
                                </div>
                            ` : ''}
                        </div>
                        ${!notification.is_read ? `
                            <div class="notification-actions">
                                <form method="POST" action="${base}/notifications_read" class="mark-read-form">
                                    <input type="hidden" name="notification_id" value="${notification.id}">
                                    <button type="submit" class="mark-read-btn" title="تعليم كمقروء">✓</button>
                                </form>
                            </div>
                        ` : ''}
                    </div>
                `;
            });

            list.innerHTML = html;
            attachNotificationEvents();
        } catch (err) {
            console.error('Failed to load notifications:', err);
            list.innerHTML = '<div class="no-notifications">خطأ في تحميل الإشعارات</div>';
        }
    };

    const attachNotificationEvents = () => {
        // Mark as read
        document.querySelectorAll('.mark-read-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const item = form.closest('.notification-item');

                try {
                    await fetch(`${base}/notifications_read`, {
                        method: 'POST',
                        body: formData
                    });

                    item.classList.remove('unread');
                    item.classList.add('read');
                    form.remove();
                    updateNotificationBadge();
                } catch (err) {
                    console.error('Failed to mark as read:', err);
                }
            });
        });

        // Friend request actions
        document.querySelectorAll('.friend-request-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                if (!confirm('تأكيد الإجراء؟')) return;

                const formData = new FormData(form);
                const item = form.closest('.notification-item');

                try {
                    const res = await fetch(`${base}/notifications_handle_friend_request`, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        item.querySelector('.friend-request-actions')?.remove();
                        updateNotificationBadge();
                    }
                } catch (err) {
                    console.error('Failed to handle friend request:', err);
                }
            });
        });
    };

    const updateNotificationBadge = async () => {
        try {
            const res = await fetch(`${base}/notifications_check?last_check=0`);
            const data = await res.json();
            
            const badge = document.querySelector('.notifications-badge');
            if (data.new_count > 0) {
                if (!badge) {
                    const btn = document.querySelector('.notifications-btn');
                    if (btn) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notifications-badge';
                        newBadge.textContent = data.new_count > 9 ? '9+' : data.new_count;
                        btn.appendChild(newBadge);
                    }
                } else {
                    badge.textContent = data.new_count > 9 ? '9+' : data.new_count;
                }
            } else if (badge) {
                badge.remove();
            }
        } catch (err) {
            // Silent fail
        }
    };

    const startNotificationsPolling = () => {
        stopNotificationsPolling();
        notificationsCheckInterval = setInterval(updateNotificationBadge, 30000); // 30 seconds
    };

    const stopNotificationsPolling = () => {
        if (notificationsCheckInterval) {
            clearInterval(notificationsCheckInterval);
            notificationsCheckInterval = null;
        }
    };

    // =========================
    // LIKE/DISLIKE SYSTEM (ENHANCED)
    // =========================
    const initLikeSystem = () => {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.like-btn');
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();

            const container = btn.closest('.video-actions');
            if (!container) return;

            const videoId = container.dataset.videoId;
            const csrf = container.dataset.csrf;
            const type = btn.dataset.type;

            if (!videoId || !csrf || !type) return;

            btn.disabled = true;
            const originalHTML = btn.innerHTML;
            
            // Add loading state
            btn.innerHTML = '⏳';

            try {
                const res = await fetch(`${base}/like`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        video_id: videoId,
                        type: type,
                        _csrf: csrf
                    })
                });

                const data = await res.json();
                
                if (data.success) {
                    // Update counts - find elements within this specific video
                    const likeCount = container.querySelector('#likeCount');
                    const dislikeCount = container.querySelector('#dislikeCount');
                    
                    if (likeCount) {
                        likeCount.textContent = data.likes || 0;
                        likeCount.style.animation = 'none';
                        setTimeout(() => { likeCount.style.animation = 'scaleIn 0.3s ease-out'; }, 10);
                    }
                    if (dislikeCount) {
                        dislikeCount.textContent = data.dislikes || 0;
                        dislikeCount.style.animation = 'none';
                        setTimeout(() => { dislikeCount.style.animation = 'scaleIn 0.3s ease-out'; }, 10);
                    }

                    // Update button states in this container only
                    container.querySelectorAll('.like-btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    
                    if (data.userReaction) {
                        const activeBtn = container.querySelector(`.like-btn[data-type="${data.userReaction}"]`);
                        if (activeBtn) activeBtn.classList.add('active');
                    }

                    // Update like ratio
                    const total = (data.likes || 0) + (data.dislikes || 0);
                    if (total > 0) {
                        const percentage = Math.round(((data.likes || 0) / total) * 100);
                        const fill = document.getElementById('likeRatioFill');
                        const text = document.getElementById('likePercentage');
                        
                        if (fill) {
                            fill.style.width = `${percentage}%`;
                            fill.style.animation = 'none';
                            setTimeout(() => { fill.style.animation = 'slideIn 0.3s ease-out'; }, 10);
                        }
                        if (text) text.textContent = `${percentage}%`;
                    }
                }
            } catch (err) {
                console.error('Like action failed:', err);
                // Show error feedback
                btn.innerHTML = '❌';
                setTimeout(() => { btn.innerHTML = originalHTML; }, 2000);
            } finally {
                btn.disabled = false;
                if (btn.innerHTML === '⏳') btn.innerHTML = originalHTML;
            }
        });
    };

    // =========================
    // COMMENTS SYSTEM
    // =========================
    const initCommentsSystem = () => {
        // Toggle comments section (show/hide)
        const toggleBtn = document.getElementById('toggleCommentsBtn');
        const commentsSection = document.querySelector('.comments-section');
        const commentsContainer = document.getElementById('commentsContainer');
        if (toggleBtn && commentsSection && commentsContainer) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const collapsed = commentsSection.classList.toggle('collapsed');
                const iconEl = toggleBtn.querySelector('.icon');
                const textEl = toggleBtn.querySelector('.text');
                if (iconEl) iconEl.textContent = collapsed ? '▶' : '▼';
                if (textEl) textEl.textContent = collapsed ? 'Show Comments' : 'Hide Comments';
                toggleBtn.setAttribute('aria-expanded', !collapsed);
            });
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        // Reply buttons
        document.addEventListener('click', (e) => {
            const replyBtn = e.target.closest('.reply-btn');
            if (replyBtn) {
                e.preventDefault();
                const commentId = replyBtn.dataset.commentId;
                const replyForm = document.getElementById(`replyForm-${commentId}`);
                
                if (replyForm) {
                    replyForm.style.display = 'block';
                    const textarea = replyForm.querySelector('textarea');
                    if (textarea) textarea.focus();
                    
                    // Update main form for reply
                    const mainForm = document.getElementById('commentForm');
                    if (mainForm) {
                        const parentInput = mainForm.querySelector('#parentIdInput');
                        if (parentInput) parentInput.value = commentId;
                        const ta = mainForm.querySelector('textarea');
                        if (ta) ta.placeholder = 'Write a reply...';
                        const cancelReplyBtn = document.getElementById('cancelReplyBtn');
                        if (cancelReplyBtn) cancelReplyBtn.style.display = 'block';
                    }
                }
            }

            // Cancel reply
            const cancelBtn = e.target.closest('.cancel-reply-btn');
            if (cancelBtn) {
                e.preventDefault();
                const form = cancelBtn.closest('.reply-form-container');
                if (form) form.style.display = 'none';
                
                // Reset main form
                const mainForm = document.getElementById('commentForm');
                if (mainForm) {
                    const parentInput = mainForm.querySelector('#parentIdInput');
                    if (parentInput) parentInput.value = '';
                    const ta = mainForm.querySelector('textarea');
                    if (ta) ta.placeholder = 'Add a comment...';
                    const cancelReplyBtn = document.getElementById('cancelReplyBtn');
                    if (cancelReplyBtn) cancelReplyBtn.style.display = 'none';
                }
            }

            // Delete comment (only if it's the comment delete btn, not video delete)
            const deleteBtn = e.target.closest('.comment-actions .delete-btn, [data-comment-id] .delete-btn');
            if (deleteBtn && deleteBtn.dataset.commentId) {
                e.preventDefault();
                if (!confirm('Delete this comment?')) return;
                deleteComment(deleteBtn.dataset.commentId);
            }
        });

        // Cancel reply in main form
        const cancelReplyBtn = document.getElementById('cancelReplyBtn');
        if (cancelReplyBtn) {
            cancelReplyBtn.addEventListener('click', () => {
                const mainForm = document.getElementById('commentForm');
                if (mainForm) {
                    const parentInput = mainForm.querySelector('#parentIdInput');
                    if (parentInput) parentInput.value = '';
                    const ta = mainForm.querySelector('textarea');
                    if (ta) ta.placeholder = 'Add a comment...';
                    cancelReplyBtn.style.display = 'none';
                }
                document.querySelectorAll('.reply-form-container').forEach(form => {
                    form.style.display = 'none';
                });
            });
        }
    };

    const deleteComment = async (commentId) => {
        const csrf = document.querySelector('input[name="_csrf"]')?.value;
        if (!csrf) return;

        try {
            const res = await fetch(`${base}/comment_delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    comment_id: commentId,
                    _csrf: csrf
                })
            });

            const data = await res.json();
            if (data.success) {
                const commentEl = document.querySelector(`[data-comment-id="${commentId}"]`);
                if (commentEl) {
                    commentEl.remove();
                    
                    // Update comments count
                    const countEl = document.getElementById('commentsCount');
                    if (countEl) {
                        const current = parseInt(countEl.textContent) || 0;
                        countEl.textContent = Math.max(0, current - 1);
                    }
                    
                    // Show no comments message if needed
                    if (document.querySelectorAll('.comment').length === 0) {
                        document.querySelector('.comments-list').innerHTML = 
                            '<div class="no-comments" id="noComments">No comments yet. Be the first to comment!</div>';
                    }
                }
            }
        } catch (err) {
            console.error('Failed to delete comment:', err);
            alert('Failed to delete comment. Please try again.');
        }
    };

    // =========================
    // MESSAGES SYSTEM
    // =========================
    const initMessagesSystem = () => {
        // User search
        const searchInput = document.getElementById('searchUser');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                const query = searchInput.value.trim();
                
                if (query.length < 2) {
                    hideSearchResults();
                    return;
                }
                
                searchTimeout = setTimeout(() => searchUsers(query), 300);
            });
            
            // Hide results when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.messages-search')) {
                    hideSearchResults();
                }
            });
        }

        // Message sending
        const sendBtn = document.querySelector('.send-message-btn');
        const messageInput = document.querySelector('.message-input-area textarea');
        
        if (sendBtn && messageInput) {
            sendBtn.addEventListener('click', sendMessage);
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
    };

    const searchUsers = async (query) => {
        const results = document.getElementById('searchResults');
        if (!results) return;

        try {
            const res = await fetch(`${base}/search_users?q=${encodeURIComponent(query)}`);
            const data = await res.json();

            if (!Array.isArray(data) || data.length === 0) {
                results.innerHTML = '<div class="search-result-item">No users found</div>';
            } else {
                let html = '';
                data.forEach(user => {
                    html += `
                        <a href="${base}/messages/start?user_id=${user.id}" class="search-result-item">
                            <img src="${base}/uploads/avatars/${user.avatar || 'default.png'}" 
                                 alt="${escapeHtml(user.username)}" 
                                 class="search-result-avatar">
                            <span>${escapeHtml(user.username)}</span>
                        </a>
                    `;
                });
                results.innerHTML = html;
            }
            
            results.classList.add('show');
        } catch (err) {
            console.error('Search failed:', err);
        }
    };

    const hideSearchResults = () => {
        const results = document.getElementById('searchResults');
        if (results) results.classList.remove('show');
    };

    const sendMessage = async () => {
        const input = document.querySelector('.message-input-area textarea');
        const btn = document.querySelector('.send-message-btn');
        const csrf = document.querySelector('input[name="_csrf"]')?.value;
        const receiverId = new URLSearchParams(window.location.search).get('user_id');

        if (!input || !btn || !csrf || !receiverId) return;

        const message = input.value.trim();
        if (!message) return;

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Sending...';

        try {
            const res = await fetch(`${base}/chat_send`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    receiver_id: receiverId,
                    message: message,
                    _csrf: csrf
                })
            });

            const data = await res.json();
            if (data.success) {
                input.value = '';
                input.focus();
                btn.textContent = 'Sent!';
                btn.disabled = false;
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 1500);
                return;
            }
        } catch (err) {
            console.error('Failed to send message:', err);
            alert('Failed to send message. Please try again.');
        }
        btn.disabled = false;
        btn.textContent = originalText;
    };

    // =========================
    // PROFILE ACTIONS
    // =========================
    const initProfileActions = () => {
        // Follow/Unfollow
        document.addEventListener('submit', async (e) => {
            const form = e.target;
            if (!form.classList.contains('profile-action-form')) return;
            
            e.preventDefault();
            const action = form.querySelector('input[name="action"]')?.value;
            const userId = form.querySelector('input[name="user_id"]')?.value;
            const csrf = form.querySelector('input[name="_csrf"]')?.value;
            
            if (!action || !userId || !csrf) return;

            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(`${base}/profile_update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: action,
                        user_id: userId,
                        _csrf: csrf
                    })
                });

                if (res.ok) {
                    window.location.reload();
                }
            } catch (err) {
                console.error('Profile action failed:', err);
                alert('Action failed. Please try again.');
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    };

    // =========================
    // UTILITY FUNCTIONS
    // =========================
    const getTimeAgo = (dateString) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);

        if (diffSec < 60) return 'الآن';
        if (diffMin < 60) return `منذ ${diffMin} دقيقة`;
        if (diffHour < 24) return `منذ ${diffHour} ساعة`;
        if (diffDay < 30) return `منذ ${diffDay} يوم`;
        return date.toLocaleDateString('ar-EG');
    };

    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    };

    // =========================
    // DELETE VIDEO HANDLER
    // =========================
    window.deleteVideo = async (event, videoId) => {
        event.preventDefault();
        event.stopPropagation();
        
        if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
            return;
        }
        
        try {
            const deleteUrl = `${base}/delete?id=${videoId}`;
            console.log('[DELETE_VIDEO] Sending delete request to:', deleteUrl);
            
            const response = await fetch(deleteUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            console.log('[DELETE_VIDEO] Response status:', response.status);
            
            if (response.ok || response.status === 302 || response.status === 301) {
                console.log('[DELETE_VIDEO] Deletion successful');
                alert('Video deleted successfully');
                window.location.href = `${base}/home?deleted=1`;
            } else {
                console.error('[DELETE_VIDEO] Failed with status:', response.status);
                alert('Failed to delete video. Please try again.');
            }
        } catch (error) {
            console.error('[DELETE_VIDEO] Error:', error);
            alert('Error deleting video: ' + error.message);
        }
    };

    // =========================
    // INITIALIZE EVERYTHING
    // =========================
    initTheme();
    initSidebar();
    initNotifications();
    initLikeSystem();
    initCommentsSystem();
    initMessagesSystem();
    initProfileActions();

    // =========================
    // WINDOW EVENT HANDLERS
    // =========================
    window.addEventListener('beforeunload', stopNotificationsPolling);
});
