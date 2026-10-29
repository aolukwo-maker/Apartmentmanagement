<?php
declare(strict_types=1);

require_once __DIR__ . '/../../User & Authentication Module/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    respond(405, ['error' => 'Only GET requests are allowed.']);
}

$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    respond(401, ['error' => 'Sign in to view your manager profile.']);
}

if (($_SESSION['role'] ?? '') !== 'Manager') {
    respond(403, ['error' => 'Manager access is required.']);
}

try {
    $stmt = $conn->prepare(
        "SELECT u.username AS full_name, u.email
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = ? AND r.role_name = 'Manager' AND u.status = 'Active'
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        respond(403, ['error' => 'The manager account is unavailable.']);
    }

    respond(200, [
        'fullName' => $user['full_name'],
        'email' => $user['email'],
        'role' => 'Manager'
    ]);
} catch (mysqli_sql_exception $exception) {
    error_log('Manager profile could not be loaded: ' . $exception->getMessage());
    respond(500, ['error' => 'Your manager profile could not be loaded.']);
}
