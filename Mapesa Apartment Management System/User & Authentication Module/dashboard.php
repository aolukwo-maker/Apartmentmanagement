<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$tenantRequests = [];
$requestErrors = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Tenant') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $requestErrors[] = 'Your session expired. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'tenant_request') {
        $requestType = is_string($_POST['request_type'] ?? null) ? $_POST['request_type'] : '';
        $subject = is_string($_POST['subject'] ?? null) ? trim($_POST['subject']) : '';
        $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
        $urgency = is_string($_POST['urgency'] ?? null) ? $_POST['urgency'] : '';
        $noticeDate = is_string($_POST['notice_date'] ?? null) ? trim($_POST['notice_date']) : '';
        $allowedTypes = ['Emergency', 'Notice', 'Feedback'];
        $allowedUrgencies = ['Normal', 'Urgent', 'Critical'];

        if (!in_array($requestType, $allowedTypes, true)) {
            $requestErrors[] = 'Choose a valid request type.';
        }
        if ($subject === '' || strlen($subject) > 150) {
            $requestErrors[] = 'Enter a subject of 1 to 150 characters.';
        }
        if ($message === '' || strlen($message) > 5000) {
            $requestErrors[] = 'Enter details of up to 5,000 characters.';
        }
        if (!in_array($urgency, $allowedUrgencies, true)) {
            $requestErrors[] = 'Choose a valid urgency.';
        }
        if ($requestType !== 'Notice') {
            $noticeDate = '';
        } elseif ($noticeDate !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $noticeDate);
            if (!$date || $date->format('Y-m-d') !== $noticeDate) {
                $requestErrors[] = 'Enter a valid notice date.';
            }
        }

        if (empty($requestErrors)) {
            $noticeDateValue = $noticeDate !== '' ? $noticeDate : null;
            $stmt = $conn->prepare(
                'INSERT INTO tenant_requests (user_id, request_type, subject, message, urgency, notice_date)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'isssss',
                $_SESSION['user_id'],
                $requestType,
                $subject,
                $message,
                $urgency,
                $noticeDateValue
            );

            if ($stmt->execute()) {
                setFlash('success', 'Your ' . strtolower($requestType) . ' was submitted.');
                header('Location: dashboard.php');
                exit;
            }

            $requestErrors[] = 'We could not submit your request. Please try again.';
        }
    } else {
        $requestErrors[] = 'That request could not be processed.';
    }
}

if ($role === 'Tenant') {
    $stmt = $conn->prepare(
        'SELECT request_type, subject, message, urgency, notice_date, status, created_at
         FROM tenant_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $tenantRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$roleLinks = [
    'Admin' => ['Manage Users', 'Reports', 'All Roles'],
    'Manager' => ['Assignments', 'Manage Tenants', 'View Reports'],
    'Tenant' => ['My Profile', 'Payment Tracker', 'Support'],
    'Caretaker' => ['Work Orders', 'Maintenance'],
    'Security' => ['Visitor Logs', 'Incident Reports'],
];
$dashboardClass = $role === 'Tenant' ? 'card dashboard-card tenant-dashboard' : 'card dashboard-card';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="<?= $role === 'Tenant' ? 'tenant-app-shell' : 'container' ?>">
        <?php if ($role === 'Tenant'): ?>
            <aside class="tenant-sidebar">
                <a class="tenant-brand" href="#dashboard" aria-label="Mapesa tenant dashboard">
                    <span class="tenant-brand-mark">M</span>
                    <span>MAPESA<small>RESIDENT PORTAL</small></span>
                </a>
                <p class="sidebar-label">YOUR HOME</p>
                <nav id="tenantNavigation" class="tenant-nav" aria-label="Tenant navigation">
                    <a class="tenant-nav-link active" href="#dashboard" aria-current="page"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a>
                    <a class="tenant-nav-link" href="profile.php"><i data-lucide="user-round"></i><span>My Profile</span></a>
                    <a class="tenant-nav-link" href="#house"><i data-lucide="house"></i><span>My House</span></a>
                    <a class="tenant-nav-link" href="#rent-payments"><i data-lucide="wallet-cards"></i><span>Rent Payments</span></a>
                    <a class="tenant-nav-link" href="#payment-history"><i data-lucide="receipt-text"></i><span>Payment History</span></a>
                    <a class="tenant-nav-link" href="#maintenance-requests"><i data-lucide="wrench"></i><span>Maintenance Requests</span></a>
                    <a class="tenant-nav-link" href="#notifications"><i data-lucide="bell"></i><span>Notifications</span></a>
                </nav>
                <a class="tenant-nav-link tenant-logout" href="logout.php"><i data-lucide="log-out"></i><span>Logout</span></a>
                <div class="sidebar-resident">
                    <span class="resident-avatar"><?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))) ?></span>
                    <span><strong><?= htmlspecialchars($user['full_name']) ?></strong><small>Resident account</small></span>
                </div>
            </aside>
            <main class="tenant-main">
                <nav class="topbar tenant-topbar" aria-label="Page navigation">
                    <button class="tenant-menu-toggle" type="button" aria-controls="tenantNavigation" aria-expanded="false">
                        <i data-lucide="menu"></i><span>Menu</span>
                    </button>
                    <div class="topbar-context"><span>Resident portal</span><span aria-hidden="true">/</span><strong>Dashboard</strong></div>
                    <a class="topbar-notification" href="#notifications" aria-label="View notifications">
                        <i data-lucide="bell"></i><span class="notification-dot" aria-hidden="true"></span>
                    </a>
                    <a class="topbar-profile" href="profile.php">
                        <span class="topbar-profile-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="topbar-profile-icon"><i data-lucide="user-round"></i></span>
                    </a>
                </nav>
        <?php else: ?>
            <nav class="topbar">
                <div class="topbar-links">
                    <a href="profile.php">Profile</a>
                    <a href="logout.php">Logout</a>
                </div>
            </nav>
        <?php endif; ?>

        <div id="dashboard" class="<?= htmlspecialchars($dashboardClass) ?>">
            <?php if ($role === 'Tenant'): ?>
                <header class="tenant-heading">
                    <div>
                        <p class="tenant-eyebrow">TENANT DASHBOARD</p>
                        <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?></h1>
                        <p>Manage your communication with property management.</p>
                    </div>
                    <a class="tenant-profile-link" href="profile.php">View profile</a>
                </header>
                <div class="tenant-contact-line">
                    <span><?= htmlspecialchars($user['email']) ?></span>
                    <?php if (!empty($user['unit_number'])): ?>
                        <span>Unit <?= htmlspecialchars($user['unit_number']) ?></span>
                    <?php endif; ?>
                </div>
                <section id="rent-payments" class="rent-balance-panel" aria-labelledby="rent-balance-title">
                    <div class="rent-balance-heading">
                        <div>
                            <p class="tenant-eyebrow">ACCOUNT OVERVIEW</p>
                            <h2 id="rent-balance-title">Rent payments</h2>
                        </div>
                        <span class="balance-live-indicator"><span></span> Live updates</span>
                    </div>
                    <div class="balance-summary-grid" aria-live="polite">
                        <article class="balance-summary">
                            <span class="balance-icon"><i data-lucide="calendar-days"></i></span>
                            <span>Monthly rent</span>
                            <strong id="rentMonthly">Loading...</strong>
                        </article>
                        <article class="balance-summary balance-outstanding">
                            <span class="balance-icon"><i data-lucide="wallet"></i></span>
                            <span>Total outstanding</span>
                            <strong id="rentOutstanding">Loading...</strong>
                        </article>
                        <article class="balance-summary">
                            <span class="balance-icon"><i data-lucide="calendar-clock"></i></span>
                            <span>Next payment date</span>
                            <strong id="rentNextDate">Loading...</strong>
                        </article>
                        <article class="balance-summary">
                            <span class="balance-icon"><i data-lucide="circle-check"></i></span>
                            <span>Payment status</span>
                            <strong id="rentPaymentStatus">Loading...</strong>
                        </article>
                    </div>
                    <div class="balance-secondary"><span>Overdue <strong id="rentOverdue">Loading...</strong></span><span>Current month balance <strong id="rentCurrentMonth">Loading...</strong></span></div>
                    <p class="balance-updated" id="rentBalanceUpdated">Fetching your latest account data...</p>
                    <div id="payment-history" class="rent-transactions">
                        <div class="subsection-heading"><div><h3>Recent rent transactions</h3><p>Charges and confirmed payments on your account</p></div></div>
                        <div class="rent-table-scroll">
                            <table class="rent-transactions-table">
                                <thead>
                                    <tr><th scope="col">Billing period</th><th scope="col">Rent charged</th><th scope="col">Paid</th><th scope="col">Balance</th><th scope="col">Due date</th><th scope="col">Status</th></tr>
                                </thead>
                                <tbody id="rentChargeList">
                                    <tr><td colspan="6" class="rent-table-message">Loading rent charges...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section id="house" class="tenant-house-panel" aria-labelledby="house-title">
                    <div class="section-title-row"><div><p class="tenant-eyebrow">TENANCY DETAILS</p><h2 id="house-title">My house</h2></div><a href="profile.php">Edit profile</a></div>
                    <div class="house-details-grid">
                        <div class="house-detail"><span class="house-detail-icon"><i data-lucide="door-open"></i></span><span><small>Unit</small><strong><?= htmlspecialchars(($user['unit_number'] ?? '') ?: 'Not provided') ?></strong></span></div>
                        <div class="house-detail"><span class="house-detail-icon"><i data-lucide="map-pin"></i></span><span><small>Address</small><strong><?= htmlspecialchars(($user['address'] ?? '') ?: 'Not provided') ?></strong></span></div>
                        <div class="house-detail"><span class="house-detail-icon"><i data-lucide="mail"></i></span><span><small>Contact email</small><strong><?= htmlspecialchars($user['email']) ?></strong></span></div>
                        <div class="house-detail"><span class="house-detail-icon"><i data-lucide="phone"></i></span><span><small>Phone</small><strong><?= htmlspecialchars(($user['phone'] ?? '') ?: 'Not provided') ?></strong></span></div>
                    </div>
                </section>
            <?php else: ?>
                <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?></h1>
                <p><strong>Role:</strong> <?= htmlspecialchars($role) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <?php endif; ?>

            <?php $flash = getFlash('success'); if ($flash): ?>
                <div class="alert success"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <?php if (!empty($requestErrors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach ($requestErrors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($role !== 'Tenant'): ?>
                <div class="grid">
                    <?php foreach (($roleLinks[$role] ?? ['Overview']) as $link): ?>
                        <div class="mini-box"><?= htmlspecialchars($link) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($role === 'Admin'): ?>
                <div class="section-box">
                    <h3>Admin Access</h3>
                    <p>Manage all users, roles, and system settings.</p>
                </div>
            <?php elseif ($role === 'Manager'): ?>
                <div class="section-box">
                    <h3>Manager Access</h3>
                    <p>Coordinate staff, tenants, and operational tasks.</p>
                </div>
            <?php elseif ($role === 'Tenant'): ?>
                <section id="maintenance-requests" class="tenant-support-section">
                    <div class="section-title-row"><div><p class="tenant-eyebrow">TENANT SUPPORT</p><h2>Maintenance requests &amp; support</h2></div></div>
                    <div class="tenant-actions">
                    <section class="section-box request-panel emergency-panel">
                        <h2>Report an Emergency</h2>
                        <p class="form-hint">For immediate danger, contact local emergency services first.</p>
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="tenant_request">
                            <input type="hidden" name="request_type" value="Emergency">
                            <div class="form-group">
                                <label for="emergency_subject">What is happening?</label>
                                <input type="text" id="emergency_subject" name="subject" maxlength="150" required>
                            </div>
                            <div class="form-group">
                                <label for="emergency_message">Location and details</label>
                                <textarea id="emergency_message" name="message" rows="4" maxlength="5000" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="emergency_urgency">Urgency</label>
                                <select id="emergency_urgency" name="urgency">
                                    <option value="Critical">Critical</option>
                                    <option value="Urgent">Urgent</option>
                                    <option value="Normal">Normal</option>
                                </select>
                            </div>
                            <button class="btn btn-danger" type="submit">Send Emergency Report</button>
                        </form>
                    </section>

                    <section class="section-box request-panel">
                        <h2>Give Notice</h2>
                        <p class="form-hint">Send management a formal notice, such as a planned move-out or access notice.</p>
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="tenant_request">
                            <input type="hidden" name="request_type" value="Notice">
                            <div class="form-group">
                                <label for="notice_subject">Notice subject</label>
                                <input type="text" id="notice_subject" name="subject" maxlength="150" required>
                            </div>
                            <div class="form-group">
                                <label for="notice_date">Effective date, if applicable</label>
                                <input type="date" id="notice_date" name="notice_date">
                            </div>
                            <div class="form-group">
                                <label for="notice_message">Notice details</label>
                                <textarea id="notice_message" name="message" rows="4" maxlength="5000" required></textarea>
                            </div>
                            <button class="btn" type="submit">Submit Notice</button>
                        </form>
                    </section>

                    <section class="section-box request-panel">
                        <h2>Share Feedback</h2>
                        <p class="form-hint">Tell management what is working or what could be improved.</p>
                        <form method="POST" action="dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="tenant_request">
                            <input type="hidden" name="request_type" value="Feedback">
                            <div class="form-group">
                                <label for="feedback_subject">Subject</label>
                                <input type="text" id="feedback_subject" name="subject" maxlength="150" required>
                            </div>
                            <div class="form-group">
                                <label for="feedback_message">Your feedback</label>
                                <textarea id="feedback_message" name="message" rows="4" maxlength="5000" required></textarea>
                            </div>
                            <button class="btn" type="submit">Send Feedback</button>
                        </form>
                    </section>
                    </div>
                </section>

                <section class="section-box request-history">
                    <div class="section-title-row"><div><p class="tenant-eyebrow">REQUEST TRACKER</p><h2>Your recent requests</h2></div><span class="section-count"><?= count($tenantRequests) ?> recent</span></div>
                    <?php if (empty($tenantRequests)): ?>
                        <p>You have not submitted anything yet.</p>
                    <?php else: ?>
                        <?php foreach ($tenantRequests as $request): ?>
                            <article class="request-entry">
                                <div class="request-entry-heading">
                                    <strong><?= htmlspecialchars($request['subject']) ?></strong>
                                    <span class="request-badge"><?= htmlspecialchars($request['request_type']) ?></span>
                                </div>
                                <p><?= nl2br(htmlspecialchars($request['message'])) ?></p>
                                <small>
                                    <?= htmlspecialchars(date('M j, Y g:i a', strtotime($request['created_at']))) ?>
                                    · Status: <?= htmlspecialchars($request['status']) ?>
                                    <?php if ($request['request_type'] === 'Emergency'): ?>
                                        · <?= htmlspecialchars($request['urgency']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($request['notice_date'])): ?>
                                        · Effective date: <?= htmlspecialchars($request['notice_date']) ?>
                                    <?php endif; ?>
                                </small>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <section id="notifications" class="notification-panel">
                    <span class="notification-icon"><i data-lucide="bell-ring"></i></span>
                    <div><p class="tenant-eyebrow">IMPORTANT</p><h2>Emergency assistance</h2><p>For immediate danger or a safety emergency, contact local emergency services first. Use the emergency report form to notify property management.</p></div>
                </section>
            <?php elseif ($role === 'Caretaker'): ?>
                <div class="section-box">
                    <h3>Caretaker Access</h3>
                    <p>Track maintenance requests and property upkeep tasks.</p>
                </div>
            <?php else: ?>
                <div class="section-box">
                    <h3>Security Access</h3>
                    <p>Monitor visitor logs, incidents, and access controls.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($role === 'Tenant'): ?>
            </main>
        <?php endif; ?>
    </div>
    <?php if ($role === 'Tenant'): ?>
        <script>
            const rentMoney = new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency: 'KES',
                maximumFractionDigits: 2
            });

            async function refreshRentBalance() {
                const updated = document.querySelector('#rentBalanceUpdated');
                try {
                    const response = await fetch('tenant-rent-balance.php', {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store'
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.message || 'Unable to load balance.');
                    }

                    document.querySelector('#rentOutstanding').textContent = rentMoney.format(Number(data.outstanding));
                    document.querySelector('#rentOverdue').textContent = rentMoney.format(Number(data.overdue));
                    document.querySelector('#rentCurrentMonth').textContent = rentMoney.format(Number(data.current_month));

                    const latestCharge = data.charges[0];
                    const today = new Date().toISOString().slice(0, 10);
                    const nextCharge = [...data.charges]
                        .filter(charge => Number(charge.balance) > 0 && charge.due_date >= today)
                        .sort((first, second) => first.due_date.localeCompare(second.due_date))[0];
                    document.querySelector('#rentMonthly').textContent = latestCharge
                        ? rentMoney.format(Number(latestCharge.amount))
                        : 'Not available';
                    document.querySelector('#rentNextDate').textContent = nextCharge
                        ? new Date(`${nextCharge.due_date}T00:00:00`).toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })
                        : Number(data.outstanding) > 0 ? 'Past due' : 'No payment due';
                    const paymentStatus = document.querySelector('#rentPaymentStatus');
                    paymentStatus.textContent = latestCharge ? latestCharge.status : 'No charges';
                    paymentStatus.className = `payment-status-value${latestCharge ? ` ${latestCharge.status.toLowerCase()}` : ''}`;

                    const chargeList = document.querySelector('#rentChargeList');
                    chargeList.replaceChildren();
                    if (data.charges.length === 0) {
                        const empty = document.createElement('tr');
                        const message = document.createElement('td');
                        message.className = 'rent-table-message';
                        message.colSpan = 6;
                        message.textContent = 'No rent charges have been added to your account yet.';
                        empty.append(message);
                        chargeList.append(empty);
                    } else {
                        data.charges.forEach(charge => {
                            const row = document.createElement('tr');
                            const period = document.createElement('th');
                            period.scope = 'row';
                            period.textContent = charge.period_label;
                            const rent = document.createElement('td');
                            rent.textContent = rentMoney.format(Number(charge.amount));
                            const paid = document.createElement('td');
                            paid.textContent = rentMoney.format(Number(charge.amount_paid));
                            const balance = document.createElement('td');
                            balance.textContent = rentMoney.format(Number(charge.balance));
                            const dueDate = document.createElement('td');
                            dueDate.textContent = new Date(`${charge.due_date}T00:00:00`).toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
                            const statusCell = document.createElement('td');
                            const status = document.createElement('span');
                            status.className = `rent-charge-status ${charge.status.toLowerCase()}`;
                            status.textContent = charge.status;
                            statusCell.append(status);
                            row.append(period, rent, paid, balance, dueDate, statusCell);
                            chargeList.append(row);
                        });
                    }

                    updated.textContent = `Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
                } catch (error) {
                    ['#rentMonthly', '#rentOutstanding', '#rentNextDate', '#rentPaymentStatus', '#rentOverdue', '#rentCurrentMonth'].forEach(selector => {
                        document.querySelector(selector).textContent = 'Unavailable';
                    });
                    const errorRow = document.createElement('tr');
                    const errorCell = document.createElement('td');
                    errorCell.className = 'rent-table-message';
                    errorCell.colSpan = 6;
                    errorCell.textContent = 'Rent information is temporarily unavailable.';
                    errorRow.append(errorCell);
                    document.querySelector('#rentChargeList').replaceChildren(errorRow);
                    updated.textContent = 'Balance temporarily unavailable. Retrying shortly.';
                }
            }

            refreshRentBalance();
            window.setInterval(refreshRentBalance, 30000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) refreshRentBalance();
            });

            const tenantShell = document.querySelector('.tenant-app-shell');
            const menuToggle = document.querySelector('.tenant-menu-toggle');
            const tenantNav = document.querySelector('#tenantNavigation');

            function closeTenantMenu() {
                tenantShell.classList.remove('tenant-menu-open');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.querySelector('span').textContent = 'Menu';
            }

            menuToggle.addEventListener('click', () => {
                const isOpen = tenantShell.classList.toggle('tenant-menu-open');
                menuToggle.setAttribute('aria-expanded', String(isOpen));
                menuToggle.querySelector('span').textContent = isOpen ? 'Close' : 'Menu';
            });

            tenantNav.addEventListener('click', event => {
                if (event.target.closest('a') && window.matchMedia('(max-width: 760px)').matches) {
                    closeTenantMenu();
                }
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeTenantMenu();
            });
        </script>
    <?php endif; ?>
    <?php if ($role === 'Tenant'): ?>
        <script src="https://unpkg.com/lucide@latest"></script>
        <script>if (window.lucide) window.lucide.createIcons();</script>
    <?php endif; ?>
</body>
</html>
