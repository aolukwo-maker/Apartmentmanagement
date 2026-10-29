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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Only POST requests are allowed.']);
}

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    respond(403, ['error' => 'Manager or administrator access is required.']);
}

$subject = trim((string) ($_POST['title'] ?? ''));
$location = trim((string) ($_POST['unit'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$priority = trim((string) ($_POST['priority'] ?? ''));
$priorities = ['High', 'Medium', 'Low'];

if ($subject === '' || strlen($subject) > 150
    || $location === '' || strlen($location) > 150
    || $description === '' || strlen($description) > 5000
    || !in_array($priority, $priorities, true)) {
    respond(400, ['error' => 'Enter a valid issue title, unit or location, description, and priority.']);
}

try {
    $unitStmt = $conn->prepare(
        'SELECT unit_id FROM units WHERE unit_number = ? ORDER BY unit_id LIMIT 2'
    );
    $unitStmt->bind_param('s', $location);
    $unitStmt->execute();
    $unitMatches = $unitStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $unitId = null;
    $tenantId = null;
    if (count($unitMatches) === 1) {
        $unitId = (int) $unitMatches[0]['unit_id'];
        $tenantStmt = $conn->prepare(
            "SELECT tenant_id
             FROM tenancies
             WHERE unit_id = ? AND status = 'Active'
             ORDER BY start_date DESC
             LIMIT 1"
        );
        $tenantStmt->bind_param('i', $unitId);
        $tenantStmt->execute();
        $tenant = $tenantStmt->get_result()->fetch_assoc();
        $tenantId = $tenant ? (int) $tenant['tenant_id'] : null;
    } else {
        $description = "Location: {$location}\n\n{$description}";
    }

    $status = 'Open';
    $insert = $conn->prepare(
        'INSERT INTO maintenance_requests (tenant_id, unit_id, subject, description, priority, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param('iissss', $tenantId, $unitId, $subject, $description, $priority, $status);
    $insert->execute();

    respond(201, [
        'message' => 'Maintenance request recorded successfully.',
        'requestId' => (int) $conn->insert_id,
    ]);
} catch (Throwable $exception) {
    error_log('Maintenance request could not be saved: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to save the maintenance request to the database.']);
}
