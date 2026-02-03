<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base = $base === '' ? '' : $base;

$otherUserId = $other_user_id ?? 0;
$otherUser = $other_user ?? [];
$csrf = $csrf ?? '';
$currentUserId = Auth::user()['id'];
?>

<div class="conversation-page">

    <!-- Conversation Header -->
    <div class="conversation-header">
        <a href="<?= $base ?>/messages" class="back-btn">← Back</a>
        
        <div class="conversation-peer">
            <?php if (!empty($otherUser['avatar'])): ?>
                <img src="<?= $base ?>/uploads/avatars/<?= htmlspecialchars($otherUser['avatar']) ?>" 
                     alt="<?= htmlspecialchars($otherUser['username']) ?>"
                     class="peer-avatar">
            <?php else: ?>
                <div class="peer-avatar default-avatar">👤</div>
            <?php endif; ?>
            <div class="peer-info">
                <h2><?= htmlspecialchars($otherUser['username']) ?></h2>
            </div>
        </div>

        <a href="<?= $base ?>/profile?id=<?= (int)$otherUserId ?>" class="view-profile-btn">View Profile</a>
    </div>

    <!-- Messages Container -->
    <div class="messages-content" id="messagesContent">
        <div class="messages-loading">Loading messages...</div>
    </div>

    <!-- Message Input -->
    <div class="message-input-container">
        <form id="messageForm" class="message-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="receiver_id" value="<?= (int)$otherUserId ?>">
            
            <textarea 
                id="messageInput"
                name="message" 
                class="message-textarea"
                placeholder="Type a message..."
                rows="3"
                required></textarea>
            
            <button type="submit" class="send-btn">Send</button>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const base = window.PANTUBE_BASE || '<?= $base ?>';
    const otherUserId = <?= (int)$otherUserId ?>;
    const currentUserId = <?= (int)$currentUserId ?>;
    const csrf = '<?= htmlspecialchars($csrf) ?>';
    
    const messagesContent = document.getElementById('messagesContent');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    let messageCheckInterval = null;

    // Load messages
    const loadMessages = async () => {
        try {
            const response = await fetch(`${base}/chat_fetch?user_id=${otherUserId}`);
            if (!response.ok) throw new Error('Failed to load messages');
            
            const messages = await response.json();
            renderMessages(messages);
            
            // Scroll to bottom
            setTimeout(() => {
                messagesContent.scrollTop = messagesContent.scrollHeight;
            }, 50);
        } catch (err) {
            console.error('Error loading messages:', err);
            messagesContent.innerHTML = '<div class="error-message">Failed to load messages</div>';
        }
    };

    // Render messages
    const renderMessages = (messages) => {
        if (!Array.isArray(messages)) {
            messagesContent.innerHTML = '<div class="empty-messages">No messages yet. Start the conversation!</div>';
            return;
        }

        messagesContent.innerHTML = messages.map(msg => {
            const isOwn = msg.sender_id == currentUserId;
            const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return `
                <div class="message ${isOwn ? 'own-message' : 'peer-message'}">
                    <div class="message-bubble">
                        ${msg.message}
                    </div>
                    <div class="message-time">${time}</div>
                </div>
            `;
        }).join('');
    };

    // Send message
    messageForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const messageText = messageInput.value.trim();
        if (!messageText) return;

        try {
            const formData = new FormData();
            formData.append('_csrf', csrf);
            formData.append('receiver_id', otherUserId);
            formData.append('message', messageText);

            const response = await fetch(`${base}/chat_send`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Failed to send message');

            messageInput.value = '';
            messageInput.focus();
            
            // Reload messages
            await loadMessages();
        } catch (err) {
            console.error('Error sending message:', err);
            alert('Failed to send message');
        }
    });

    // Initial load
    loadMessages();

    // Auto-refresh messages every 2 seconds
    messageCheckInterval = setInterval(loadMessages, 2000);

    // Cleanup on page leave
    window.addEventListener('beforeunload', () => {
        if (messageCheckInterval) clearInterval(messageCheckInterval);
    });
});
</script>
