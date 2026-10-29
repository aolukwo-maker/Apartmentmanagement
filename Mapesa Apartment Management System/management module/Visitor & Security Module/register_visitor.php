<?php
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitor = normalizeVisitor($_POST);
    $visitor['history'] = [];
    $visitor = addHistoryEvent($visitor, 'Visitor registered by front desk.');

    $visitors = loadVisitors();
    $visitors[] = $visitor;
    saveVisitors($visitors);

    header('Location: index.php?success=' . urlencode('Visitor registered successfully.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Visitor</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Front desk</p>
                <h1>Register visitor</h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to dashboard</a>
        </header>

        <section class="form-card">
            <form method="POST" class="inline-form">
                <div class="grid two-col">
                    <label>
                        <span>Full name</span>
                        <input type="text" name="full_name" required>
                    </label>
                    <label>
                        <span>Phone number</span>
                        <input type="tel" name="phone" required>
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
                        <span>Tenant name</span>
                        <input type="text" name="tenant_name" required>
                    </label>
                    <label>
                        <span>Unit</span>
                        <input type="text" name="unit_id" required>
                    </label>
                    <label>
                        <span>Purpose of visit</span>
                        <input type="text" name="visit_purpose" required>
                    </label>
                    <label>
                        <span>Visitor type</span>
                        <select name="visitor_type">
                            <option value="guest">Guest</option>
                            <option value="family">Family</option>
                            <option value="delivery">Delivery</option>
                            <option value="contractor">Contractor</option>
                            <option value="service">Service</option>
                        </select>
                    </label>
                    <label>
                        <span>Scheduled date</span>
                        <input type="date" name="scheduled_date" required>
                    </label>
                    <label>
                        <span>Check-in status</span>
                        <select name="status">
                            <option value="expected">Expected</option>
                            <option value="checked_in">Checked in</option>
                        </select>
                    </label>
                    <label>
                        <span>Security note</span>
                        <textarea name="security_note"></textarea>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Save visitor</button>
            </form>
        </section>
    </div>
</body>
</html>
