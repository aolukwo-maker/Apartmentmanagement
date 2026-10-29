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

$tenantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tenantId) {
    http_response_code(404);
    echo '<h1>Tenant not found</h1>';
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT t.tenant_id AS id,
                COALESCE(NULLIF(t.full_name, ''), u.username) AS full_name,
                COALESCE(NULLIF(t.email, ''), u.email) AS email,
                t.phone,
                t.national_id,
                t.emergency_contact,
                t.emergency_phone,
                unit.unit_number AS unit_id,
                property.property_name,
                u.status AS account_status,
                tenancy.monthly_rent,
                tenancy.start_date,
                tenancy.end_date,
                t.created_at
         FROM tenants t
         INNER JOIN users u ON u.user_id = t.user_id
         INNER JOIN roles r ON r.role_id = u.role_id
         LEFT JOIN (
             SELECT tenant_id, MAX(tenancy_id) AS tenancy_id
             FROM tenancies
             WHERE status = 'Active'
             GROUP BY tenant_id
         ) current_tenancy ON current_tenancy.tenant_id = t.tenant_id
         LEFT JOIN tenancies tenancy ON tenancy.tenancy_id = current_tenancy.tenancy_id
         LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
         LEFT JOIN properties property ON property.property_id = t.property_id
         WHERE t.tenant_id = ? AND r.role_name = 'Tenant'
         LIMIT 1"
    );
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
} catch (mysqli_sql_exception $exception) {
    error_log('Tenant record could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    echo '<h1>Tenant record could not be loaded</h1>';
    exit;
}

if (!$tenant) {
    http_response_code(404);
    echo '<h1>Tenant not found</h1>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Details</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Resident profile</p>
                <h1><?php echo e($tenant['full_name'] ?? 'Tenant'); ?></h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to tenants</a>
        </header>

        <section class="detail-card">
            <h2>Database profile</h2>
            <dl class="detail-list">
                <div><dt>Property</dt><dd><?php echo e($tenant['property_name'] ?? 'Not assigned'); ?></dd></div>
                <div><dt>Unit</dt><dd><?php echo e($tenant['unit_id'] ?: 'Unassigned'); ?></dd></div>
                <div><dt>Phone</dt><dd><?php echo e($tenant['phone'] ?? 'N/A'); ?></dd></div>
                <div><dt>Email</dt><dd><?php echo e($tenant['email'] ?? 'N/A'); ?></dd></div>
                <div><dt>ID number</dt><dd><?php echo e($tenant['national_id'] ?? 'N/A'); ?></dd></div>
                <div><dt>Emergency contact</dt><dd><?php echo e($tenant['emergency_contact'] ?? 'N/A'); ?></dd></div>
                <div><dt>Emergency phone</dt><dd><?php echo e($tenant['emergency_phone'] ?? 'N/A'); ?></dd></div>
                <div><dt>Account status</dt><dd><?php echo e($tenant['account_status'] ?? 'Unknown'); ?></dd></div>
                <?php if ($tenant['monthly_rent'] !== null): ?>
                    <div><dt>Monthly rent</dt><dd>KES <?php echo number_format((float) $tenant['monthly_rent'], 2); ?></dd></div>
                    <div><dt>Lease start</dt><dd><?php echo formatDate($tenant['start_date'] ?? ''); ?></dd></div>
                    <div><dt>Lease end</dt><dd><?php echo formatDate($tenant['end_date'] ?? ''); ?></dd></div>
                <?php endif; ?>
                <div><dt>Registered</dt><dd><?php echo formatDate(isset($tenant['created_at']) ? substr((string) $tenant['created_at'], 0, 10) : ''); ?></dd></div>
            </dl>
        </section>
    </div>
</body>
</html>
