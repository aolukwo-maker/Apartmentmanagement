document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  let adminName = 'Administrator';
  const navItems = document.querySelectorAll('.nav-item');
  const pageTitle = document.getElementById('pageTitle');
  let dashboardData = null;

  function updateGreeting() {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    pageTitle.textContent = `${greeting}, ${adminName.split(/\s+/)[0]}.`;
  }

  updateGreeting();
  document.getElementById('dashboardDate').textContent = new Intl.DateTimeFormat(undefined, {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
  }).format(new Date());

  function formatMoney(amount) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'KES', maximumFractionDigits: 0 }).format(amount);
  }

  function formatDate(value) {
    if (!value) return 'Date not recorded';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'Date not recorded' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
  }

  function renderDashboard(data) {
    dashboardData = data;
    const units = data.units;
    if (data.currentAdmin?.name) adminName = data.currentAdmin.name;
    document.querySelectorAll('[data-admin-name]').forEach((element) => {
      element.textContent = adminName;
    });
    document.querySelectorAll('[data-admin-email]').forEach((element) => {
      element.textContent = data.currentAdmin?.email || data.currentAdmin?.role || 'Admin account';
    });
    const initials = adminName.split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
    document.querySelectorAll('[data-admin-initials]').forEach((element) => {
      element.textContent = initials || 'AD';
    });
    updateGreeting();

    document.getElementById('activeUsersValue').textContent = data.activeUsers ?? 'Not tracked';
    document.getElementById('activeUsersNote').textContent = data.userStatusTracked
      ? 'Accounts marked active in the user database'
      : `Status is not tracked; ${data.users.length} recent accounts shown below`;
    document.getElementById('occupiedUnitsValue').innerHTML = `${units.occupied}<span class="metric-total"> / ${units.total}</span>`;
    document.getElementById('occupancyNote').textContent = units.occupancyRate === null
      ? 'No unit records are available'
      : `${units.occupancyRate}% of registered units occupied`;
    document.getElementById('activeTenantsValue').textContent = data.activeTenants;
    document.getElementById('activeTenantsNote').textContent = 'Active tenant records';
    document.getElementById('rentCollectedValue').textContent = formatMoney(data.rentCollected);
    document.getElementById('rentCollectedNote').textContent = `Recorded payments for ${data.rentPeriod}`;
    document.getElementById('occupancyRate').textContent = units.occupancyRate === null ? 'No unit data' : `${units.occupancyRate}% occupied`;
    document.getElementById('occupiedLegend').textContent = units.occupied;
    document.getElementById('vacantLegend').textContent = units.vacant;
    document.getElementById('otherUnitsLegend').textContent = `Reserved: ${units.reserved} · Maintenance: ${units.maintenance}`;

    const chartY = 220 - ((units.occupancyRate ?? 0) / 100) * 200;
    document.getElementById('occupancyArea').setAttribute('d', `M0,${chartY} L700,${chartY} L700,240 L0,240 Z`);
    document.getElementById('occupancyLine').setAttribute('d', `M0,${chartY} L700,${chartY}`);
    document.getElementById('occupancyPoint').setAttribute('cy', String(chartY));

    const activityList = document.getElementById('activityList');
    activityList.replaceChildren();
    if (!data.activity.length) {
      activityList.innerHTML = '<p class="empty-state">No unit, tenant, or rent activity has been recorded in the database.</p>';
    } else {
      data.activity.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'activity-item';
        row.innerHTML = `<div class="activity-icon ${item.tone}"><i data-lucide="${item.icon}"></i></div><div><strong></strong><p></p><small></small></div>`;
        row.querySelector('strong').textContent = item.title;
        row.querySelector('p').textContent = item.detail;
        row.querySelector('small').textContent = formatDate(item.date);
        activityList.append(row);
      });
    }

    const teamRows = document.getElementById('teamRows');
    teamRows.replaceChildren();
    if (!data.users.length) {
      teamRows.innerHTML = '<tr><td colspan="4" class="empty-state">No user records were returned.</td></tr>';
    } else {
      data.users.forEach((user, index) => {
        const row = document.createElement('tr');
        const initials = user.name.split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
        const roleClass = /admin/i.test(user.role) ? 'admin' : /manager/i.test(user.role) ? 'manager' : 'operations';
        const statusClass = /active|verified/i.test(user.status) ? 'active' : /pending|invited/i.test(user.status) ? 'invited' : '';
        row.innerHTML = `<td><div class="user-cell"><div class="avatar avatar-${['coral', 'yellow', 'blue'][index % 3]}"></div><div><strong></strong><span></span></div></div></td><td><span class="role-badge ${roleClass}"></span></td><td></td><td><span class="status-badge ${statusClass}"></span></td>`;
        row.querySelector('.avatar').textContent = initials || '?';
        row.querySelector('.user-cell strong').textContent = user.name;
        row.querySelector('.user-cell span').textContent = user.email;
        row.querySelector('.role-badge').textContent = user.role;
        row.children[2].textContent = user.createdAt ? formatDate(user.createdAt) : 'Not recorded';
        row.querySelector('.status-badge').textContent = user.status;
        teamRows.append(row);
      });
    }

    if (window.lucide) lucide.createIcons();
  }

  let dashboardRequestInProgress = false;
  function loadDashboard() {
    if (dashboardRequestInProgress) return;
    dashboardRequestInProgress = true;
    fetch('dashboard-data.php', { credentials: 'same-origin', cache: 'no-store' })
      .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
      .then(({ ok, data }) => {
        if (!ok) throw new Error(data.message || 'Dashboard data is unavailable.');
        renderDashboard(data);
      })
      .catch((error) => {
        console.error('Dashboard data request failed:', error);
        document.getElementById('activeUsersNote').textContent = 'Database data unavailable';
        document.getElementById('occupancyNote').textContent = 'Database data unavailable';
        document.getElementById('activeTenantsNote').textContent = 'Database data unavailable';
        document.getElementById('rentCollectedNote').textContent = 'Database data unavailable';
        document.getElementById('activityList').innerHTML = '<p class="empty-state">Dashboard data could not be loaded. Open this page through the authenticated XAMPP site.</p>';
        document.getElementById('teamRows').innerHTML = '<tr><td colspan="4" class="empty-state">Dashboard data could not be loaded.</td></tr>';
      })
      .finally(() => {
        dashboardRequestInProgress = false;
      });
  }

  loadDashboard();
  window.setInterval(loadDashboard, 30000);

  const sidebar = document.getElementById('sidebar');
  navItems.forEach((item) => item.addEventListener('click', () => {
    if (item.hash) {
      navItems.forEach((navItem) => navItem.classList.toggle('active', navItem === item));
    }
    sidebar.classList.remove('open');
  }));
  document.getElementById('menuToggle').addEventListener('click', () => sidebar.classList.toggle('open'));

  const modal = document.getElementById('userModal');
  const openModal = () => { modal.hidden = false; modal.querySelector('input').focus(); };
  const closeModal = () => { modal.hidden = true; };
  document.getElementById('quickAction').addEventListener('click', openModal);
  document.getElementById('manageUsers').addEventListener('click', openModal);
  document.querySelectorAll('.close-modal').forEach((button) => button.addEventListener('click', closeModal));
  modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });

  document.getElementById('userForm').addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    const createUserUrl = '../User%20%26%20Authentication%20Module/admin-create-user.php';
    fetch(createUserUrl, { credentials: 'same-origin', cache: 'no-store' })
    .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
    .then(({ ok, data }) => {
      if (!ok) throw new Error(data.message || 'Unable to verify the administrator session.');
      if (!data.csrfToken) throw new Error('Could not verify the administrator session. Refresh the page and try again.');

      return fetch(createUserUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        body: new URLSearchParams({
          full_name: formData.get('name'),
          email: formData.get('email'),
          password: formData.get('password'),
          csrf_token: data.csrfToken
        })
      });
    })
    .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
    .then(({ ok, data }) => {
        if (!ok) throw new Error(data.message || 'Unable to create the account.');
        closeModal();
        form.reset();
        showToast(`Manager account created for ${formData.get('name')}.`);
        loadDashboard();
      })
      .catch((error) => showToast(error.message))
      .finally(() => { submitButton.disabled = false; });
  });

  document.getElementById('exportButton').addEventListener('click', () => {
    if (!dashboardData) {
      showToast('Dashboard data is not ready to export.');
      return;
    }

    const rows = [
      ['Category', 'Name', 'Details', 'Status', 'Date', 'Value'],
      ['Summary', 'Active users', '', '', '', dashboardData.activeUsers ?? 'Not tracked'],
      ['Summary', 'Occupied units', `${dashboardData.units.occupied} of ${dashboardData.units.total}`, '', '', dashboardData.units.occupancyRate ?? 'Not available'],
      ['Summary', 'Active tenants', '', '', '', dashboardData.activeTenants],
      ['Summary', 'Rent collected', dashboardData.rentPeriod, '', '', dashboardData.rentCollected],
      ...dashboardData.users.map((user) => ['Team member', user.name, user.email, user.status, user.createdAt || '', user.role]),
      ...dashboardData.activity.map((item) => ['Recent activity', item.title, item.detail, '', item.date || '', ''])
    ];
    const csv = `\uFEFF${rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n')}`;
    const downloadUrl = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const downloadLink = document.createElement('a');
    downloadLink.href = downloadUrl;
    downloadLink.download = `mapesa-admin-report-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.append(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
    showToast('Dashboard report downloaded.');
  });
  let toastTimeout;
  function showToast(message) {
    const toast = document.getElementById('toast');
    toast.querySelector('span').textContent = message;
    toast.classList.add('visible');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => toast.classList.remove('visible'), 2800);
  }
});