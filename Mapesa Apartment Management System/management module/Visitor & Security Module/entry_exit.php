<?php
require __DIR__ . '/includes/functions.php';

$visitors = loadVisitors();
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitorId = $_POST['visitor_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $note = trim((string) ($_POST['security_note'] ?? ''));

    foreach ($visitors as $index => $visitor) {
        if (($visitor['id'] ?? '') !== $visitorId) {
            continue;
        }

        if ($action === 'entry') {
            $visitors[$index]['status'] = 'checked_in';
            $visitors[$index]['check_in_time'] = date('Y-m-d H:i:s');
            $visitors[$index]['security_note'] = $note !== '' ? $note : ($visitors[$index]['security_note'] ?? '');
            $visitors[$index] = addHistoryEvent($visitors[$index], 'Visitor checked in at the gate.');
            $message = 'Visitor marked as checked in.';
        } elseif ($action === 'exit') {
            $visitors[$index]['status'] = 'checked_out';
            $visitors[$index]['check_out_time'] = date('Y-m-d H:i:s');
            $visitors[$index]['security_note'] = $note !== '' ? $note : ($visitors[$index]['security_note'] ?? '');
            $visitors[$index] = addHistoryEvent($visitors[$index], 'Visitor checked out and left the property.');
            $message = 'Visitor marked as checked out.';
        } else {
            $message = 'No action selected.';
        }

        saveVisitors($visitors);
        header('Location: entry_exit.php?success=' . urlencode($message));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Entry / Exit</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="container narrow">
        <header class="topbar compact">
            <div>
                <p class="eyebrow">Gate management</p>
                <h1>Record entry / exit</h1>
            </div>
            <a class="btn btn-secondary" href="index.php">Back to dashboard</a>
        </header>

        <?php if ($success): ?>
            <div class="alert success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <section class="form-card">
            <form method="POST" class="inline-form">
                <label>
                    <span>Select visitor</span>
                    <select name="visitor_id" required>
                        <option value="">Choose a visitor</option>
                        <?php foreach ($visitors as $visitor): ?>
                            <option value="<?php echo e($visitor['id'] ?? ''); ?>"><?php echo e($visitor['full_name'] ?? 'Unknown'); ?> (<?php echo e($visitor['tenant_name'] ?? 'No tenant'); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Action</span>
                    <select name="action" required>
                        <option value="entry">Check-in</option>
                        <option value="exit">Check-out</option>
                    </select>
                </label>

                <label>
                    <span>Security note</span>
                    <textarea name="security_note"></textarea>
                </label>

                <button class="btn btn-primary" type="submit">Save status</button>
            </form>
        </section>
    </div>
</body>
</html>
