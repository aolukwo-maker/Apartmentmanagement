<?php

function arrayPath(string $filename): string
{
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir . '/' . $filename;
}

function readJsonFile(string $path, array $default = []): array
{
    if (!file_exists($path)) {
        file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $default;
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return $default;
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function writeJsonFile(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function openDatabase(): array
{
    $unitsPath = arrayPath('units.json');
    $rentPath = arrayPath('rent_records.json');

    $unitsData = readJsonFile($unitsPath, ['units' => []]);
    if (!isset($unitsData['units']) || !is_array($unitsData['units'])) {
        $unitsData['units'] = [];
        writeJsonFile($unitsPath, $unitsData);
    }

    readJsonFile($rentPath, ['records' => []]);

    return ['units_path' => $unitsPath, 'rent_path' => $rentPath];
}

function validStatuses(): array
{
    return ['vacant', 'occupied', 'reserved', 'maintenance'];
}

function sanitizeStatus(?string $status): string
{
    $value = strtolower(trim((string) $status));
    return in_array($value, validStatuses(), true) ? $value : 'vacant';
}

function initializeDatabase(): array
{
    return openDatabase();
}

function saveUnit(array $data): ?array
{
    $storage = initializeDatabase();
    $unitsPath = $storage['units_path'];
    $store = readJsonFile($unitsPath, ['units' => []]);
    $units = $store['units'];

    $id = isset($data['id']) ? (int) $data['id'] : 0;
    $unitNumber = trim((string) ($data['unit_number'] ?? ''));
    if ($unitNumber === '') {
        throw new InvalidArgumentException('Unit number is required.');
    }

    $status = sanitizeStatus($data['status'] ?? 'vacant');
    $monthlyRent = (float) ($data['monthly_rent'] ?? 0);
    $tenantName = trim((string) ($data['tenant_name'] ?? ''));
    $tenantName = $tenantName === '' ? null : $tenantName;
    $buildingName = trim((string) ($data['building_name'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $bedrooms = max(0, (int) ($data['bedrooms'] ?? 0));
    $bathrooms = max(0, (int) ($data['bathrooms'] ?? 0));

    if ($id > 0) {
        $index = null;
        foreach ($units as $pos => $unit) {
            if ((int) $unit['id'] === $id) {
                $index = $pos;
                break;
            }
        }

        if ($index === null) {
            return null;
        }

        $units[$index] = array_merge($units[$index], [
            'unit_number' => $unitNumber,
            'building_name' => $buildingName !== '' ? $buildingName : null,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'status' => $status,
            'monthly_rent' => $monthlyRent,
            'tenant_name' => $tenantName,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } else {
        $newId = 1;
        foreach ($units as $unit) {
            $newId = max($newId, (int) $unit['id'] + 1);
        }

        $units[] = [
            'id' => $newId,
            'unit_number' => $unitNumber,
            'building_name' => $buildingName !== '' ? $buildingName : null,
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'status' => $status,
            'monthly_rent' => $monthlyRent,
            'tenant_name' => $tenantName,
            'notes' => $notes !== '' ? $notes : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = $newId;
    }

    $store['units'] = $units;
    writeJsonFile($unitsPath, $store);

    return getUnitById($id);
}

function listUnits(?string $status = null): array
{
    $storage = initializeDatabase();
    $units = readJsonFile($storage['units_path'], ['units' => []]);
    $items = $units['units'] ?? [];

    if ($status !== null && $status !== '') {
        $items = array_values(array_filter($items, function ($unit) use ($status) {
            return sanitizeStatus((string) ($unit['status'] ?? 'vacant')) === sanitizeStatus($status);
        }));
    }

    usort($items, function ($left, $right) {
        return strcmp((string) ($left['unit_number'] ?? ''), (string) ($right['unit_number'] ?? ''));
    });

    return $items;
}

function getUnitById(int $unitId): ?array
{
    $storage = initializeDatabase();
    $units = readJsonFile($storage['units_path'], ['units' => []]);
    foreach ($units['units'] ?? [] as $unit) {
        if ((int) $unit['id'] === $unitId) {
            return $unit;
        }
    }

    return null;
}

function assignTenant(int $unitId, string $tenantName): ?array
{
    $unit = getUnitById($unitId);
    if (!$unit) {
        return null;
    }

    $trimmedTenant = trim($tenantName);
    $status = $trimmedTenant !== '' && in_array($unit['status'], ['vacant', 'reserved'], true)
        ? 'occupied'
        : $unit['status'];

    $unit['tenant_name'] = $trimmedTenant !== '' ? $trimmedTenant : null;
    $unit['status'] = sanitizeStatus($status);
    $unit['updated_at'] = date('Y-m-d H:i:s');

    $storage = initializeDatabase();
    $store = readJsonFile($storage['units_path'], ['units' => []]);
    $units = $store['units'];
    foreach ($units as $index => $item) {
        if ((int) $item['id'] === $unitId) {
            $units[$index] = $unit;
            break;
        }
    }

    $store['units'] = $units;
    writeJsonFile($storage['units_path'], $store);

    return getUnitById($unitId);
}

function updateUnitStatus(int $unitId, string $status): ?array
{
    $unit = getUnitById($unitId);
    if (!$unit) {
        return null;
    }

    $unit['status'] = sanitizeStatus($status);
    $unit['updated_at'] = date('Y-m-d H:i:s');

    $storage = initializeDatabase();
    $store = readJsonFile($storage['units_path'], ['units' => []]);
    foreach ($store['units'] as $index => $item) {
        if ((int) $item['id'] === $unitId) {
            $store['units'][$index] = $unit;
            break;
        }
    }

    writeJsonFile($storage['units_path'], $store);
    return getUnitById($unitId);
}

function recordRent(int $unitId, float $amount, ?string $period = null, ?string $paymentDate = null): ?array
{
    $unit = getUnitById($unitId);
    if (!$unit) {
        return null;
    }

    $storage = initializeDatabase();
    $rentData = readJsonFile($storage['rent_path'], ['records' => []]);
    $records = $rentData['records'];
    $records[] = [
        'id' => count($records) + 1,
        'unit_id' => $unitId,
        'amount' => (float) $amount,
        'rent_period' => $period !== null && trim((string) $period) !== '' ? trim((string) $period) : null,
        'payment_date' => $paymentDate !== null && trim((string) $paymentDate) !== '' ? trim((string) $paymentDate) : date('Y-m-d'),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $rentData['records'] = $records;
    writeJsonFile($storage['rent_path'], $rentData);

    return getUnitById($unitId);
}

function getRentEntries(int $unitId): array
{
    $storage = initializeDatabase();
    $rentData = readJsonFile($storage['rent_path'], ['records' => []]);
    $records = $rentData['records'];

    $entries = array_values(array_filter($records, function ($record) use ($unitId) {
        return (int) $record['unit_id'] === $unitId;
    }));

    usort($entries, function ($left, $right) {
        $leftDate = (string) ($left['payment_date'] ?? '');
        $rightDate = (string) ($right['payment_date'] ?? '');
        return strcmp($rightDate, $leftDate);
    });

    return $entries;
}

function getDashboardStats(): array
{
    $stats = [
        'total_units' => 0,
        'vacant' => 0,
        'occupied' => 0,
        'reserved' => 0,
        'maintenance' => 0,
        'total_monthly_rent' => 0.0,
    ];

    $units = listUnits();
    foreach ($units as $unit) {
        $status = sanitizeStatus((string) ($unit['status'] ?? 'vacant'));
        $stats['total_units']++;
        $stats[$status]++;
        $stats['total_monthly_rent'] += (float) ($unit['monthly_rent'] ?? 0);
    }

    return $stats;
}
