<?php
declare(strict_types=1);

require_once __DIR__ . '/../../User & Authentication Module/config.php';
require __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../../User%20%26%20Authentication%20Module/login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Manager', 'Admin'], true)) {
    header('Location: ../../User%20%26%20Authentication%20Module/access_denied.php');
    exit;
}

$history = [];
$loadError = false;

try {
    $result = $conn->query(
        "SELECT COALESCE(NULLIF(t.full_name, ''), u.username) AS tenant,
                COALESCE(unit.unit_number, 'Unassigned') AS unit,
                r.request_type,
                r.subject,
                r.message,
                r.urgency,
                r.status,
                r.created_at
         FROM tenant_requests r
         INNER JOIN users u ON u.user_id = r.user_id
         INNER JOIN roles role ON role.role_id = u.role_id
         LEFT JOIN tenants t ON t.user_id = u.user_id
         LEFT JOIN (
             SELECT tenant_id, MAX(tenancy_id) AS tenancy_id
             FROM tenancies
             WHERE status = 'Active'
             GROUP BY tenant_id
         ) current_tenancy ON current_tenancy.tenant_id = t.tenant_id
         LEFT JOIN tenancies tenancy ON tenancy.tenancy_id = current_tenancy.tenancy_id
         LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
         WHERE role.role_name = 'Tenant'
         ORDER BY r.created_at DESC, r.request_id DESC"
    );
    $history = $result->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
    error_log('Tenant activity could not be loaded: ' . $exception->getMessage());
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant History</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Database activity</p>
                <h1>Tenant requests</h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to tenants</a>
        </header>

        <section class="detail-card">
            <?php if ($loadError): ?>
                <div class="alert error" role="alert">
                    Tenant requests could not be loaded from the database. Please try again later.
                </div>
            <?php elseif (empty($history)): ?>
                <p>No tenant requests are recorded in the database.</p>
            <?php else: ?>
                <ul class="history-list">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <strong><?php echo e($entry['tenant']); ?></strong>
                            <span>(Unit <?php echo e($entry['unit']); ?>)</span>
                            <small><?php echo e($entry['created_at']); ?> · <?php echo e($entry['request_type']); ?> · <?php echo e($entry['status']); ?></small>
                            <p><?php echo e($entry['subject']); ?> — <?php echo e($entry['message']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
