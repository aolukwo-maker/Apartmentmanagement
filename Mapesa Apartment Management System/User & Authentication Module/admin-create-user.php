<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['message' => 'Your administrator session has expired. Sign in again and retry.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    echo json_encode(['csrfToken' => $_SESSION['csrf_token']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Only GET and POST requests are allowed.']);
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (
    !is_string($submittedToken)
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    http_response_code(403);
    echo json_encode(['message' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$role = 'Manager';

if (
    $fullName === ''
    || strlen($fullName) > 50
    || strlen($email) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($password) < 6
) {
    http_response_code(422);
    echo json_encode(['message' => 'Enter a name of up to 50 characters, a valid email, and password of at least 6 characters.']);
    exit;
}

$transactionStarted = false;
try {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['message' => 'An account with that email already exists.']);
        exit;
    }

    $roleStmt = $conn->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $roleStmt->bind_param('s', $role);
    $roleStmt->execute();
    $roleRecord = $roleStmt->get_result()->fetch_assoc();
    if (!$roleRecord) {
        http_response_code(422);
        echo json_encode(['message' => 'The selected role is not configured.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $conn->begin_transaction();
    $transactionStarted = true;

    $createUser = $conn->prepare(
        "INSERT INTO users (username, email, password_hash, role_id, status)
         VALUES (?, ?, ?, ?, 'Active')"
    );
    $roleId = (int) $roleRecord['role_id'];
    $createUser->bind_param('sssi', $fullName, $email, $passwordHash, $roleId);
    $createUser->execute();

    $conn->commit();
    $transactionStarted = false;
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    if ($exception->getCode() === 1062) {
        http_response_code(409);
        echo json_encode(['message' => 'An account with that email already exists.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['message' => 'The account could not be created.']);
    exit;
}

echo json_encode(['message' => 'User created successfully.']);