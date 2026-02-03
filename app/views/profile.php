<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base = $base === '' ? '' : $base;

$isOwnProfile = $is_own_profile ?? false;
$userData = $user ?? [];
$stats = $stats ?? [];
$videos = $videos ?? [];
$relationships = $relationships ?? [];
$avatar = $userData['avatar'] ?? 'default.png';

$flashSuccess = $_SESSION['success'] ?? null;
$flashError = $_SESSION['error'] ?? null;
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);
?>

<div class="profile-page">

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="profile-header">

        <div class="profile-avatar-wrap">
            <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="profile-avatar-img">
        </div>

        <div class="profile-info">
            <h2><?= htmlspecialchars($userData['username']) ?></h2>
            <p class="profile-email"><?= htmlspecialchars($userData['email']) ?></p>
            <?php if ($isOwnProfile): ?>
                <p class="profile-user-id">ID: <?= (int)$userData['id'] ?></p>
            <?php endif; ?>

            <?php if (!empty($userData['bio'])): ?>
                <div class="profile-bio">
                    <?= nl2br(htmlspecialchars($userData['bio'])) ?>
                </div>
            <?php endif; ?>

            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['videos'] ?? 0 ?></span>
                    <span class="stat-label">Videos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['followers'] ?? 0 ?></span>
                    <span class="stat-label">Followers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['following'] ?? 0 ?></span>
                    <span class="stat-label">Following</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['friends'] ?? 0 ?></span>
                    <span class="stat-label">Friends</span>
                </div>
            </div>
        </div>

        <?php if (!$isOwnProfile && empty($relationships['has_blocked_me'])): ?>
            <div class="profile-actions">
                <div class="action-section">

                    <a href="<?= $base ?>/messages/conversation?user_id=<?= (int)$userData['id'] ?>" class="btn btn-primary">
                        💬 Send Message
                    </a>

                    <?php if ($relationships['is_following'] ?? false): ?>
                        <form method="POST" action="profile_update" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="unfollow">
                            <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                            <button type="submit" class="btn btn-secondary">✓ Following</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="profile_update" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="follow">
                            <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                            <button type="submit" class="btn btn-secondary">+ Follow</button>
                        </form>
                    <?php endif; ?>

                    <?php
                        $friendshipStatus = $relationships['friendship_status'] ?? null;
                        if ($friendshipStatus === 'pending_received'):
                    ?>
                        <div class="friend-request-actions">
                            <form method="POST" action="profile_update" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="accept_friend_request">
                                <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                                <button type="submit" class="btn btn-success">✓ Accept Friend Request</button>
                            </form>
                            <form method="POST" action="profile_update" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="reject_friend_request">
                                <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                                <button type="submit" class="btn btn-danger">✗ Decline</button>
                            </form>
                        </div>
                    <?php elseif ($friendshipStatus === 'pending_sent'): ?>
                        <button class="btn btn-secondary" disabled>⏱ Request Sent</button>
                    <?php elseif ($friendshipStatus === 'friends'): ?>
                        <form method="POST" action="profile_update" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="remove_friend">
                            <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                            <button type="submit" class="btn btn-secondary">👥 Friends</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="profile_update" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="send_friend_request">
                            <input type="hidden" name="user_id" value="<?= (int)$userData['id'] ?>">
                            <button type="submit" class="btn btn-secondary">+ Add Friend</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php if ($isOwnProfile): ?>
    <section class="profile-edit" aria-labelledby="profile-edit-heading">
        <h2 id="profile-edit-heading" class="profile-edit-title">Edit profile</h2>

        <div class="profile-edit-grid">

            <!-- Change Avatar -->
            <div class="profile-edit-card">
                <h3 class="profile-edit-card-title">Avatar</h3>
                <div class="profile-edit-avatar-preview">
                    <img src="uploads/avatars/<?= htmlspecialchars($avatar) ?>" alt="Current avatar" id="avatarPreview">
                </div>
                <form method="POST" action="profile_update" enctype="multipart/form-data" class="profile-edit-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update_avatar">
                    <label for="avatarFile" class="profile-edit-label">Choose image (JPG, PNG, WebP)</label>
                    <input type="file" id="avatarFile" name="avatar" accept="image/jpeg,image/png,image/webp" class="profile-edit-file">
                    <button type="submit" class="btn btn-primary profile-edit-submit">Update avatar</button>
                </form>
            </div>

            <!-- Change Username -->
            <div class="profile-edit-card">
                <h3 class="profile-edit-card-title">Display name</h3>
                <form method="POST" action="profile_update" class="profile-edit-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update_username">
                    <label for="usernameInput" class="profile-edit-label">Username (3–30 characters)</label>
                    <input type="text" id="usernameInput" name="username" value="<?= htmlspecialchars($userData['username'] ?? '') ?>" minlength="3" maxlength="30" required class="profile-edit-input">
                    <button type="submit" class="btn btn-primary profile-edit-submit">Save username</button>
                </form>
            </div>

            <!-- Change Bio -->
            <div class="profile-edit-card">
                <h3 class="profile-edit-card-title">Bio</h3>
                <form method="POST" action="profile_update" class="profile-edit-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update_bio">
                    <label for="bioInput" class="profile-edit-label">About you (max 500 characters)</label>
                    <textarea id="bioInput" name="bio" rows="4" maxlength="500" class="profile-edit-textarea" placeholder="Tell others about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                    <span class="profile-edit-hint" id="bioCount">0</span>/500
                    <button type="submit" class="btn btn-primary profile-edit-submit">Save bio</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-edit-card">
                <h3 class="profile-edit-card-title">Password</h3>
                <form method="POST" action="profile_update" class="profile-edit-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update_password">
                    <label for="oldPassword" class="profile-edit-label">Current password</label>
                    <input type="password" id="oldPassword" name="old_password" required class="profile-edit-input" autocomplete="current-password">
                    <label for="newPassword" class="profile-edit-label">New password (min 6 characters)</label>
                    <input type="password" id="newPassword" name="new_password" minlength="6" required class="profile-edit-input" autocomplete="new-password">
                    <label for="confirmPassword" class="profile-edit-label">Confirm new password</label>
                    <input type="password" id="confirmPassword" name="confirm_password" minlength="6" required class="profile-edit-input" autocomplete="new-password">
                    <button type="submit" class="btn btn-primary profile-edit-submit">Change password</button>
                </form>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <div class="profile-videos">
        <h3>Videos</h3>

        <?php if (empty($videos)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎬</div>
                <h3>No videos yet</h3>
                <p>Upload your first video from the Upload page.</p>
            </div>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card">
                        <a href="watch/<?= (int)$video['id'] ?>" class="video-thumb">
                            <?php if ($video['thumbnail']): ?>
                                <img src="uploads/thumbnails/<?= htmlspecialchars($video['thumbnail']) ?>">
                            <?php endif; ?>
                        </a>
                        <div class="video-info">
                            <strong><?= htmlspecialchars($video['title']) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php if ($isOwnProfile): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var bioInput = document.getElementById('bioInput');
    var bioCount = document.getElementById('bioCount');
    if (bioInput && bioCount) {
        function updateCount() { bioCount.textContent = bioInput.value.length; }
        updateCount();
        bioInput.addEventListener('input', updateCount);
    }
    var avatarFile = document.getElementById('avatarFile');
    var avatarPreview = document.getElementById('avatarPreview');
    if (avatarFile && avatarPreview) {
        avatarFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var r = new FileReader();
                r.onload = function(e) { avatarPreview.src = e.target.result; };
                r.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>
<?php endif; ?>
