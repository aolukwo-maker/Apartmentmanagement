<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$isTenant = $user['role'] === 'Tenant';
if ($isTenant) {
    $tenantStmt = $conn->prepare('SELECT phone FROM tenants WHERE user_id = ? LIMIT 1');
    $tenantStmt->bind_param('i', $user['id']);
    $tenantStmt->execute();
    $tenant = $tenantStmt->get_result()->fetch_assoc();
    $user['phone'] = $tenant['phone'] ?? '';
}

$success = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (strlen($fullName) > 50) {
            $errors[] = 'Full name cannot exceed 50 characters.';
        }

        if (empty($errors)) {
            $transactionStarted = false;
            try {
                $conn->begin_transaction();
                $transactionStarted = true;

                $stmt = $conn->prepare('UPDATE users SET username = ? WHERE user_id = ?');
                $stmt->bind_param('si', $fullName, $user['id']);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Unable to update the user account.');
                }

                if ($isTenant) {
                    $tenantUpdate = $conn->prepare(
                        'INSERT INTO tenants (user_id, full_name, email, phone)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            full_name = VALUES(full_name),
                            email = VALUES(email),
                            phone = VALUES(phone)'
                    );
                    $tenantUpdate->bind_param('isss', $user['id'], $fullName, $user['email'], $phone);
                    if (!$tenantUpdate->execute()) {
                        throw new RuntimeException('Unable to update the tenant profile.');
                    }
                }

                $conn->commit();
                $transactionStarted = false;
                $success = 'Profile updated successfully.';
                $user['full_name'] = $fullName;
                $user['phone'] = $phone;
            } catch (mysqli_sql_exception | RuntimeException $exception) {
                if ($transactionStarted) {
                    $conn->rollback();
                }
                $errors[] = 'Unable to update profile.';
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
    <title>Profile</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <nav class="topbar">
            <div class="topbar-links">
                <a href="<?= ($_SESSION['role'] ?? '') === 'Manager' ? '/Mapesa%20Apartment%20Management%20System/management%20module/index.html' : 'dashboard.php' ?>">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <div class="card dashboard-card">
            <h1>Profile Management</h1>

            <?php if ($success): ?>
                <div class="alert success"><?= htmlspecialchars($success) ?></div>
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

            <div class="profile-grid">
                <div class="profile-item">
                    <strong>Role</strong>
                    <p><?= htmlspecialchars($user['role']) ?></p>
                </div>
                <div class="profile-item">
                    <strong>Email</strong>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <?php if ($isTenant): ?>
                    <div class="profile-item">
                        <strong>Phone</strong>
                        <p><?= htmlspecialchars(($user['phone'] ?? '') ?: 'Not provided') ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="profile.php" style="margin-top: 25px;">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" maxlength="50" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <?php if ($isTenant): ?>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" maxlength="20" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <button class="btn" type="submit">Update Profile</button>
            </form>
        </div>
    </div>
</body>
</html>
