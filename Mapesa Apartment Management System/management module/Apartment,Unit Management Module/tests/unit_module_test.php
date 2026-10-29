<?php
require_once __DIR__ . '/../db.php';

function assertCondition($condition, $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$storageDir = __DIR__ . '/../storage';
if (is_dir($storageDir)) {
    foreach (glob($storageDir . '/*') as $file) {
        @unlink($file);
    }
}

initializeDatabase();

saveUnit([
    'unit_number' => 'A-101',
    'building_name' => 'Mapesa Tower',
    'bedrooms' => 2,
    'bathrooms' => 1,
    'status' => 'vacant',
    'monthly_rent' => 25000,
    'tenant_name' => null,
    'notes' => 'Corner unit'
]);

$units = listUnits();
assertCondition(count($units) >= 1, 'Expected at least one unit to be created');

$unit = $units[0];
assertCondition($unit['unit_number'] === 'A-101', 'Unit number should be saved');

assignTenant($unit['id'], 'Jane Wanjiku');
updateUnitStatus($unit['id'], 'occupied');
recordRent($unit['id'], 25000, '2026-09');

$updated = getUnitById($unit['id']);
assertCondition($updated['tenant_name'] === 'Jane Wanjiku', 'Tenant assignment failed');
assertCondition($updated['status'] === 'occupied', 'Status update failed');

$rentEntries = getRentEntries($unit['id']);
assertCondition(count($rentEntries) >= 1, 'Rent entry should be recorded');
assertCondition((float) $rentEntries[0]['amount'] === 25000.0, 'Rent amount should be saved correctly');

$availableUnits = listUnits('vacant');
assertCondition(is_array($availableUnits), 'Available units should be returned as an array');

$occupiedUnits = listUnits('occupied');
assertCondition(is_array($occupiedUnits), 'Occupied units should be returned as an array');

echo "Apartment unit module test passed.\n";
