<h2>Login</h2>

<form method="POST" action="login">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div>
        <label>Email</label><br>
        <input type="email" name="email" required>
    </div>

    <br>

    <div>
        <label>Password</label><br>
        <input type="password" name="password" required>
    </div>

    <br>

    <button type="submit">Login</button>
</form>

<div style="margin-top: 20px; text-align: center;">
    <p>
        Don't have an account?
        <a href="register"
           style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
            Create Account
        </a>
    </p>
</div>

