<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfAuthenticated();

$token = $_GET['token'] ?? '';
$errors = [];
$success = null;

if ($token === '') {
    $errors[] = 'Missing reset token.';
} else {
    $stmt = $conn->prepare('SELECT user_id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $errors[] = 'This reset link is invalid or has expired.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?');
            $update->bind_param('si', $hash, $user['user_id']);
            if ($update->execute()) {
                $success = 'Your password has been reset successfully.';
            } else {
                $errors[] = 'Failed to update password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card auth-card">
            <h1>Set New Password</h1>

            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <div class="auth-links">
                    <p><a href="login.php">Login now</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reset_password.php?token=<?= urlencode($token) ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button class="btn" type="submit">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
