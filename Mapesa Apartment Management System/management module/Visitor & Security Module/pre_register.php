<?php
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantName = trim((string) ($_POST['tenant_name'] ?? ''));
    $unitId = trim((string) ($_POST['unit_id'] ?? ''));
    $visitorName = trim((string) ($_POST['visitor_name'] ?? ''));
    $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? ''));

    if ($tenantName === '' || $unitId === '' || $visitorName === '') {
        $error = 'Tenant name, unit, and visitor name are required.';
    } else {
        $entry = normalizeVisitor([
            'full_name' => $visitorName,
            'tenant_name' => $tenantName,
            'unit_id' => $unitId,
            'scheduled_date' => $scheduledDate,
            'visit_purpose' => trim((string) ($_POST['visit_purpose'] ?? 'Tenant pre-registration')),
            'status' => 'expected',
            'visitor_type' => trim((string) ($_POST['visitor_type'] ?? 'guest')),
            'security_note' => trim((string) ($_POST['security_note'] ?? '')),
            'company' => trim((string) ($_POST['company'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'id_number' => trim((string) ($_POST['id_number'] ?? '')),
            'registered_by' => 'Tenant pre-registration',
        ]);

        $entry = addHistoryEvent($entry, 'Tenant pre-registered visitor for access approval.');

        $visitors = loadVisitors();
        $visitors[] = $entry;
        saveVisitors($visitors);

        header('Location: index.php?success=' . urlencode('Visitor pre-registration saved successfully.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Visitor Pre-registration</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Tenant portal</p>
                <h1>Tenant pre-registration</h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to dashboard</a>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <section class="form-card">
            <form method="POST" class="inline-form">
                <div class="grid two-col">
                    <label>
                        <span>Tenant name</span>
                        <input type="text" name="tenant_name" required>
                    </label>
                    <label>
                        <span>Unit</span>
                        <input type="text" name="unit_id" required>
                    </label>
                    <label>
                        <span>Visitor name</span>
                        <input type="text" name="visitor_name" required>
                    </label>
                    <label>
                        <span>Visitor type</span>
                        <select name="visitor_type">
                            <option value="guest">Guest</option>
                            <option value="family">Family</option>
                            <option value="delivery">Delivery</option>
                            <option value="service">Service</option>
                        </select>
                    </label>
                    <label>
                        <span>Phone number</span>
                        <input type="tel" name="phone">
                    </label>
                    <label>
                        <span>ID number</span>
                        <input type="text" name="id_number">
                    </label>
                    <label>
                        <span>Company / origin</span>
                        <input type="text" name="company">
                    </label>
                    <label>
                        <span>Scheduled date</span>
                        <input type="date" name="scheduled_date">
                    </label>
                    <label>
                        <span>Purpose of visit</span>
                        <input type="text" name="visit_purpose" value="Tenant pre-registration">
                    </label>
                    <label>
                        <span>Security note</span>
                        <textarea name="security_note"></textarea>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Save pre-registration</button>
            </form>
        </section>
    </div>
</body>
</html>
