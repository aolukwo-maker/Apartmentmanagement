<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfAuthenticated();

$errors = [];
$input = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'Tenant',
    'unit_number' => '',
    'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['email'] = trim($_POST['email'] ?? '');
    $input['phone'] = trim($_POST['phone'] ?? '');
    $input['role'] = $_POST['role'] ?? 'Tenant';
    $input['unit_number'] = trim($_POST['unit_number'] ?? '');
    $input['address'] = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($input['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $allowedRoles = ['Admin', 'Manager', 'Tenant', 'Caretaker', 'Security'];
    if (!in_array($input['role'], $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $input['email']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $roleIds = [
                'Admin' => 1,
                'Manager' => 2,
                'Tenant' => 3,
                'Caretaker' => 4,
                'Security' => 5
            ];
            $roleId = $roleIds[$input['role']];
            $username = substr(trim($input['full_name']), 0, 50);
            $stmt = $conn->prepare(
                'INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssi',
                $username,
                $input['email'],
                $passwordHash,
                $roleId
            );

            $transactionStarted = false;
            try {
                $conn->begin_transaction();
                $transactionStarted = true;

                if (!$stmt->execute()) {
                    throw new RuntimeException('User account could not be created.');
                }

                if ($input['role'] === 'Tenant') {
                    syncTenantRecord([
                        'id' => (int) $conn->insert_id,
                        'full_name' => $input['full_name'],
                        'email' => $input['email'],
                        'phone' => $input['phone'],
                        'unit_number' => $input['unit_number'],
                        'address' => $input['address']
                    ]);
                }

                $conn->commit();
                $transactionStarted = false;
                setFlash('success', 'Registration successful. Please log in.');
                header('Location: login.php');
                exit;
            } catch (mysqli_sql_exception | RuntimeException $exception) {
                if ($transactionStarted) {
                    $conn->rollback();
                }
                $errors[] = 'Registration failed. Please try again.';
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
    <title>Register</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card auth-card">
            <h1>Create Account</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($input['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($input['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($input['phone']) ?>">
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="Admin" <?= $input['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Manager" <?= $input['role'] === 'Manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="Tenant" <?= $input['role'] === 'Tenant' ? 'selected' : '' ?>>Tenant</option>
                        <option value="Caretaker" <?= $input['role'] === 'Caretaker' ? 'selected' : '' ?>>Caretaker</option>
                        <option value="Security" <?= $input['role'] === 'Security' ? 'selected' : '' ?>>Security</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="unit_number">Unit Number</label>
                    <input type="text" id="unit_number" name="unit_number" value="<?= htmlspecialchars($input['unit_number']) ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($input['address']) ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button class="btn" type="submit">Register</button>
            </form>

            <div class="auth-links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
