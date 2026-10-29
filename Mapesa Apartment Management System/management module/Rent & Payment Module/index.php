<?php
declare(strict_types=1);

$apiRequest = ($_GET['api'] ?? '') === '1';
if ($apiRequest) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

require_once __DIR__ . '/../../User & Authentication Module/config.php';

if ($apiRequest) {

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    respond(403, ['error' => 'Manager or administrator access is required.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Only GET and POST requests are allowed.']);
}

$transactionStarted = false;
try {
    if ($method === 'POST') {
        $paymentTarget = trim((string) ($_POST['ledgerId'] ?? ''));
        $requestedPropertyId = array_key_exists('propertyId', $_POST)
            ? filter_var($_POST['propertyId'], FILTER_VALIDATE_INT)
            : null;
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $paymentDate = trim((string) ($_POST['date'] ?? ''));
        $reference = trim((string) ($_POST['reference'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if ((!preg_match('/^charge:[1-9]\d*$/', $paymentTarget)
                && !preg_match('/^tenancy:[1-9]\d*$/', $paymentTarget))
            || $amount === false || $amount <= 0
            || ($requestedPropertyId !== null
                && ($requestedPropertyId === false || $requestedPropertyId < 1))
            || !$date || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            || $date->format('Y-m-d') !== $paymentDate || strlen($reference) > 100) {
            respond(400, ['error' => 'Enter a valid charge, payment amount, date, and reference.']);
        }

        $conn->begin_transaction();
        $transactionStarted = true;
        $chargeCreated = false;
        if (str_starts_with($paymentTarget, 'tenancy:')) {
            $tenancyId = (int) substr($paymentTarget, strlen('tenancy:'));
            $tenancyStmt = $conn->prepare(
                "SELECT tenancy.tenancy_id, tenancy.monthly_rent, unit.property_id
                 FROM tenancies tenancy
                 INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
                 LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
                 WHERE tenancy.tenancy_id = ?
                   AND tenancy.status = 'Active'
                   AND tenancy.monthly_rent > 0
                   AND TRIM(tenant.full_name) <> ''
                 FOR UPDATE"
            );
            $tenancyStmt->bind_param('i', $tenancyId);
            $tenancyStmt->execute();
            $tenancy = $tenancyStmt->get_result()->fetch_assoc();
            if (!$tenancy) {
                $conn->rollback();
                $transactionStarted = false;
                respond(404, ['error' => 'The selected tenant does not have an active tenancy with a valid rent amount.']);
            }

            $periodResult = $conn->query(
                "SELECT DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AS billing_month"
            );
            $billingMonth = (string) $periodResult->fetch_assoc()['billing_month'];
            $currentChargeStmt = $conn->prepare(
                "SELECT c.charge_id, c.amount, c.due_date, unit.property_id,
                        COALESCE((SELECT SUM(rp.amount)
                                  FROM rent_payments rp
                                  WHERE rp.charge_id = c.charge_id
                                    AND rp.payment_status = 'Confirmed'), 0) AS amount_paid
                 FROM rent_charges c
                 INNER JOIN tenancies tenancy ON tenancy.tenancy_id = c.tenancy_id
                 LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
                 WHERE c.tenancy_id = ? AND c.billing_month = ?
                 FOR UPDATE"
            );
            $currentChargeStmt->bind_param('is', $tenancyId, $billingMonth);
            $currentChargeStmt->execute();
            $charge = $currentChargeStmt->get_result()->fetch_assoc();

            if (!$charge) {
                $monthlyRent = (float) $tenancy['monthly_rent'];
                $insertChargeStmt = $conn->prepare(
                    "INSERT INTO rent_charges (tenancy_id, amount, billing_month, due_date, status)
                     VALUES (?, ?, ?, ?, 'Unpaid')"
                );
                $insertChargeStmt->bind_param(
                    'idss',
                    $tenancyId,
                    $monthlyRent,
                    $billingMonth,
                    $billingMonth
                );
                $insertChargeStmt->execute();
                $charge = [
                    'charge_id' => $conn->insert_id,
                    'amount' => $monthlyRent,
                    'due_date' => $billingMonth,
                    'amount_paid' => 0,
                    'property_id' => $tenancy['property_id'],
                ];
                $chargeCreated = true;
            }
        } else {
            $chargeId = (int) substr($paymentTarget, strlen('charge:'));
            $chargeStmt = $conn->prepare(
                "SELECT c.charge_id, c.amount, c.due_date, unit.property_id,
                        COALESCE((SELECT SUM(rp.amount)
                                  FROM rent_payments rp
                                  WHERE rp.charge_id = c.charge_id
                                    AND rp.payment_status = 'Confirmed'), 0) AS amount_paid
                 FROM rent_charges c
                 INNER JOIN tenancies tenancy ON tenancy.tenancy_id = c.tenancy_id
                 INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
                 LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
                 WHERE c.charge_id = ?
                   AND TRIM(tenant.full_name) <> ''
                 FOR UPDATE"
            );
            $chargeStmt->bind_param('i', $chargeId);
            $chargeStmt->execute();
            $charge = $chargeStmt->get_result()->fetch_assoc();
        }

        if (!$charge) {
            $conn->rollback();
            $transactionStarted = false;
            respond(404, ['error' => 'The selected rent charge was not found.']);
        }

        if ($requestedPropertyId !== null
            && (int) ($charge['property_id'] ?? 0) !== $requestedPropertyId) {
            $conn->rollback();
            $transactionStarted = false;
            respond(400, ['error' => 'The selected rent charge does not belong to this property.']);
        }

        $balance = max((float) $charge['amount'] - (float) $charge['amount_paid'], 0);
        if ($amount > $balance) {
            $conn->rollback();
            $transactionStarted = false;
            respond(400, ['error' => 'Payment amount exceeds the outstanding charge balance.']);
        }

        $insertStmt = $conn->prepare(
            "INSERT INTO rent_payments (charge_id, amount, payment_date, reference, payment_status)
             VALUES (?, ?, ?, NULLIF(?, ''), 'Confirmed')"
        );
        $chargeId = (int) $charge['charge_id'];
        $insertStmt->bind_param('idss', $chargeId, $amount, $paymentDate, $reference);
        $insertStmt->execute();

        $remaining = $balance - $amount;
        $chargeStatus = $remaining <= 0
            ? 'Paid'
            : ((string) $charge['due_date'] < date('Y-m-d') ? 'Overdue' : 'Partial');
        $updateStmt = $conn->prepare('UPDATE rent_charges SET status = ? WHERE charge_id = ?');
        $updateStmt->bind_param('si', $chargeStatus, $chargeId);
        $updateStmt->execute();
        $conn->commit();
        $transactionStarted = false;

        respond(201, [
            'message' => 'Payment recorded successfully.',
            'chargeCreated' => $chargeCreated,
        ]);
    }

    $nowResult = $conn->query(
        "SELECT CURRENT_DATE AS today,
                DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AS month_start,
                DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-01') AS previous_month_start,
                DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-%d') AS previous_month_end,
                DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS refreshed_at"
    );
    $now = $nowResult->fetch_assoc();
    $portfolioResult = $conn->query(
        "SELECT (SELECT COUNT(*) FROM properties) AS property_count,
                (SELECT COUNT(*) FROM units WHERE status = 'Occupied') AS occupied_units"
    );
    $portfolio = $portfolioResult->fetch_assoc();
    $summaryResult = $conn->query(
        "SELECT
            (SELECT COALESCE(SUM(rp.amount), 0)
             FROM rent_payments rp
             INNER JOIN rent_charges rc ON rc.charge_id = rp.charge_id
             INNER JOIN tenancies rt ON rt.tenancy_id = rc.tenancy_id
             INNER JOIN tenants rtenant ON rtenant.tenant_id = rt.tenant_id
             WHERE rp.payment_status = 'Confirmed'
               AND TRIM(rtenant.full_name) <> ''
               AND rp.payment_date >= '{$now['month_start']}'
               AND rp.payment_date <= '{$now['today']}') AS collected_this_month,
            (SELECT COALESCE(SUM(rp.amount), 0)
             FROM rent_payments rp
             INNER JOIN rent_charges rc ON rc.charge_id = rp.charge_id
             INNER JOIN tenancies rt ON rt.tenancy_id = rc.tenancy_id
             INNER JOIN tenants rtenant ON rtenant.tenant_id = rt.tenant_id
             WHERE rp.payment_status = 'Confirmed'
               AND TRIM(rtenant.full_name) <> ''
               AND rp.payment_date >= '{$now['previous_month_start']}'
               AND rp.payment_date <= '{$now['previous_month_end']}') AS collected_last_month,
            COALESCE(SUM(c.amount), 0) AS total_charged,
            COALESCE(SUM(COALESCE(p.amount_paid, 0)), 0) AS total_paid,
            COALESCE(SUM(GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)), 0) AS outstanding,
            COALESCE(SUM(CASE
                WHEN c.due_date < '{$now['today']}'
                THEN GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0)
                ELSE 0
            END), 0) AS overdue,
            COALESCE(SUM(GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) > 0), 0) AS open_charges,
            COALESCE(SUM(
                c.due_date < '{$now['today']}'
                AND GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) > 0
            ), 0) AS overdue_charges
         FROM rent_charges c
         INNER JOIN tenancies tenancy ON tenancy.tenancy_id = c.tenancy_id
         INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
         LEFT JOIN (
             SELECT charge_id, SUM(amount) AS amount_paid
             FROM rent_payments
             WHERE payment_status = 'Confirmed'
             GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         WHERE TRIM(tenant.full_name) <> ''"
    );
    $summary = $summaryResult->fetch_assoc();

    $ledgerResult = $conn->query(
        "SELECT c.charge_id AS id, tenant.full_name AS tenant,
                COALESCE(unit.unit_number, 'Unassigned') AS unit,
                DATE_FORMAT(c.billing_month, '%b %Y') AS period,
                DATE_FORMAT(c.billing_month, '%Y-%m-%d') AS billingMonth,
                c.amount AS due, COALESCE(p.amount_paid, 0) AS paid,
                GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) AS balance,
                c.due_date AS dueDate,
                latest_payment.amount AS latestPaymentAmount,
                latest_payment.payment_date AS latestPaymentDate,
                latest_payment.reference AS latestPaymentReference,
                CASE
                    WHEN c.charge_id IS NULL THEN 'none'
                    WHEN GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) = 0 THEN 'paid'
                    WHEN c.due_date < CURRENT_DATE THEN 'overdue'
                    WHEN COALESCE(p.amount_paid, 0) > 0 THEN 'partial'
                    ELSE 'due'
                END AS status
                FROM tenants tenant
                LEFT JOIN tenancies tenancy ON tenancy.tenant_id = tenant.tenant_id
                LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
                LEFT JOIN rent_charges c ON c.tenancy_id = tenancy.tenancy_id
         LEFT JOIN (
             SELECT charge_id, SUM(amount) AS amount_paid
             FROM rent_payments
             WHERE payment_status = 'Confirmed'
             GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         LEFT JOIN (
             SELECT charge_id, MAX(payment_id) AS payment_id
             FROM rent_payments
             WHERE payment_status = 'Confirmed'
             GROUP BY charge_id
         ) latest ON latest.charge_id = c.charge_id
         LEFT JOIN rent_payments latest_payment ON latest_payment.payment_id = latest.payment_id
         WHERE TRIM(tenant.full_name) <> ''
           AND (c.charge_id IS NOT NULL OR tenancy.status = 'Active' OR tenancy.tenancy_id IS NULL)
         ORDER BY c.billing_month DESC, c.charge_id DESC"
    );
    $ledgers = $ledgerResult->fetch_all(MYSQLI_ASSOC);
    foreach ($ledgers as &$ledger) {
        $ledger['id'] = $ledger['id'] === null ? null : (int) $ledger['id'];
        $ledger['due'] = (float) $ledger['due'];
        $ledger['paid'] = (float) $ledger['paid'];
        $ledger['balance'] = (float) $ledger['balance'];
        $ledger['latestPaymentAmount'] = $ledger['latestPaymentAmount'] === null
            ? null
            : (float) $ledger['latestPaymentAmount'];
        $ledger['initials'] = implode('', array_map(
            static fn (string $part): string => strtoupper(substr($part, 0, 1)),
            array_slice(preg_split('/\s+/', trim((string) $ledger['tenant'])) ?: [], 0, 2)
        ));
    }
    unset($ledger);

    $paymentOptionsResult = $conn->query(
        "SELECT CONCAT('charge:', c.charge_id) AS target,
                tenant.full_name AS tenantName,
               unit.property_id AS propertyId,
               CONCAT('Unit ', COALESCE(unit.unit_number, 'Unassigned'),
                       ' · ', DATE_FORMAT(c.billing_month, '%b %Y'),
                       ' · KES ', FORMAT(GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0), 2), ' due') AS details,
                GREATEST(c.amount - COALESCE(p.amount_paid, 0), 0) AS max_amount
         FROM rent_charges c
         INNER JOIN tenancies tenancy ON tenancy.tenancy_id = c.tenancy_id
         INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
         LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
         LEFT JOIN (
             SELECT charge_id, SUM(amount) AS amount_paid
             FROM rent_payments
             WHERE payment_status = 'Confirmed'
             GROUP BY charge_id
         ) p ON p.charge_id = c.charge_id
         WHERE TRIM(tenant.full_name) <> ''
           AND c.amount > COALESCE(p.amount_paid, 0)

         UNION ALL

         SELECT CONCAT('tenancy:', tenancy.tenancy_id) AS target,
                tenant.full_name AS tenantName,
                unit.property_id AS propertyId,
                CONCAT('Unit ', COALESCE(unit.unit_number, 'Unassigned'),
                       ' · Create ', DATE_FORMAT(CURRENT_DATE, '%b %Y'),
                       ' charge · KES ', FORMAT(tenancy.monthly_rent, 2)) AS details,
                tenancy.monthly_rent AS max_amount
         FROM tenancies tenancy
         INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
         LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
         WHERE tenancy.status = 'Active'
           AND tenancy.monthly_rent > 0
           AND TRIM(tenant.full_name) <> ''
           AND NOT EXISTS (
               SELECT 1
               FROM rent_charges current_charge
               WHERE current_charge.tenancy_id = tenancy.tenancy_id
                 AND current_charge.billing_month = DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
           )
         ORDER BY tenantName, details"
    );
    $paymentOptions = $paymentOptionsResult->fetch_all(MYSQLI_ASSOC);
    foreach ($paymentOptions as &$option) {
        $option['maxAmount'] = (float) $option['maxAmount'];
        $option['propertyId'] = $option['propertyId'] === null
            ? null
            : (int) $option['propertyId'];
    }
    unset($option);

    respond(200, [
        'ledgers' => $ledgers,
        'paymentOptions' => $paymentOptions,
        'summary' => [
            'collectedThisMonth' => (float) $summary['collected_this_month'],
            'collectedLastMonth' => (float) $summary['collected_last_month'],
            'totalCharged' => (float) $summary['total_charged'],
            'totalPaid' => (float) $summary['total_paid'],
            'outstanding' => (float) $summary['outstanding'],
            'overdue' => (float) $summary['overdue'],
            'openCharges' => (int) $summary['open_charges'],
            'overdueCharges' => (int) $summary['overdue_charges'],
            'propertyCount' => (int) $portfolio['property_count'],
            'occupiedUnits' => (int) $portfolio['occupied_units'],
            'today' => $now['today'],
            'monthStart' => $now['month_start'],
            'refreshedAt' => $now['refreshed_at'],
        ],
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('Rent ledger request failed: ' . $exception->getMessage());
    respond(503, ['error' => 'Rent ledger data is temporarily unavailable.']);
}
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../../User%20%26%20Authentication%20Module/login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    header('Location: ../../User%20%26%20Authentication%20Module/access_denied.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mapesa | Rent & Payments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="../module-navigation.css" />
  </head>
  <body>
    <div class="app-shell">
      <aside class="sidebar">
        <div class="brand">
          <span class="brand-mark">M</span><span>mapesa</span>
        </div>
        <div class="workspace-label">PROPERTY OPERATIONS</div>
        <nav class="nav-list" aria-label="Main navigation">
          <a href="#" class="nav-item"
            ><span class="nav-icon">⌂</span>Overview</a
          >
          <a href="#ledger" class="nav-item active"
            ><span class="nav-icon">▤</span>Rent & payments</a
          >
          <a href="#tenants" class="nav-item"
            ><span class="nav-icon">♙</span>Tenants</a
          >
          <a href="#reports" class="nav-item"
            ><span class="nav-icon">◫</span>Reports</a
          >
        </nav>
        <div class="sidebar-bottom">
          <div class="property-switcher">
            <span class="property-dot"></span
            ><span
              ><strong id="propertyName">All properties</strong
              ><small id="propertySummary">Loading portfolio...</small></span
            ><span class="chevron">⌄</span>
          </div>
          <a href="#" class="nav-item muted"
            ><span class="nav-icon">⚙</span>Settings</a
          >
          <div class="user-card">
            <span class="avatar" data-manager-initials>M</span
            ><span
              ><strong data-manager-name>Manager account</strong
              ><small data-manager-role>Manager</small></span
            ><span class="chevron">•••</span>
          </div>
        </div>
      </aside>

      <main class="main-content">
        <header class="topbar">
          <div class="breadcrumb">
            <span id="breadcrumbProperty">All properties</span><span>/</span
            ><strong>Rent & payments</strong>
          </div>
          <div class="top-actions">
            <button class="icon-button" title="Notifications">
              ♢<span class="notification-dot"></span></button
            ><button class="help-button">?</button>
          </div>
        </header>

        <nav class="module-navigation" aria-label="Management modules">
          <a class="module-home" href="../index.html">Dashboard</a>
          <details class="module-switcher">
            <summary>Workspaces</summary>
            <div class="module-menu">
              <a href="index.php" aria-current="page">Rent & payments</a>
              <a href="../Financial%20Management%20Module/index.html"
                >Financial management</a
              >
              <a href="../Maintenance%20Module/index.html">Maintenance</a>
              <a href="../Utility%20%20Management%20Module/index.html"
                >Utilities</a
              >
              <a href="../Complaints%20%20Feedback%20Module/index.html"
                >Complaints & feedback</a
              >
              <a
                href="../Communication%20%26%20Notification%20Module/index.html"
                >Communication</a
              >
              <a href="../Reports%20%26%20Analytics%20Module/index.html"
                >Reports & analytics</a
              >
            </div>
          </details>
        </nav>

        <section class="page-heading" id="ledger">
          <div>
            <p class="eyebrow" id="periodLabel">FINANCE</p>
            <h1>Rent & payments</h1>
            <p class="heading-copy">
              Keep your rent ledger accurate and your cash flow clear.
            </p>
          </div>
          <div class="heading-actions">
            <span class="data-updated" id="dataUpdated" role="status">Connecting to database...</span>
            <button class="button secondary" id="exportBtn">
              ↓ <span>Export ledger</span></button
            ><button class="button primary" id="recordPaymentBtn">
              + <span>Record payment</span>
            </button>
          </div>
        </section>

        <section class="summary-grid" aria-label="Financial summary">
          <article class="summary-card">
            <div class="summary-top">
              <span class="summary-label">COLLECTED THIS MONTH</span
              ><span class="summary-icon mint">↗</span>
            </div>
            <strong class="summary-value" id="collectedTotal">KES 0</strong>
            <div class="trend positive">
              <span id="collectionTrend">--</span><em> vs last month</em>
            </div>
          </article>
          <article class="summary-card">
            <div class="summary-top">
              <span class="summary-label">OUTSTANDING BALANCE</span
              ><span class="summary-icon amber">◌</span>
            </div>
            <strong class="summary-value" id="outstandingTotal">KES 0</strong>
            <div class="trend neutral">
              <span id="openInvoices">0</span> open invoices
            </div>
          </article>
          <article class="summary-card">
            <div class="summary-top">
              <span class="summary-label">OVERDUE RENT</span
              ><span class="summary-icon coral">!</span>
            </div>
            <strong class="summary-value" id="overdueTotal">KES 0</strong>
            <div class="trend negative">
              <span id="overdueUnits">0 units</span><em> need attention</em>
            </div>
          </article>
          <article class="summary-card">
            <div class="summary-top">
              <span class="summary-label">LIFETIME COLLECTION RATE</span
              ><span class="summary-icon blue">◔</span>
            </div>
            <strong class="summary-value" id="collectionRate">0%</strong>
            <div class="progress-track"><span id="rateBar"></span></div>
          </article>
        </section>

        <section class="content-panel">
          <div class="panel-heading">
            <div>
              <h2>Payment ledger</h2>
              <p>Rent charges and confirmed payments for registered tenants.</p>
            </div>
            <div class="panel-controls">
              <label class="search-box"
                ><span>⌕</span
                ><input
                  id="searchInput"
                  type="search"
                  placeholder="Search tenant or unit" /></label
              ><select id="statusFilter" aria-label="Filter by status">
                <option value="all">All statuses</option>
                <option value="paid">Paid</option>
                <option value="partial">Partial</option>
                <option value="due">Due</option>
                <option value="overdue">Overdue</option>
                <option value="none">No charge</option></select
              ><button class="more-button" title="More options">•••</button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tenant / unit</th>
                  <th>Billing period</th>
                  <th>Amount due</th>
                  <th>Paid</th>
                  <th>Balance</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="ledgerBody"></tbody>
            </table>
            <div id="emptyState" class="empty-state" hidden>
              Loading tenant rent data...
            </div>
          </div>
          <div class="table-footer">
            <span id="recordCount">Showing 0 records</span>
            <div class="pagination">
              <button id="previousPage" aria-label="Previous page" disabled>‹</button>
              <span id="pageInfo">1 / 1</span>
              <button id="nextPage" aria-label="Next page" disabled>›</button>
            </div>
          </div>
        </section>

        <section class="bottom-grid">
          <div class="methods-panel">
            <div class="section-heading">
              <div>
                <h2>Rent charges by status</h2>
                <p>Registered tenant charges grouped by payment status.</p>
              </div>
            </div>
            <div id="methodList"></div>
          </div>
        </section>
      </main>
    </div>

    <div class="modal-backdrop" id="modalBackdrop" hidden>
      <div
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle"
      >
        <button class="modal-close" id="closeModal" aria-label="Close">
          ×
        </button>
        <p class="eyebrow">NEW TRANSACTION</p>
        <h2 id="modalTitle">Record payment</h2>
        <p class="modal-copy">
          Record a payment for a database tenant. If the current month has no charge,
          it will be created from the active tenancy's monthly rent.
        </p>
        <form id="transactionForm">
          <label
          >Tenant / unit / billing period<select
              name="ledgerId"
              id="ledgerSelect"
              required
            ></select
          ></label>
          <small class="payment-hint">
            Payments are limited to the selected charge balance or the active tenancy's monthly rent.
          </small>
          <div class="form-row">
            <label
              >Amount (KES)<input
                name="amount"
                type="number"
                min="0.01"
                step="0.01"
                required
                placeholder="0" /></label
            ><label
              >Payment date<input name="date" type="date" required
            /></label>
          </div>
          <label
            >Reference number <span class="optional">(optional)</span
            ><input
              name="reference"
              type="text"
              placeholder="e.g. QH72K1L9" /></label
          ><button class="button primary full-width" type="submit">
            Save payment
          </button>
        </form>
      </div>
    </div>
    <div class="toast" id="toast" role="status"></div>
    <script src="../management-live.js"></script>
    <script src="app.js"></script>
  </body>
</html>
