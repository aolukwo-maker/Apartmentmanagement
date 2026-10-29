<?php
require __DIR__ . '/includes/functions.php';

$visitors = loadVisitors();
$history = [];

foreach ($visitors as $visitor) {
    foreach (($visitor['history'] ?? []) as $entry) {
        $history[] = [
            'visitor' => $visitor['full_name'] ?? 'Unknown visitor',
            'tenant' => $visitor['tenant_name'] ?? 'Unknown tenant',
            'date' => $entry['date'] ?? '',
            'message' => $entry['message'] ?? '',
            'status' => $visitor['status'] ?? 'expected',
        ];
    }
}

usort($history, static function ($a, $b) {
    return strcmp(($b['date'] ?? ''), ($a['date'] ?? ''));
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor History</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Audit trail</p>
                <h1>Visitor history</h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to dashboard</a>
        </header>

        <section class="detail-card">
            <?php if (empty($history)): ?>
                <p>No visitor activity recorded yet.</p>
            <?php else: ?>
                <ul class="history-list">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <strong><?php echo e($entry['visitor']); ?></strong>
                            <span><?php echo e($entry['tenant']); ?></span>
                            <small><?php echo e($entry['date']); ?></small>
                            <p><?php echo e($entry['message']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
