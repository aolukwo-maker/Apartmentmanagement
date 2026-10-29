<?php
require_once __DIR__ . '/../User & Authentication Module/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Administrator access is required.');
}

try {
    $result = $conn->query(
        'SELECT unit_id, property_id, unit_number, status, rent_amount
         FROM units
         ORDER BY unit_number'
    );
    if (!$result) {
        throw new RuntimeException('The units query could not be completed.');
    }
    $units = $result->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $error) {
    error_log('Admin unit list could not be loaded: ' . $error->getMessage());
    http_response_code(500);
    exit('Units could not be loaded. Please try again later.');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Units | Mapesa Admin Console</title>
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <main class="content-wrap">
      <header class="page-heading">
        <div>
          <p class="eyebrow">Mapesa Apartments</p>
          <h1>Units</h1>
          <p class="heading-copy">Units currently registered in the database.</p>
        </div>
        <a class="button" href="/Mapesa%20Apartment%20Management%20System/Admin%20Module/index.html">Back to overview</a>
      </header>

      <section class="panel users-panel" aria-labelledby="units-heading">
        <div class="panel-heading">
          <div>
            <h2 id="units-heading">Unit table</h2>
            <p><?= count($units); ?> registered <?= count($units) === 1 ? 'unit' : 'units'; ?></p>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Unit ID</th>
                <th>Property ID</th>
                <th>Unit number</th>
                <th>Status</th>
                <th>Rent amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$units): ?>
                <tr>
                  <td colspan="5">No units are registered in the database.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($units as $unit): ?>
                  <tr>
                    <td><?= (int) $unit['unit_id']; ?></td>
                    <td><?= (int) $unit['property_id']; ?></td>
                    <td><?= htmlspecialchars((string) $unit['unit_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) $unit['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>KSh <?= number_format((float) $unit['rent_amount'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </body>
</html>
