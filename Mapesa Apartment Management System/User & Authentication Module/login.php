<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfAuthenticated();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare(
            'SELECT u.*, u.user_id AS id, u.username AS full_name, r.role_name AS role
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
             WHERE u.email = ? AND u.status = "Active" LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] === 'Tenant') {
                try {
                    syncTenantRecord([
                        'id' => (int) $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'] ?? '',
                        'unit_number' => $user['unit_number'] ?? '',
                        'address' => $user['address'] ?? ''
                    ]);
                } catch (mysqli_sql_exception | RuntimeException $exception) {
                    $errors[] = 'Your tenant profile could not be loaded. Please try again.';
                }
            }

            if (empty($errors)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                redirectToRoleHome();
            }
        }

        if (empty($errors)) {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#14263e">
    <title>Sign in | Mapesa Resident Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-brand-panel" aria-label="Mapesa resident portal">
            <a class="login-brand" href="index.php" aria-label="Mapesa home">
                <span class="login-brand-mark">M</span>
                <span class="login-brand-name">MAPESA<small>RESIDENT PORTAL</small></span>
            </a>
            <div class="login-brand-copy">
                <p class="login-kicker">YOUR HOME, IN GOOD HANDS</p>
                <h2>Welcome back<br>to your place.</h2>
                <p>Sign in to stay connected with your home and property team.</p>
            </div>
            <div class="login-brand-footer">
                <span class="brand-rule"></span>
                <span>Resident services, made simple.</span>
            </div>
        </section>

        <section class="login-form-panel" aria-labelledby="login-title">
            <div class="login-form-wrap">
                <p class="login-eyebrow">RESIDENT ACCESS</p>
                <h1 id="login-title">Sign in</h1>
                <p class="login-intro">Enter your account details to continue.</p>

                <?php $flash = getFlash('success'); if ($flash): ?>
                    <div class="alert success"><?= htmlspecialchars($flash) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert error" role="alert">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="login.php">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" autocomplete="username" placeholder="you@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                    </div>

                    <div class="login-forgot-row">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                    <button class="btn login-submit" type="submit">Sign in <span aria-hidden="true">&rarr;</span></button>
                </form>

                <p class="login-secure-note"><span></span> Secure access to your resident account</p>
            </div>
        </section>
    </main>
</body>
</html>
