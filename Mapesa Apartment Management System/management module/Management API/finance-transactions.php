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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Only GET and POST requests are allowed.']);
}

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'], true)) {
    respond(403, ['error' => 'Manager or administrator access is required.']);
}

try {
    $columnsResult = $conn->query(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses'"
    );
    if (!$columnsResult) {
        throw new RuntimeException('Unable to inspect expense records.');
    }

    $expenseColumns = array_fill_keys(
        array_column($columnsResult->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME'),
        true
    );
    if (!isset($expenseColumns['description'], $expenseColumns['amount'], $expenseColumns['expense_date'])) {
        throw new RuntimeException('The expenses table is missing required transaction columns.');
    }

    $columnName = static function (array $candidates) use ($expenseColumns): ?string {
        foreach ($candidates as $candidate) {
            if (isset($expenseColumns[$candidate])) {
                return $candidate;
            }
        }
        return null;
    };
    $columnExpression = static function (array $candidates) use ($columnName): ?string {
        $column = $columnName($candidates);
        return $column === null ? null : 'expense.`' . str_replace('`', '``', $column) . '`';
    };

    $descriptionColumn = $columnExpression([
        'description',
        'expense_description',
        'title',
        'expense_name',
    ]);
    $categoryColumnName = $columnName(['category', 'expense_category']);
    $categoryColumn = $categoryColumnName === null
        ? null
        : 'expense.`' . str_replace('`', '``', $categoryColumnName) . '`';
    $statusColumn = $columnExpression(['status', 'expense_status']);
    $idColumn = $columnExpression(['expense_id', 'id']);
    $propertyId = filter_var($_POST['propertyId'] ?? null, FILTER_VALIDATE_INT);
    $expenseCategories = ['Maintenance', 'Utilities', 'Staff & operations', 'Other expense'];

    if ($method === 'POST') {
        $type = trim((string) ($_POST['type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $amountInput = trim((string) ($_POST['amount'] ?? ''));
        $expenseDate = trim((string) ($_POST['date'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expenseDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $amount = filter_var($amountInput, FILTER_VALIDATE_FLOAT);

        if ($type !== 'expense'
            || $description === ''
            || strlen($description) > 230
            || !in_array($category, $expenseCategories, true)
            || !preg_match('/^\d+(?:\.\d{1,2})?$/', $amountInput)
            || $amount === false || $amount <= 0 || $amount > 9999999999.99
            || $propertyId === false || $propertyId === null || $propertyId < 1
            || !$date
            || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            || $date->format('Y-m-d') !== $expenseDate) {
            respond(400, ['error' => 'Enter a valid expense description, category, amount, and date.']);
        }

        $propertyStmt = $conn->prepare('SELECT property_id FROM properties WHERE property_id = ?');
        $propertyStmt->bind_param('i', $propertyId);
        $propertyStmt->execute();
        if (!$propertyStmt->get_result()->fetch_assoc()) {
            respond(400, ['error' => 'Select a property that exists in the database.']);
        }

        $storedDescription = $categoryColumn
            ? $description
            : '[' . $category . '] ' . $description;
        if (strlen($storedDescription) > 255) {
            respond(400, ['error' => 'The expense description is too long.']);
        }

        if ($categoryColumnName) {
            $insert = $conn->prepare(
                'INSERT INTO expenses (`property_id`, `description`, `' . str_replace('`', '``', $categoryColumnName) . '`, `amount`, `expense_date`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->bind_param('issds', $propertyId, $storedDescription, $category, $amount, $expenseDate);
        } else {
            $insert = $conn->prepare(
                'INSERT INTO expenses (`property_id`, `description`, `amount`, `expense_date`) VALUES (?, ?, ?, ?)'
            );
            $insert->bind_param('isds', $propertyId, $storedDescription, $amount, $expenseDate);
        }
        if (!$insert->execute()) {
            throw new RuntimeException('Unable to save the expense record.');
        }

        respond(201, ['message' => 'Expense recorded successfully.', 'id' => $conn->insert_id]);
    }

    $expenseDescription = $descriptionColumn
        ? "COALESCE(NULLIF(CAST({$descriptionColumn} AS CHAR), ''), 'Expense record')"
        : "'Expense record'";
    $expenseCategory = $categoryColumn
        ? "COALESCE(NULLIF(CAST({$categoryColumn} AS CHAR), ''), 'Other expense')"
        : "'Other expense'";
    if (!$categoryColumn) {
        $categoryCases = [];
        $descriptionCases = [];
        foreach ($expenseCategories as $category) {
            $prefix = '[' . $category . '] ';
            $quotedPrefix = "'" . $conn->real_escape_string($prefix) . "'";
            $quotedCategory = "'" . $conn->real_escape_string($category) . "'";
            $categoryCases[] = "WHEN LEFT({$descriptionColumn}, LENGTH({$quotedPrefix})) = {$quotedPrefix} THEN {$quotedCategory}";
            $descriptionCases[] = "WHEN LEFT({$descriptionColumn}, LENGTH({$quotedPrefix})) = {$quotedPrefix} THEN SUBSTRING({$descriptionColumn}, LENGTH({$quotedPrefix}) + 1)";
        }
        $expenseCategory = 'CASE ' . implode(' ', $categoryCases) . " ELSE 'Other expense' END";
        $expenseDescription = 'CASE ' . implode(' ', $descriptionCases) . " ELSE {$expenseDescription} END";
    }
    $expenseStatus = $statusColumn
        ? "COALESCE(NULLIF(CAST({$statusColumn} AS CHAR), ''), 'Recorded')"
        : "'Recorded'";
    $expenseId = $idColumn
        ? "CONCAT('expense-', CAST({$idColumn} AS CHAR))"
        : "CONCAT('expense-', MD5(CONCAT(expense.`expense_date`, '-', expense.`amount`, '-', {$expenseDescription})))";
    $collatedText = static fn (string $expression): string =>
        "CONVERT(({$expression}) USING utf8mb4) COLLATE utf8mb4_unicode_ci";

    $sql = "
        SELECT transaction_id AS id, description, category, type, amount, property_name AS property,
               transaction_date AS date, status
        FROM (
            SELECT {$collatedText("CONCAT('payment-', payment.payment_id)")} AS transaction_id,
                   {$collatedText("CONCAT(
                       'Rent payment - Unit ',
                       COALESCE(NULLIF(unit.unit_number, ''), 'Unassigned'),
                       ' - ', tenant.full_name,
                       CASE
                           WHEN payment.reference IS NOT NULL AND payment.reference <> ''
                           THEN CONCAT(' (', payment.reference, ')')
                           ELSE ''
                       END
                   )")} AS description,
                   {$collatedText("'Rent revenue'")} AS category,
                   {$collatedText("'income'")} AS type,
                   payment.amount AS amount,
                   property.property_name AS property_name,
                   {$collatedText("DATE_FORMAT(payment.payment_date, '%Y-%m-%d')")} AS transaction_date,
                   {$collatedText('payment.payment_status')} AS status
            FROM rent_payments payment
            INNER JOIN rent_charges charge ON charge.charge_id = payment.charge_id
            INNER JOIN tenancies tenancy ON tenancy.tenancy_id = charge.tenancy_id
            INNER JOIN tenants tenant ON tenant.tenant_id = tenancy.tenant_id
            LEFT JOIN units unit ON unit.unit_id = tenancy.unit_id
            LEFT JOIN properties property ON property.property_id = unit.property_id
            WHERE payment.payment_status = 'Confirmed'

            UNION ALL

            SELECT {$collatedText($expenseId)} AS transaction_id,
                   {$collatedText($expenseDescription)} AS description,
                   {$collatedText($expenseCategory)} AS category,
                   {$collatedText("'expense'")} AS type,
                   expense.`amount` AS amount,
                   property.property_name AS property_name,
                   {$collatedText("DATE_FORMAT(expense.`expense_date`, '%Y-%m-%d')")} AS transaction_date,
                   {$collatedText($expenseStatus)} AS status
            FROM expenses expense
            LEFT JOIN properties property ON property.property_id = expense.`property_id`
        ) transactions
        ORDER BY transaction_date DESC, id DESC
    ";
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Unable to load financial transactions.');
    }

    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($transactions as &$transaction) {
        $transaction['id'] = (string) $transaction['id'];
        $transaction['description'] = (string) $transaction['description'];
        $transaction['category'] = (string) $transaction['category'];
        $transaction['type'] = (string) $transaction['type'];
        $transaction['amount'] = (float) $transaction['amount'];
        $transaction['property'] = (string) ($transaction['property'] ?? 'Unassigned');
        $transaction['date'] = (string) $transaction['date'];
        $transaction['status'] = (string) $transaction['status'];
    }
    unset($transaction);

    $propertiesResult = $conn->query(
        'SELECT property_id AS id, property_name AS name FROM properties ORDER BY property_name'
    );
    if (!$propertiesResult) {
        throw new RuntimeException('Unable to load properties.');
    }
    $properties = $propertiesResult->fetch_all(MYSQLI_ASSOC);
    foreach ($properties as &$property) {
        $property['id'] = (int) $property['id'];
        $property['name'] = (string) $property['name'];
    }
    unset($property);

    respond(200, ['transactions' => $transactions, 'properties' => $properties]);
} catch (Throwable $exception) {
    error_log('Financial transactions API error: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to load financial transactions from the database.']);
}
