<?php
require_once __DIR__ . '/db.php';

initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_unit':
                $unit = saveUnit($_POST);
                $message = $unit ? 'Unit saved successfully.' : 'Unable to save unit.';
                break;

            case 'assign_tenant':
                $unit = assignTenant((int) ($_POST['unit_id'] ?? 0), (string) ($_POST['tenant_name'] ?? ''));
                $message = $unit ? 'Tenant assignment updated.' : 'Unable to assign tenant.';
                break;

            case 'update_status':
                $unit = updateUnitStatus((int) ($_POST['unit_id'] ?? 0), (string) ($_POST['status'] ?? 'vacant'));
                $message = $unit ? 'Unit status updated.' : 'Unable to update status.';
                break;

            case 'record_rent':
                $unit = recordRent(
                    (int) ($_POST['unit_id'] ?? 0),
                    (float) ($_POST['amount'] ?? 0),
                    (string) ($_POST['rent_period'] ?? ''),
                    (string) ($_POST['payment_date'] ?? '')
                );
                $message = $unit ? 'Rent recorded successfully.' : 'Unable to record rent.';
                break;

            default:
                $message = 'Unknown action.';
                break;
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}

$selectedEditId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$editUnit = $selectedEditId > 0 ? getUnitById($selectedEditId) : null;
$units = listUnits();
$availableUnits = listUnits('vacant');
$occupiedUnits = listUnits('occupied');
$dashboard = getDashboardStats();
$statuses = validStatuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apartment Unit Management</title>
    <style>
        :root {
            --bg: #f3f6fb;
            --panel: #ffffff;
            --primary: #1d4ed8;
            --primary-soft: #dbeafe;
            --success: #15803d;
            --warning: #b45309;
            --danger: #b91c1c;
            --muted: #475569;
            --border: #dfe7f5;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: #0f172a;
        }

        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 20px 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        h1, h2, h3 { margin: 0 0 12px; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .metric {
            font-size: 28px;
            font-weight: 700;
            margin: 8px 0 4px;
        }

        .section-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
        }

        form {
            display: grid;
            gap: 12px;
        }

        .inline-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 600;
            color: var(--muted);
        }

        input, select, textarea, button {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
        }

        textarea { min-height: 82px; resize: vertical; }

        button {
            cursor: pointer;
            font-weight: 700;
            background: var(--primary);
            color: #fff;
            border: none;
        }

        .secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .vacant { background: #dcfce7; color: #166534; }
        .occupied { background: #dbeafe; color: #1d4ed8; }
        .reserved { background: #fef3c7; color: #92400e; }
        .maintenance { background: #fee2e2; color: #991b1b; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th { background: #f8fafc; }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .alert {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px 14px;
            color: #1e3a8a;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .section-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Apartment & Unit Management</h1>
                <p>Track occupancy, tenant assignments, and rent collection.</p>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <div>Total Units</div>
                <div class="metric"><?= (int) $dashboard['total_units']; ?></div>
            </div>
            <div class="card">
                <div>Available</div>
                <div class="metric"><?= (int) $dashboard['vacant']; ?></div>
            </div>
            <div class="card">
                <div>Occupied</div>
                <div class="metric"><?= (int) $dashboard['occupied']; ?></div>
            </div>
            <div class="card">
                <div>Monthly Rent</div>
                <div class="metric">KSh <?= number_format((float) $dashboard['total_monthly_rent'], 2); ?></div>
            </div>
        </div>

        <div class="section-grid">
            <div class="card">
                <h2><?= $editUnit ? 'Edit Unit' : 'Add Unit'; ?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="save_unit">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($editUnit['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="inline-grid">
                        <label>
                            Unit Number
                            <input type="text" name="unit_number" value="<?= htmlspecialchars((string) ($editUnit['unit_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                        <label>
                            Building Name
                            <input type="text" name="building_name" value="<?= htmlspecialchars((string) ($editUnit['building_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>

                    <div class="inline-grid">
                        <label>
                            Bedrooms
                            <input type="number" min="0" name="bedrooms" value="<?= htmlspecialchars((string) ($editUnit['bedrooms'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label>
                            Bathrooms
                            <input type="number" min="0" name="bathrooms" value="<?= htmlspecialchars((string) ($editUnit['bathrooms'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>

                    <div class="inline-grid">
                        <label>
                            Status
                            <select name="status">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?= (($editUnit['status'] ?? 'vacant') === $status) ? 'selected' : ''; ?>>
                                        <?= ucfirst($status); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Monthly Rent
                            <input type="number" min="0" step="0.01" name="monthly_rent" value="<?= htmlspecialchars((string) ($editUnit['monthly_rent'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>

                    <label>
                        Tenant Name
                        <input type="text" name="tenant_name" value="<?= htmlspecialchars((string) ($editUnit['tenant_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <label>
                        Notes
                        <textarea name="notes"><?= htmlspecialchars((string) ($editUnit['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <div class="actions">
                        <button type="submit"><?= $editUnit ? 'Update Unit' : 'Add Unit'; ?></button>
                        <?php if ($editUnit): ?>
                            <a href="index.php" class="secondary" style="display:inline-block; text-decoration:none; text-align:center; padding:10px 12px; border-radius:10px;">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Quick Actions</h2>

                <form method="post">
                    <input type="hidden" name="action" value="assign_tenant">
                    <label>
                        Unit
                        <select name="unit_id">
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id']; ?>"><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Tenant Name
                        <input type="text" name="tenant_name" placeholder="Enter tenant name">
                    </label>
                    <button type="submit">Assign Tenant</button>
                </form>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">

                <form method="post">
                    <input type="hidden" name="action" value="update_status">
                    <label>
                        Update Status
                        <select name="unit_id">
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id']; ?>"><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?> (<?= ucfirst($unit['status']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        New Status
                        <select name="status">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?= ucfirst($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Update Status</button>
                </form>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">

                <form method="post">
                    <input type="hidden" name="action" value="record_rent">
                    <label>
                        Unit
                        <select name="unit_id">
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id']; ?>"><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Amount
                        <input type="number" min="0" step="0.01" name="amount" placeholder="0.00">
                    </label>
                    <label>
                        Rent Period
                        <input type="text" name="rent_period" value="<?= date('Y-m'); ?>">
                    </label>
                    <label>
                        Payment Date
                        <input type="date" name="payment_date" value="<?= date('Y-m-d'); ?>">
                    </label>
                    <button type="submit">Record Rent</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top: 28px;">
            <h2>Unit Overview</h2>
            <table>
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Building</th>
                        <th>Bedrooms</th>
                        <th>Bathrooms</th>
                        <th>Status</th>
                        <th>Tenant</th>
                        <th>Monthly Rent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $unit): ?>
                        <tr>
                            <td><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($unit['building_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int) $unit['bedrooms']; ?></td>
                            <td><?= (int) $unit['bathrooms']; ?></td>
                            <td><span class="status-badge <?= htmlspecialchars($unit['status'], ENT_QUOTES, 'UTF-8'); ?>"><?= ucfirst(htmlspecialchars($unit['status'], ENT_QUOTES, 'UTF-8')); ?></span></td>
                            <td><?= htmlspecialchars((string) ($unit['tenant_name'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>KSh <?= number_format((float) $unit['monthly_rent'], 2); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="index.php?edit_id=<?= (int) $unit['id']; ?>" style="text-decoration:none; color:#0f172a; background:#e2e8f0; padding:8px 10px; border-radius:8px;">Edit</a>
                                    <a href="index.php?status=<?= urlencode($unit['status']); ?>" style="text-decoration:none; color:#0f172a; background:#f1f5f9; padding:8px 10px; border-radius:8px;">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section-grid" style="margin-top: 28px;">
            <div class="card">
                <h2>Available Units</h2>
                <?php if (empty($availableUnits)): ?>
                    <p>No vacant units.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($availableUnits as $unit): ?>
                            <li><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?> - KSh <?= number_format((float) $unit['monthly_rent'], 2); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Occupied Units</h2>
                <?php if (empty($occupiedUnits)): ?>
                    <p>No occupied units.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($occupiedUnits as $unit): ?>
                            <li><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?> - <?= htmlspecialchars((string) ($unit['tenant_name'] ?? 'Tenant not set'), ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
