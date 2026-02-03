<div class="container mt-5">
    <h1>Admin Dashboard</h1>
    
    <?php if (!Auth::isAdmin() && !Auth::isOwner()): ?>
        <div class="alert alert-danger">
            Access Denied. Admin privileges required.
        </div>
    <?php else: ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <h2>User Management</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $user['role'] === 'admin' ? 'warning' : ($user['role'] === 'owner' ? 'danger' : 'info') ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (Auth::isOwner() && $user['id'] > 1): ?>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <a href="/pantube/public/admin_promote?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Promote</a>
                                        <?php else: ?>
                                            <a href="/pantube/public/admin_demote?id=<?= $user['id'] ?>" class="btn btn-sm btn-secondary">Demote</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
