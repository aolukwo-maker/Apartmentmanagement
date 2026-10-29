<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfAuthenticated();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = $conn->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?');
            $update->bind_param('ssi', $token, $expiry, $user['user_id']);
            $update->execute();

            $modulePath = '/Mapesa Apartment Management System/User & Authentication Module';
            $modulePath = str_replace(' ', '%20', $modulePath);
            $modulePath = str_replace('&', '%26', $modulePath);
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = 'http://' . $host . $modulePath . '/reset_password.php?token=' . urlencode($token);
            $success = 'Password reset link generated. Use this demo link: <a href="' . htmlspecialchars($resetLink) . '" target="_blank">Reset Password</a>';
        } else {
            $errors[] = 'No account found for that email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card auth-card">
            <h1>Reset Password</h1>

            <?php if ($success): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <button class="btn" type="submit">Send Reset Link</button>
            </form>

            <div class="auth-links">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
