<?php
require_once __DIR__ . '/../User & Authentication Module/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Only GET requests are allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['message' => 'Administrator access is required.']);
    exit;
}

function readDashboardJson(string $path, string $key): array
{
    if (!is_file($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = $content === false ? null : json_decode($content, true);
    return is_array($data) && isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
}

function dashboardColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function safeDashboardDate(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('c', $timestamp);
}

try {
    global $conn;

    $columnResult = $conn->query('SHOW COLUMNS FROM users');
    if (!$columnResult) {
        throw new RuntimeException('Unable to read user records.');
    }

    $userColumns = [];
    while ($column = $columnResult->fetch_assoc()) {
        $userColumns[] = $column['Field'];
    }

    $nameColumn = dashboardColumn($userColumns, ['full_name', 'username', 'name']);
    $emailColumn = dashboardColumn($userColumns, ['email']);
    $roleColumn = dashboardColumn($userColumns, ['role', 'role_name']);
    $roleJoin = '';
    if ($roleColumn === null && in_array('role_id', $userColumns, true)) {
        $roleTableResult = $conn->query('SHOW COLUMNS FROM roles');
        if ($roleTableResult) {
            $roleColumns = [];
            while ($column = $roleTableResult->fetch_assoc()) {
                $roleColumns[] = $column['Field'];
            }
            $roleIdColumn = dashboardColumn($roleColumns, ['role_id', 'id']);
            $roleNameColumn = dashboardColumn($roleColumns, ['role_name', 'name']);
            if ($roleIdColumn !== null && $roleNameColumn !== null) {
                $roleColumn = 'r.' . $roleNameColumn;
                $roleJoin = " INNER JOIN roles r ON r.`{$roleIdColumn}` = u.`role_id`";
            }
        }
    }
    $statusColumn = dashboardColumn($userColumns, ['status', 'account_status', 'is_active']);
    $createdColumn = dashboardColumn($userColumns, ['created_at']);
    $idColumn = dashboardColumn($userColumns, ['user_id', 'id']);

    $activeUsers = null;
    if ($statusColumn !== null) {
        $statusExpression = $statusColumn === 'is_active'
            ? "`{$statusColumn}` IN (1, '1', 'true', 'yes')"
            : "LOWER(`{$statusColumn}`) IN ('active', 'enabled', 'verified')";
        $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE {$statusExpression}");
        if (!$countResult) {
            throw new RuntimeException('Unable to count active users.');
        }
        $activeUsers = (int) $countResult->fetch_assoc()['total'];
    }

    $selectFields = [];
    foreach ([
        'name' => $nameColumn,
        'email' => $emailColumn,
        'role' => $roleColumn,
        'status' => $statusColumn,
        'created_at' => $createdColumn,
        'id' => $idColumn,
    ] as $alias => $column) {
        if ($column !== null) {
            $columnExpression = $alias === 'role' && str_starts_with((string) $column, 'r.')
                ? 'r.`' . substr($column, 2) . '`'
                : 'u.`' . $column . '`';
            $selectFields[] = "{$columnExpression} AS `{$alias}`";
        }
    }

    $users = [];
    $currentAdmin = [
        'name' => (string) ($_SESSION['full_name'] ?? 'Administrator'),
        'email' => '',
        'role' => (string) ($_SESSION['role'] ?? 'Admin'),
    ];
    if ($selectFields) {
        $orderBy = $createdColumn !== null ? "u.`{$createdColumn}` DESC" : ($idColumn !== null ? "u.`{$idColumn}` DESC" : '1');
        $userResult = $conn->query('SELECT ' . implode(', ', $selectFields) . " FROM users u{$roleJoin} ORDER BY {$orderBy} LIMIT 6");
        if (!$userResult) {
            throw new RuntimeException('Unable to load the team list.');
        }

        while ($user = $userResult->fetch_assoc()) {
            $userStatus = $user['status'] ?? '';
            if ($statusColumn === 'is_active') {
                $userStatus = in_array(strtolower((string) $userStatus), ['1', 'true', 'yes'], true) ? 'Active' : 'Inactive';
            } elseif ($userStatus !== '') {
                $userStatus = ucfirst(strtolower((string) $userStatus));
            } else {
                $userStatus = 'Registered';
            }

            $users[] = [
                'name' => (string) ($user['name'] ?? 'Unnamed user'),
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role'] ?? 'Role not recorded'),
                'status' => $userStatus,
                'createdAt' => safeDashboardDate($user['created_at'] ?? null),
            ];
        }
    }

    if ($idColumn !== null) {
        $adminFields = [];
        foreach (['name' => $nameColumn, 'email' => $emailColumn, 'role' => $roleColumn] as $alias => $column) {
            if ($column !== null) {
                $columnExpression = $alias === 'role' && str_starts_with((string) $column, 'r.')
                    ? 'r.`' . substr($column, 2) . '`'
                    : 'u.`' . $column . '`';
                $adminFields[] = "{$columnExpression} AS `{$alias}`";
            }
        }

        if ($adminFields) {
            $adminQuery = $conn->prepare('SELECT ' . implode(', ', $adminFields) . " FROM users u{$roleJoin} WHERE u.`{$idColumn}` = ? LIMIT 1");
            if ($adminQuery) {
                $adminId = (int) $_SESSION['user_id'];
                $adminQuery->bind_param('i', $adminId);
                $adminQuery->execute();
                $adminRecord = $adminQuery->get_result()->fetch_assoc();
                if ($adminRecord) {
                    $currentAdmin = [
                        'name' => (string) ($adminRecord['name'] ?? $currentAdmin['name']),
                        'email' => (string) ($adminRecord['email'] ?? ''),
                        'role' => (string) ($adminRecord['role'] ?? $currentAdmin['role']),
                    ];
                }
            }
        }
    }

    $managementRoot = dirname(__DIR__) . '/management module';
    $units = readDashboardJson($managementRoot . '/Apartment,Unit Management Module/storage/units.json', 'units');
    $tenants = readDashboardJson($managementRoot . '/Tenant Management Module/storage/tenants.json', '');
    if (!$tenants) {
        $tenantPath = $managementRoot . '/Tenant Management Module/storage/tenants.json';
        if (is_file($tenantPath)) {
            $tenantContent = file_get_contents($tenantPath);
            $tenantData = $tenantContent === false ? null : json_decode($tenantContent, true);
            $tenants = is_array($tenantData) ? $tenantData : [];
        }
    }
    $rentRecords = readDashboardJson($managementRoot . '/Apartment,Unit Management Module/storage/rent_records.json', 'records');

    $unitCounts = ['occupied' => 0, 'vacant' => 0, 'reserved' => 0, 'maintenance' => 0];
    foreach ($units as $unit) {
        $status = strtolower((string) ($unit['status'] ?? 'vacant'));
        if (!array_key_exists($status, $unitCounts)) {
            $status = 'vacant';
        }
        $unitCounts[$status]++;
    }

    $activeTenantCount = 0;
    foreach ($tenants as $tenant) {
        if (strtolower((string) ($tenant['status'] ?? 'active')) === 'active') {
            $activeTenantCount++;
        }
    }

    $month = date('Y-m');
    $rentCollected = 0.0;
    foreach ($rentRecords as $record) {
        $period = (string) ($record['rent_period'] ?? '');
        if (substr($period, 0, 7) === $month) {
            $rentCollected += (float) ($record['amount'] ?? 0);
        }
    }

    $activityResult = $conn->query(
        "SELECT title, detail, activity_date AS date, icon, tone
         FROM (
             SELECT 'Unit record updated' AS title,
                    CONCAT('Unit ', unit_number, ' is ', LOWER(status)) AS detail,
                    updated_at AS activity_date,
                    'building-2' AS icon,
                    'peach' AS tone
             FROM units
             WHERE updated_at IS NOT NULL

             UNION ALL

             SELECT 'Tenant registered' AS title,
                    COALESCE(NULLIF(full_name, ''), CONCAT('Tenant #', tenant_id)) AS detail,
                    created_at AS activity_date,
                    'user-round-plus' AS icon,
                    'mint' AS tone
             FROM tenants
             WHERE created_at IS NOT NULL

             UNION ALL

             SELECT 'Rent payment recorded' AS title,
                    CONCAT(
                        'Unit ', COALESCE(NULLIF(unit.unit_number, ''), 'Unassigned'),
                        ' · KSh ', FORMAT(payment.amount, 2),
                        ' · ', payment.payment_status
                    ) AS detail,
                    payment.created_at AS activity_date,
                    'wallet-cards' AS icon,
                    'blue' AS tone
             FROM rent_payments payment
             LEFT JOIN rent_charges charge ON charge.charge_id = payment.charge_id
             LEFT JOIN tenancies tenancy ON tenancy.tenancy_id = charge.tenancy_id
             LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
             WHERE payment.created_at IS NOT NULL
         ) recent_activity
         ORDER BY activity_date DESC
         LIMIT 5"
    );
    if (!$activityResult) {
        throw new RuntimeException('Unable to load recent database records.');
    }

    $activity = [];
    while ($record = $activityResult->fetch_assoc()) {
        $record['date'] = safeDashboardDate($record['date'] ?? null);
        $activity[] = $record;
    }

    echo json_encode([
        'generatedAt' => date('c'),
        'activeUsers' => $activeUsers,
        'userStatusTracked' => $statusColumn !== null,
        'currentAdmin' => $currentAdmin,
        'users' => $users,
        'units' => array_merge($unitCounts, [
            'total' => count($units),
            'occupancyRate' => count($units) ? round($unitCounts['occupied'] / count($units) * 100, 1) : null,
        ]),
        'activeTenants' => $activeTenantCount,
        'rentCollected' => $rentCollected,
        'rentPeriod' => $month,
        'activity' => array_slice($activity, 0, 5),
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['message' => 'Dashboard data could not be loaded.']);
}