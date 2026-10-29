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

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    respond(403, ['error' => 'Manager or administrator access is required.']);
}

try {
    $one = static function (string $sql) use ($conn): array {
        $result = $conn->query($sql);
        if (!$result) {
            throw new RuntimeException('A dashboard query could not be completed.');
        }
        return $result->fetch_assoc() ?: [];
    };

    $now = $one('SELECT NOW() AS generated_at, UNIX_TIMESTAMP(NOW()) AS generated_at_epoch, CURRENT_DATE AS today, DATE_FORMAT(CURRENT_DATE, "%Y-%m-01") AS month_start');
    $portfolio = $one(
        "SELECT COUNT(*) AS units,
                COALESCE(SUM(status = 'Occupied'), 0) AS occupied,
                COUNT(DISTINCT property_id) AS properties
         FROM units"
    );
    $tenants = $one(
        "SELECT COUNT(DISTINCT tenant_id) AS active
         FROM tenancies
         WHERE status = 'Active'"
    );
    $rent = $one(
        "SELECT COALESCE(SUM(CASE
                    WHEN c.due_date < '{$now['today']}'
                        AND c.status <> 'Paid'
                    THEN 1 ELSE 0
                END), 0) AS overdue
         FROM rent_charges c"
    );
    $rentPayments = $one(
        "SELECT COALESCE(SUM(rp.amount), 0) AS collected,
                COUNT(*) AS payment_count
         FROM rent_payments rp
         WHERE rp.payment_status = 'Confirmed'
           AND rp.payment_date >= '{$now['month_start']}'
           AND rp.payment_date <= '{$now['today']}'"
    );
    $balances = $one(
        "SELECT COALESCE(SUM(GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)), 0) AS outstanding
         FROM rent_charges c
         LEFT JOIN (
             SELECT charge_id, SUM(amount) AS amount_paid
             FROM rent_payments
             WHERE payment_status = 'Confirmed'
             GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         WHERE c.status <> 'Paid'"
    );
    $maintenance = $one(
        "SELECT COALESCE(SUM(status NOT IN ('Completed', 'Cancelled')), 0) AS open,
                COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
                COALESCE(SUM(priority IN ('High', 'Emergency') AND status NOT IN ('Completed', 'Cancelled')), 0) AS urgent,
                COALESCE(SUM(status = 'Completed' AND completed_at >= '{$now['month_start']}'), 0) AS completed
         FROM maintenance_requests"
    );
    $complaints = $one(
        "SELECT COALESCE(SUM(status IN ('Open', 'In Progress')), 0) AS open,
                COUNT(*) AS total
         FROM complaints"
    );
    $feedback = $one(
        "SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average_rating
         FROM feedback"
    );
    $utility = $one(
        "SELECT (SELECT COUNT(*) FROM meters) AS meters,
                (SELECT COUNT(*) FROM meter_readings
                 WHERE reading_date >= '{$now['month_start']}'
                   AND reading_date <= '{$now['today']}') AS readings,
                (SELECT COALESCE(SUM(amount), 0) FROM utility_bills
                 WHERE status IN ('Unpaid', 'Partial', 'Overdue')) AS unpaid_bills"
    );
    $expenses = $one(
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM expenses
         WHERE expense_date >= '{$now['month_start']}'
           AND expense_date <= '{$now['today']}'"
    );
    $communications = $one(
        "SELECT (SELECT COUNT(*) FROM announcements) AS announcements,
                (SELECT COUNT(*) FROM notifications WHERE is_read = 0) AS unread,
                (SELECT COUNT(*) FROM announcements
                 WHERE created_at >= '{$now['month_start']}') AS recent"
    );

    $outstanding = (float) ($balances['outstanding'] ?? 0);
    $collected = (float) ($rentPayments['collected'] ?? 0);
    $expenseTotal = (float) ($expenses['total'] ?? 0);
    $unitCount = (int) ($portfolio['units'] ?? 0);
    $occupiedCount = (int) ($portfolio['occupied'] ?? 0);
    $utilization = $unitCount > 0 ? round($occupiedCount * 100 / $unitCount, 1) : 0;

    respond(200, [
        'generatedAt' => (int) $now['generated_at_epoch'],
        'period' => [
            'from' => $now['month_start'],
            'through' => $now['today'],
        ],
        'modules' => [
            'rent' => [
                ['label' => 'Rent collected this month', 'value' => $collected, 'format' => 'currency'],
                ['label' => 'Outstanding rent', 'value' => $outstanding, 'format' => 'currency'],
                ['label' => 'Overdue charges', 'value' => (int) ($rent['overdue'] ?? 0), 'format' => 'number'],
                ['label' => 'Confirmed rent payments', 'value' => (int) ($rentPayments['payment_count'] ?? 0), 'format' => 'number'],
            ],
            'finance' => [
                ['label' => 'Rent income this month', 'value' => $collected, 'format' => 'currency'],
                ['label' => 'Expenses this month', 'value' => $expenseTotal, 'format' => 'currency'],
                ['label' => 'Net operating income', 'value' => $collected - $expenseTotal, 'format' => 'currency'],
                ['label' => 'Outstanding rent', 'value' => $outstanding, 'format' => 'currency'],
            ],
            'maintenance' => [
                ['label' => 'Open requests', 'value' => (int) ($maintenance['open'] ?? 0), 'format' => 'number'],
                ['label' => 'In progress', 'value' => (int) ($maintenance['in_progress'] ?? 0), 'format' => 'number'],
                ['label' => 'Urgent requests', 'value' => (int) ($maintenance['urgent'] ?? 0), 'format' => 'number'],
                ['label' => 'Completed this month', 'value' => (int) ($maintenance['completed'] ?? 0), 'format' => 'number'],
            ],
            'utilities' => [
                ['label' => 'Registered meters', 'value' => (int) ($utility['meters'] ?? 0), 'format' => 'number'],
                ['label' => 'Readings this month', 'value' => (int) ($utility['readings'] ?? 0), 'format' => 'number'],
                ['label' => 'Unpaid utility bills', 'value' => (float) ($utility['unpaid_bills'] ?? 0), 'format' => 'currency'],
                ['label' => 'Occupied units', 'value' => $occupiedCount, 'format' => 'number'],
            ],
            'complaints' => [
                ['label' => 'Open complaints', 'value' => (int) ($complaints['open'] ?? 0), 'format' => 'number'],
                ['label' => 'All complaints', 'value' => (int) ($complaints['total'] ?? 0), 'format' => 'number'],
                ['label' => 'Feedback received', 'value' => (int) ($feedback['total'] ?? 0), 'format' => 'number'],
                ['label' => 'Average feedback rating', 'value' => round((float) ($feedback['average_rating'] ?? 0), 1), 'format' => 'rating'],
            ],
            'communication' => [
                ['label' => 'Announcements sent', 'value' => (int) ($communications['announcements'] ?? 0), 'format' => 'number'],
                ['label' => 'Announcements this month', 'value' => (int) ($communications['recent'] ?? 0), 'format' => 'number'],
                ['label' => 'Unread notifications', 'value' => (int) ($communications['unread'] ?? 0), 'format' => 'number'],
                ['label' => 'Active tenants', 'value' => (int) ($tenants['active'] ?? 0), 'format' => 'number'],
            ],
            'reports' => [
                ['label' => 'Managed properties', 'value' => (int) ($portfolio['properties'] ?? 0), 'format' => 'number'],
                ['label' => 'Occupied units', 'value' => $occupiedCount, 'format' => 'number'],
                ['label' => 'Portfolio occupancy', 'value' => $utilization, 'format' => 'percent'],
                ['label' => 'Rent collected this month', 'value' => $collected, 'format' => 'currency'],
            ],
        ],
    ]);
} catch (Throwable $exception) {
    error_log('Management dashboard data error: ' . $exception->getMessage());
    respond(503, ['error' => 'Live dashboard data is temporarily unavailable.']);
}
