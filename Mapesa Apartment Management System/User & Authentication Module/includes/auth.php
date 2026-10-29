<?php
require_once __DIR__ . '/../config.php';

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function syncTenantRecord(array $user): void
{
    global $conn;

    $result = $conn->query('SHOW COLUMNS FROM tenants');
    if (!$result) {
        throw new RuntimeException('Tenant table columns could not be loaded.');
    }
    $availableColumns = array_column($result->fetch_all(MYSQLI_ASSOC), 'Field');
    $values = [
        'user_id' => (int) $user['id'],
        'property_id' => isset($user['property_id']) ? (int) $user['property_id'] : null,
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'phone' => (string) ($user['phone'] ?? ''),
        'national_id' => (string) ($user['national_id'] ?? ''),
        'emergency_contact' => (string) ($user['emergency_contact'] ?? ''),
        'emergency_phone' => (string) ($user['emergency_phone'] ?? ''),
        'unit_number' => (string) ($user['unit_number'] ?? ''),
        'address' => (string) ($user['address'] ?? ''),
    ];
    $columns = array_values(array_intersect(array_keys($values), $availableColumns));
    if (!in_array('user_id', $columns, true) || !in_array('full_name', $columns, true)) {
        throw new RuntimeException('Tenant table is missing required profile columns.');
    }

    $quotedColumns = array_map(
        static fn (string $column): string => '`' . $column . '`',
        $columns
    );
    $updates = array_map(
        static function (string $column): string {
            if ($column === 'property_id') {
                return '`property_id` = COALESCE(VALUES(`property_id`), `property_id`)';
            }
            if (in_array(
                $column,
                ['phone', 'national_id', 'emergency_contact', 'emergency_phone'],
                true
            )) {
                return '`' . $column . '` = COALESCE(NULLIF(VALUES(`' . $column . '`), \'\'), `' . $column . '`)';
            }
            return '`' . $column . '` = VALUES(`' . $column . '`)';
        },
        array_values(array_diff($columns, ['user_id']))
    );
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare(
        'INSERT INTO tenants (' . implode(', ', $quotedColumns) . ')
         VALUES (' . $placeholders . ')
         ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
    );
    $types = '';
    $parameters = [];
    foreach ($columns as $column) {
        $types .= in_array($column, ['user_id', 'property_id'], true) ? 'i' : 's';
        $parameters[] = $values[$column];
    }
    $bindings = [$types];
    foreach ($parameters as &$parameter) {
        $bindings[] = &$parameter;
    }
    unset($parameter);
    call_user_func_array([$stmt, 'bind_param'], $bindings);
    if (!$stmt->execute()) {
        throw new RuntimeException('Tenant record could not be synchronized.');
    }
}

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $currentRole = $_SESSION['role'] ?? '';

    if (!in_array($currentRole, $allowedRoles, true)) {
        header('Location: access_denied.php');
        exit;
    }
}

function redirectToRoleHome(): void
{
    if (($_SESSION['role'] ?? '') === 'Admin') {
        header('Location: ../Admin Module/index.html');
        exit;
    }

    if (($_SESSION['role'] ?? '') === 'Manager') {
        header('Location: /Mapesa%20Apartment%20Management%20System/management%20module/index.html');
        exit;
    }

    header('Location: dashboard.php');
    exit;
}

function redirectIfAuthenticated(): void
{
    if (isLoggedIn()) {
        redirectToRoleHome();
    }
}

function getCurrentUser(): ?array
{
    global $conn;

    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT u.*, u.user_id AS id, u.username AS full_name, r.role_name AS role
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows ? $result->fetch_assoc() : null;
}
