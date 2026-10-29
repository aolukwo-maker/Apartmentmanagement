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

$tenants = [];
$grouped = [];
$loadError = false;
$success = $_GET['success'] ?? '';

try {
    $result = $conn->query(
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
         WHERE r.role_name = 'Tenant'
         ORDER BY t.created_at DESC, t.tenant_id DESC"
    );
    $tenants = $result->fetch_all(MYSQLI_ASSOC);
    $grouped = groupTenantsByUnit($tenants);
} catch (mysqli_sql_exception $exception) {
    error_log('Tenant management list could not be loaded: ' . $exception->getMessage());
    $loadError = true;
}

$assignedTenants = count(array_filter(
    $tenants,
    static fn (array $tenant): bool => trim((string) ($tenant['unit_id'] ?? '')) !== ''
));
$registeredThisMonth = count(array_filter(
    $tenants,
    static fn (array $tenant): bool => isset($tenant['created_at'])
        && str_starts_with((string) $tenant['created_at'], date('Y-m'))
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management Module</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div>
                <p class="eyebrow">Property operations</p>
                <h1>Tenant Management Module</h1>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($loadError): ?>
            <div class="alert error" role="alert">
                Tenant records could not be loaded from the database. Please try again later.
            </div>
        <?php endif; ?>

        <section class="stats">
            <div class="card">
                <span>Registered tenants</span>
                <strong><?php echo count($tenants); ?></strong>
            </div>
            <div class="card">
                <span>Assigned to a unit</span>
                <strong><?php echo $assignedTenants; ?></strong>
            </div>
            <div class="card">
                <span>Unassigned</span>
                <strong><?php echo count($tenants) - $assignedTenants; ?></strong>
            </div>
            <div class="card">
                <span>Added this month</span>
                <strong><?php echo $registeredThisMonth; ?></strong>
            </div>
        </section>

        <section class="toolbar">
            <a href="history.php" class="btn btn-secondary">View tenant history</a>
        </section>

        <section class="unit-list">
            <?php if (!$loadError && empty($grouped)): ?>
                <div class="empty-state">
                    <h2>No tenants registered yet</h2>
                    <p>Registered tenant accounts from the database will appear here.</p>
                </div>
            <?php elseif (!$loadError): ?>
                <?php foreach ($grouped as $unit => $unitTenants): ?>
                    <div class="unit-card">
                        <div class="unit-header">
                            <h2>Unit <?php echo e($unit); ?></h2>
                            <span><?php echo count($unitTenants); ?> tenant(s)</span>
                        </div>

                        <div class="tenant-grid">
                            <?php foreach ($unitTenants as $tenant): ?>
                                <article class="tenant-card">
                                    <div class="tenant-top">
                                        <h3><?php echo e($tenant['full_name'] ?? 'Unknown tenant'); ?></h3>
                                        <span class="badge <?php echo ($tenant['account_status'] ?? '') === 'Active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo e($tenant['account_status'] ?? 'Unknown'); ?>
                                        </span>
                                    </div>

                                    <dl>
                                        <div>
                                            <dt>Property</dt>
                                            <dd><?php echo e($tenant['property_name'] ?? 'Not assigned'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Phone</dt>
                                            <dd><?php echo e($tenant['phone'] ?? 'N/A'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Email</dt>
                                            <dd><?php echo e($tenant['email'] ?? 'N/A'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>ID number</dt>
                                            <dd><?php echo e($tenant['national_id'] ?? 'N/A'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Emergency contact</dt>
                                            <dd><?php echo e($tenant['emergency_contact'] ?? 'N/A'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Emergency phone</dt>
                                            <dd><?php echo e($tenant['emergency_phone'] ?? 'N/A'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Registered</dt>
                                            <dd><?php echo formatDate(isset($tenant['created_at']) ? substr((string) $tenant['created_at'], 0, 10) : ''); ?></dd>
                                        </div>
                                    </dl>

                                    <div class="tenant-footer">
                                        <a href="tenant.php?id=<?php echo e($tenant['id'] ?? ''); ?>" class="btn btn-link">View details</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
