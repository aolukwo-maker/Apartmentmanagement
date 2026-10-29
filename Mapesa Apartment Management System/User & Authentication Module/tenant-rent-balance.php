<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'Tenant') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Access denied.']);
    exit;
}

$tenantId = (int) $_SESSION['user_id'];

try {
    $summaryStmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)), 0) AS outstanding,
            COALESCE(SUM(CASE
                WHEN c.due_date < CURRENT_DATE
                THEN GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)
                ELSE 0
            END), 0) AS overdue,
            COALESCE(SUM(CASE
                WHEN YEAR(c.billing_month) = YEAR(CURRENT_DATE)
                    AND MONTH(c.billing_month) = MONTH(CURRENT_DATE)
                THEN GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)
                ELSE 0
            END), 0) AS current_month
         FROM tenants tenant
         INNER JOIN tenancies t ON t.tenant_id = tenant.tenant_id
         INNER JOIN rent_charges c ON c.tenancy_id = t.tenancy_id
         LEFT JOIN (
            SELECT charge_id, SUM(amount) AS amount_paid
            FROM rent_payments
            WHERE payment_status = 'Confirmed'
            GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         WHERE tenant.user_id = ?"
    );
    $summaryStmt->bind_param('i', $tenantId);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();

    $chargesStmt = $conn->prepare(
        "SELECT DATE_FORMAT(c.billing_month, '%b %Y') AS period_label, c.amount, c.due_date,
                COALESCE(p.amount_paid, 0) AS amount_paid,
                GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) AS balance,
                CASE
                    WHEN GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) = 0 THEN 'Paid'
                    WHEN c.due_date < CURRENT_DATE THEN 'Overdue'
                    ELSE 'Due'
                END AS status
         FROM tenants tenant
         INNER JOIN tenancies t ON t.tenant_id = tenant.tenant_id
         INNER JOIN rent_charges c ON c.tenancy_id = t.tenancy_id
         LEFT JOIN (
            SELECT charge_id, SUM(amount) AS amount_paid
            FROM rent_payments
            WHERE payment_status = 'Confirmed'
            GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         WHERE tenant.user_id = ?
         ORDER BY c.billing_month DESC, c.charge_id DESC
         LIMIT 12"
    );
    $chargesStmt->bind_param('i', $tenantId);
    $chargesStmt->execute();

    echo json_encode([
        'ok' => true,
        'outstanding' => $summary['outstanding'] ?? '0.00',
        'overdue' => $summary['overdue'] ?? '0.00',
        'current_month' => $summary['current_month'] ?? '0.00',
        'charges' => $chargesStmt->get_result()->fetch_all(MYSQLI_ASSOC),
    ]);
} catch (mysqli_sql_exception $exception) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Rent balance is temporarily unavailable.']);
}