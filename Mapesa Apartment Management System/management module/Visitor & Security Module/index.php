<?php
require __DIR__ . '/includes/functions.php';

$visitors = loadVisitors();
$summary = getVisitorSummary($visitors);
$notifications = getSecurityNotifications($visitors);
$upcoming = array_values($visitors);

usort($upcoming, static function ($a, $b) {
    return strcmp(($a['scheduled_date'] ?? ''), ($b['scheduled_date'] ?? ''));
});

$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor & Security Module</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div>
                <p class="eyebrow">Access control</p>
                <h1>Visitor &amp; Security Module</h1>
            </div>
            <div class="header-actions">
                <a class="btn btn-primary" href="register_visitor.php">Register visitor</a>
                <a class="btn btn-secondary" href="pre_register.php">Tenant pre-registration</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <section class="stats">
            <div class="card">
                <span>Total visitors</span>
                <strong><?php echo $summary['total']; ?></strong>
            </div>
            <div class="card">
                <span>Checked in</span>
                <strong><?php echo $summary['checked_in']; ?></strong>
            </div>
            <div class="card">
                <span>Expected</span>
                <strong><?php echo $summary['expected']; ?></strong>
            </div>
            <div class="card">
                <span>Alerts</span>
                <strong><?php echo count($notifications); ?></strong>
            </div>
        </section>

        <section class="toolbar">
            <a href="entry_exit.php" class="btn btn-secondary">Record entry / exit</a>
            <a href="history.php" class="btn btn-secondary">Visitor history</a>
        </section>

        <div class="dashboard-grid">
            <section class="panel">
                <div class="panel-header">
                    <h2>Security notifications</h2>
                </div>

                <?php if (empty($notifications)): ?>
                    <p class="empty-inline">No active security notifications.</p>
                <?php else: ?>
                    <ul class="notification-list">
                        <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                            <li>
                                <strong><?php echo e($notification['title']); ?></strong>
                                <span><?php echo e($notification['message']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Upcoming visits</h2>
                </div>

                <?php if (empty($upcoming)): ?>
                    <p class="empty-inline">No visitor visits recorded.</p>
                <?php else: ?>
                    <ul class="visit-list">
                        <?php foreach (array_slice($upcoming, 0, 5) as $visit): ?>
                            <li>
                                <div>
                                    <strong><?php echo e($visit['full_name'] ?? 'Visitor'); ?></strong>
                                    <small><?php echo e($visit['scheduled_date'] ?? 'No date'); ?></small>
                                </div>
                                <span class="badge badge-<?php echo visitorStatusBadge($visit['status'] ?? 'expected'); ?>"><?php echo e(ucfirst($visit['status'] ?? 'expected')); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
</html>
