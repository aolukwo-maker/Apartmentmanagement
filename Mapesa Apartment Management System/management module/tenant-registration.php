<?php
declare(strict_types=1);

require_once __DIR__ . '/../User & Authentication Module/config.php';
require_once __DIR__ . '/../User & Authentication Module/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../User%20%26%20Authentication%20Module/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Manager') {
    header('Location: ../User%20%26%20Authentication%20Module/access_denied.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$input = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'property_id' => '',
    'unit_id' => '',
    'national_id' => '',
    'emergency_contact' => '',
    'emergency_phone' => '',
];
$success = null;

try {
    $propertyResult = $conn->query(
        "SELECT p.property_id, p.property_name, u.unit_id, u.unit_number, u.rent_amount
         FROM properties p
         INNER JOIN units u ON u.property_id = p.property_id
         WHERE u.status = 'Vacant'
         ORDER BY p.property_name, u.unit_number"
    );
    $availableUnits = $propertyResult->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
    error_log('Available properties could not be loaded for tenant registration: ' . $exception->getMessage());
    http_response_code(500);
    exit('Property and unit options could not be loaded. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($input) as $field) {
        $value = $_POST[$field] ?? '';
        $input[$field] = is_string($value) ? trim($value) : '';
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password']
        : '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if ($input['full_name'] === '' || strlen($input['full_name']) > 50) {
        $errors[] = 'Enter a tenant name of up to 50 characters.';
    }
    if (
        strlen($input['email']) > 100
        || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Enter a valid email address of up to 100 characters.';
    }
    if (strlen($input['phone']) > 30) {
        $errors[] = 'Phone number must be 30 characters or fewer.';
    }
    if ($input['property_id'] === '' || filter_var($input['property_id'], FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Select a property.';
    }
    if ($input['unit_id'] === '' || filter_var($input['unit_id'], FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Select a vacant unit.';
    }
    if (strlen($input['national_id']) > 50) {
        $errors[] = 'ID number must be 50 characters or fewer.';
    }
    if (strlen($input['emergency_contact']) > 150) {
        $errors[] = 'Emergency contact name must be 150 characters or fewer.';
    }
    if (strlen($input['emergency_phone']) > 30) {
        $errors[] = 'Emergency contact phone must be 30 characters or fewer.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $transactionStarted = false;
        try {
            $roleName = 'Tenant';
            $roleStmt = $conn->prepare(
                'SELECT role_id FROM roles WHERE role_name = ? LIMIT 1'
            );
            $roleStmt->bind_param('s', $roleName);
            $roleStmt->execute();
            $role = $roleStmt->get_result()->fetch_assoc();
            if (!$role) {
                throw new RuntimeException('Tenant role is not configured.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Tenant password could not be secured.');
            }

            $conn->begin_transaction();
            $transactionStarted = true;

            $unitId = (int) $input['unit_id'];
            $propertyId = (int) $input['property_id'];
            $unitStmt = $conn->prepare(
                "SELECT unit_id, rent_amount
                 FROM units
                 WHERE unit_id = ? AND property_id = ? AND status = 'Vacant'
                 FOR UPDATE"
            );
            $unitStmt->bind_param('ii', $unitId, $propertyId);
            $unitStmt->execute();
            $unit = $unitStmt->get_result()->fetch_assoc();
            if (!$unit) {
                throw new DomainException(
                    'That unit is no longer vacant in the selected property. Choose another unit.'
                );
            }

            $username = $input['full_name'];
            $roleId = (int) $role['role_id'];
            $createUser = $conn->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, status)
                 VALUES (?, ?, ?, ?, 'Active')"
            );
            $createUser->bind_param(
                'sssi',
                $username,
                $input['email'],
                $passwordHash,
                $roleId
            );
            $createUser->execute();
            $userId = (int) $conn->insert_id;

            syncTenantRecord([
                'id' => $userId,
                'property_id' => $propertyId,
                'full_name' => $input['full_name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'national_id' => $input['national_id'],
                'emergency_contact' => $input['emergency_contact'],
                'emergency_phone' => $input['emergency_phone'],
            ]);

            $tenantStmt = $conn->prepare(
                'SELECT tenant_id FROM tenants WHERE user_id = ? LIMIT 1'
            );
            $tenantStmt->bind_param('i', $userId);
            $tenantStmt->execute();
            $tenantProfile = $tenantStmt->get_result()->fetch_assoc();
            if (!$tenantProfile) {
                throw new RuntimeException('Tenant profile could not be loaded.');
            }
            $tenantId = (int) $tenantProfile['tenant_id'];
            $startDate = date('Y-m-d');
            $monthlyRent = (float) $unit['rent_amount'];
            $depositAmount = 0.0;
            $tenancyStmt = $conn->prepare(
                "INSERT INTO tenancies
                    (tenant_id, unit_id, start_date, monthly_rent, deposit_amount, status)
                 VALUES (?, ?, ?, ?, ?, 'Active')"
            );
            $tenancyStmt->bind_param(
                'iisdd',
                $tenantId,
                $unitId,
                $startDate,
                $monthlyRent,
                $depositAmount
            );
            $tenancyStmt->execute();

            $updateUnit = $conn->prepare(
                "UPDATE units SET status = 'Occupied' WHERE unit_id = ?"
            );
            $updateUnit->bind_param('i', $unitId);
            $updateUnit->execute();

            $conn->commit();
            $transactionStarted = false;
            $success = 'Tenant account created successfully.';
            $input = array_fill_keys(array_keys($input), '');
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (DomainException $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            $errors[] = $exception->getMessage();
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            if (
                $exception instanceof mysqli_sql_exception
                && $exception->getCode() === 1062
            ) {
                $errors[] = 'An account with that email already exists.';
            } else {
                error_log('Manager could not create tenant account: ' . $exception->getMessage());
                $errors[] = 'The tenant account could not be created. Please try again.';
            }
        }
    }
}

$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#123b3d" />
    <title>Register tenant | Mapesa Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="module-navigation.css" />
  </head>
  <body>
      <main class="tenant-registration-page">
        <section class="tenant-registration-content">
          <header class="tenant-registration-heading">
            <span class="hub-eyebrow">TENANT MANAGEMENT</span>
            <h1>Register a tenant</h1>
            <p>Create the tenant's sign-in account and property profile.</p>
          </header>

          <?php if ($success !== null): ?>
            <div class="tenant-registration-alert is-success" role="status">
              <?= $escape($success) ?>
            </div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="tenant-registration-alert is-error" role="alert">
              <ul>
                <?php foreach ($errors as $error): ?>
                  <li><?= $escape($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form class="tenant-registration-form" method="post" action="tenant-registration.php">
            <input
              type="hidden"
              name="csrf_token"
              value="<?= $escape($_SESSION['csrf_token']) ?>"
            />
            <div class="tenant-form-grid">
              <label>
                <span>Full name</span>
                <input
                  type="text"
                  name="full_name"
                  maxlength="50"
                  autocomplete="name"
                  value="<?= $escape($input['full_name']) ?>"
                  required
                />
              </label>
              <label>
                <span>Email address</span>
                <input
                  type="email"
                  name="email"
                  maxlength="100"
                  autocomplete="email"
                  value="<?= $escape($input['email']) ?>"
                  required
                />
              </label>
              <label>
                <span>Phone number</span>
                <input
                  type="tel"
                  name="phone"
                  maxlength="30"
                  autocomplete="tel"
                  value="<?= $escape($input['phone']) ?>"
                />
              </label>
              <label>
                <span>Property name</span>
                <select name="property_id" id="tenant-property" required>
                  <option value="">Select a property</option>
                  <?php
                    $properties = [];
                    foreach ($availableUnits as $availableUnit) {
                        $properties[(string) $availableUnit['property_id']] = $availableUnit['property_name'];
                    }
                    foreach ($properties as $propertyId => $propertyName):
                  ?>
                    <option value="<?= $escape((string) $propertyId) ?>" <?= $input['property_id'] === (string) $propertyId ? 'selected' : '' ?>>
                      <?= $escape($propertyName) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>Unit number</span>
                <select name="unit_id" id="tenant-unit" required>
                  <option value="">Select a vacant unit</option>
                  <?php foreach ($availableUnits as $availableUnit): ?>
                    <option
                      value="<?= $escape((string) $availableUnit['unit_id']) ?>"
                      data-property-id="<?= $escape((string) $availableUnit['property_id']) ?>"
                      <?= $input['unit_id'] === (string) $availableUnit['unit_id'] ? 'selected' : '' ?>
                    >
                      <?= $escape($availableUnit['unit_number']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                <span>ID number</span>
                <input
                  type="text"
                  name="national_id"
                  maxlength="50"
                  autocomplete="off"
                  value="<?= $escape($input['national_id']) ?>"
                />
              </label>
              <label>
                <span>Emergency contact name</span>
                <input
                  type="text"
                  name="emergency_contact"
                  maxlength="150"
                  autocomplete="name"
                  value="<?= $escape($input['emergency_contact']) ?>"
                />
              </label>
              <label>
                <span>Emergency contact phone</span>
                <input
                  type="tel"
                  name="emergency_phone"
                  maxlength="30"
                  autocomplete="tel"
                  value="<?= $escape($input['emergency_phone']) ?>"
                />
              </label>
              <label>
                <span>Initial password</span>
                <input
                  type="password"
                  name="password"
                  minlength="6"
                  autocomplete="new-password"
                  required
                />
              </label>
              <label>
                <span>Confirm password</span>
                <input
                  type="password"
                  name="confirm_password"
                  minlength="6"
                  autocomplete="new-password"
                  required
                />
              </label>
            </div>
            <?php if (!$availableUnits): ?>
              <p class="tenant-registration-note" role="status">
                There are no vacant units available for registration.
              </p>
            <?php endif; ?>
            <p class="tenant-registration-note">
              Share the sign-in email and initial password securely with the tenant.
            </p>
            <button class="tenant-registration-submit" type="submit">
              Create tenant account
            </button>
          </form>
        </section>
      </main>
    <script src="management-live.js"></script>
    <script>
      const propertySelect = document.querySelector("#tenant-property");
      const unitSelect = document.querySelector("#tenant-unit");
      const filterUnitsByProperty = () => {
        const propertyId = propertySelect.value;
        for (const option of unitSelect.options) {
          if (!option.value) continue;
          option.hidden = option.dataset.propertyId !== propertyId;
          option.disabled = option.hidden;
          if (option.disabled && option.selected) unitSelect.value = "";
        }
      };
      propertySelect.addEventListener("change", filterUnitsByProperty);
      filterUnitsByProperty();
    </script>
  </body>
</html>
