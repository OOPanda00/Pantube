k<h2>Register</h2>

<form method="POST" action="register">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <div>
        <label>Username</label><br>
        <input type="text" name="username" required>
    </div>

    <br>

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

    <button type="submit">Create Account</button>
</form>

<div style="margin-top: 20px; text-align: center;">
    <p>
        Already have an account?
        <a href="login"
           style="color: var(--primary); font-weight: 600; text-decoration: none;">
            Login Now
        </a>
    </p>
</div>

