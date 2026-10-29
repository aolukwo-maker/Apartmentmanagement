const ledgerEndpoint = 'index.php?api=1';
const pageSize = 10;
let currentPage = 1;
let refreshPromise = null;
let ledgerLoadError = null;
let data = {
  ledgers: [],
  paymentOptions: [],
  summary: {
    collectedThisMonth: 0, collectedLastMonth: 0, totalCharged: 0,
    totalPaid: 0, outstanding: 0, overdue: 0, openCharges: 0,
    overdueCharges: 0, propertyCount: 0, occupiedUnits: 0,
    today: '', monthStart: '', refreshedAt: ''
  }
};
const money = value => `KES ${Number(value).toLocaleString('en-US')}`;
const formatDate = value => new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
const formatDateTime = value => new Date(value.replace(' ', 'T')).toLocaleString('en-GB', {
  day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
});
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
})[character]);

function updateStatus(ledger) {
  if (ledger.status !== 'none') {
    ledger.status = ledger.balance <= 0
      ? 'paid'
      : (ledger.status === 'overdue' ? 'overdue' : (ledger.paid > 0 ? 'partial' : 'due'));
  }
}

function render() {
  data.ledgers.forEach(updateStatus);
  const summary = data.summary;
  const collectionRate = summary.totalCharged
    ? Math.min(100, Math.round(summary.totalPaid / summary.totalCharged * 100))
    : 0;
  const trend = summary.collectedLastMonth > 0
    ? Math.round((summary.collectedThisMonth - summary.collectedLastMonth) / summary.collectedLastMonth * 100)
    : (summary.collectedThisMonth > 0 ? null : 0);
  const trendElement = document.querySelector('#collectionTrend');

  document.querySelector('#collectedTotal').textContent = money(summary.collectedThisMonth);
  document.querySelector('#outstandingTotal').textContent = money(summary.outstanding);
  document.querySelector('#overdueTotal').textContent = money(summary.overdue);
  document.querySelector('#collectionRate').textContent = `${collectionRate}%`;
  document.querySelector('#rateBar').style.width = `${Math.min(collectionRate, 100)}%`;
  document.querySelector('#openInvoices').textContent = summary.openCharges;
  document.querySelector('#overdueUnits').textContent = `${summary.overdueCharges} charges`;
  trendElement.textContent = trend === null ? 'New' : `${trend > 0 ? '+' : ''}${trend}%`;
  trendElement.parentElement.classList.toggle('positive', trend === null || trend >= 0);
  trendElement.parentElement.classList.toggle('negative', trend !== null && trend < 0);
  const propertyLabel = `${summary.propertyCount} ${summary.propertyCount === 1 ? 'property' : 'properties'}`;
  document.querySelector('#propertyName').textContent = 'All properties';
  document.querySelector('#breadcrumbProperty').textContent = 'All properties';
  document.querySelector('#propertySummary').textContent =
    `${propertyLabel} - ${summary.occupiedUnits} occupied ${summary.occupiedUnits === 1 ? 'unit' : 'units'}`;
  document.querySelector('#periodLabel').textContent =
    `FINANCE / ${new Date(`${summary.monthStart}T00:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }).toUpperCase()}`;
  document.querySelector('#dataUpdated').textContent = `Database updated ${formatDateTime(summary.refreshedAt)}`;
  currentPage = Math.min(currentPage, Math.max(1, Math.ceil(getFilteredRows().length / pageSize)));
  renderTable();
  renderStatusBreakdown();
  populateLedgerSelect();
}

function getFilteredRows() {
  const query = document.querySelector('#searchInput').value.toLowerCase();
  const status = document.querySelector('#statusFilter').value;
  return data.ledgers.filter(row =>
    (row.tenant.toLowerCase().includes(query) || row.unit.toLowerCase().includes(query))
    && (status === 'all' || row.status === status)
  );
}

function renderTable() {
  const rows = getFilteredRows();
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
  currentPage = Math.min(currentPage, pageCount);
  const firstIndex = (currentPage - 1) * pageSize;
  const pageRows = rows.slice(firstIndex, firstIndex + pageSize);
  document.querySelector('#ledgerBody').innerHTML = pageRows.map(row => `<tr>
    <td><div class="tenant-cell"><span class="tenant-avatar">${escapeHtml(row.initials)}</span>
      <div class="tenant-name"><strong>${escapeHtml(row.tenant)}</strong><span>Unit ${escapeHtml(row.unit)}</span></div></div></td>
    <td>${escapeHtml(row.period || 'No rent charge')}</td>
    <td class="amount">${row.status === 'none' ? '—' : money(row.due)}</td>
    <td class="amount">${row.status === 'none' ? '—' : money(row.paid)}</td>
    <td class="balance ${row.status === 'overdue' ? 'overdue-text' : ''}">${row.status === 'none' ? '—' : money(row.balance)}</td>
    <td><span class="status ${escapeHtml(row.status)}">${row.status === 'none' ? 'No charge' : escapeHtml(row.status[0].toUpperCase() + row.status.slice(1))}</span></td>
    <td>${row.status === 'none' ? '' : `<button class="row-menu" data-receipt="${escapeHtml(row.id)}" title="Generate receipt">•••</button>`}</td>
  </tr>`).join('');
  document.querySelector('#emptyState').hidden = rows.length > 0;
  document.querySelector('#emptyState').textContent = data.ledgers.length
    ? 'No ledger entries match your filters.'
    : 'No rent charges are available for registered tenants.';
  document.querySelector('#recordCount').textContent = rows.length
    ? `Showing ${firstIndex + 1}-${Math.min(firstIndex + pageSize, rows.length)} of ${rows.length} filtered records (${data.ledgers.length} total)`
    : `Showing 0 of ${data.ledgers.length} records`;
  document.querySelector('#pageInfo').textContent = `${currentPage} / ${pageCount}`;
  document.querySelector('#previousPage').disabled = currentPage <= 1;
  document.querySelector('#nextPage').disabled = currentPage >= pageCount;
  document.querySelectorAll('[data-receipt]').forEach(button => {
    button.addEventListener('click', () => generateReceipt(button.dataset.receipt));
  });
}

function renderStatusBreakdown() {
  const counts = { paid: 0, partial: 0, due: 0, overdue: 0, none: 0 };
  data.ledgers.forEach(row => counts[row.status]++);
  const total = data.ledgers.length || 1;
  const labels = { paid: 'Paid', partial: 'Partial', due: 'Due', overdue: 'Overdue', none: 'No charge' };
  document.querySelector('#methodList').innerHTML = Object.entries(counts).map(([status, count]) => `
    <div class="method-row"><div class="method-meta"><span>${labels[status]}</span><strong>${count}</strong></div>
      <div class="method-bar"><span style="width:${count / total * 100}%"></span></div></div>`).join('');
}

function populateLedgerSelect() {
  const select = document.querySelector('#ledgerSelect');
  const options = data.paymentOptions || [];
  const selectedTarget = select.value;
  select.innerHTML = options.map(option =>
    `<option value="${escapeHtml(option.target)}" data-max-amount="${escapeHtml(option.maxAmount)}">${escapeHtml(option.tenantName)} · ${escapeHtml(option.details)}</option>`
  ).join('');
  select.disabled = options.length === 0;
  if (options.some(option => option.target === selectedTarget)) {
    select.value = selectedTarget;
  }
  updatePaymentAmountLimit();
}

function updatePaymentAmountLimit() {
  const select = document.querySelector('#ledgerSelect');
  const amount = document.querySelector('[name=amount]');
  const option = select.selectedOptions[0];
  const maxAmount = option ? Number(option.dataset.maxAmount) : 0;
  amount.max = maxAmount > 0 ? String(maxAmount) : '';
  amount.placeholder = maxAmount > 0 ? `Up to ${maxAmount}` : '0';
}

function showToast(message) {
  const toast = document.querySelector('#toast');
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2800);
}

async function readApiResponse(response) {
  try {
    return JSON.parse(await response.text());
  } catch {
    throw new Error(`The server returned an invalid response (HTTP ${response.status}). Check the PHP error log.`);
  }
}

function openModal() {
  if (!data.paymentOptions.length) {
    showToast(ledgerLoadError
      ? `Tenant rent data could not be loaded: ${ledgerLoadError}`
      : 'No eligible rent charges or active tenant rent amounts are available.');
    return;
  }
  document.querySelector('#modalBackdrop').hidden = false;
  document.querySelector('[name=date]').value = data.summary.today;
  updatePaymentAmountLimit();
  document.querySelector('[name=amount]').focus();
}

function closeModal() {
  document.querySelector('#modalBackdrop').hidden = true;
  document.querySelector('#transactionForm').reset();
}

function generateReceipt(ledgerId) {
  const ledger = data.ledgers.find(row => String(row.id) === String(ledgerId));
  if (!ledger?.latestPaymentAmount) {
    showToast('No confirmed payment receipt is available for this charge.');
    return;
  }
  const receipt = `MAPESA APARTMENTS\nPAYMENT RECEIPT\n------------------------------\nTenant: ${ledger.tenant}\nUnit: ${ledger.unit}\nPeriod: ${ledger.period}\nAmount paid: ${money(ledger.latestPaymentAmount)}\nReference: ${ledger.latestPaymentReference || 'N/A'}\nDate: ${formatDate(ledger.latestPaymentDate)}\n------------------------------\nThank you for your payment.`;
  const printWindow = window.open('', '_blank', 'width=420,height=620');
  if (printWindow) {
    printWindow.document.write(`<pre style="font:14px/1.7 monospace; padding:30px; white-space:pre-wrap">${escapeHtml(receipt)}</pre>`);
    printWindow.document.close();
    printWindow.print();
  }
}

async function loadLedger(forceRefresh = false) {
  if (refreshPromise) {
    if (!forceRefresh) return refreshPromise;
    await refreshPromise.catch(() => {});
    return loadLedger();
  }
  const updated = document.querySelector('#dataUpdated');
  updated.classList.remove('error');
  if (data.summary.refreshedAt) updated.textContent = 'Refreshing live database data...';
  const request = (async () => {
    const response = await fetch(ledgerEndpoint, {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const payload = await readApiResponse(response);
    if (!response.ok) {
      throw new Error(payload.error || 'Rent ledger data could not be loaded.');
    }
    if (!payload.summary || !Array.isArray(payload.ledgers)
        || !Array.isArray(payload.paymentOptions)) {
      throw new Error('The rent ledger response is incomplete.');
    }
    data = payload;
    ledgerLoadError = null;
    render();
  })();
  refreshPromise = request;
  try {
    await request;
  } catch (error) {
    ledgerLoadError = error.message;
    updated.textContent = 'Live database refresh failed';
    updated.classList.add('error');
    throw error;
  } finally {
    if (refreshPromise === request) refreshPromise = null;
  }
}

document.querySelector('#recordPaymentBtn').addEventListener('click', openModal);
document.querySelector('#closeModal').addEventListener('click', closeModal);
document.querySelector('#modalBackdrop').addEventListener('click', event => {
  if (event.target.id === 'modalBackdrop') closeModal();
});
document.querySelector('#statusFilter').addEventListener('change', () => {
  currentPage = 1;
  renderTable();
});
document.querySelector('#previousPage').addEventListener('click', () => {
  if (currentPage > 1) {
    currentPage--;
    renderTable();
  }
});
document.querySelector('#nextPage').addEventListener('click', () => {
  currentPage++;
  renderTable();
});
document.querySelector('#ledgerSelect').addEventListener('change', updatePaymentAmountLimit);
document.querySelector('#searchInput').addEventListener('input', () => {
  currentPage = 1;
  renderTable();
});
document.querySelector('#transactionForm').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitButton = form.querySelector('[type="submit"]');
  submitButton.disabled = true;
  try {
    const response = await fetch(ledgerEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      body: new FormData(form)
    });
    const payload = await readApiResponse(response);
    if (!response.ok) {
      throw new Error(payload.error || 'Payment could not be recorded.');
    }
    closeModal();
    try {
      await loadLedger(true);
      showToast(payload.chargeCreated
        ? 'Current-month rent charge created and payment recorded.'
        : 'Payment recorded and confirmed.');
    } catch (error) {
      showToast(`Payment was saved, but the rent ledger could not be refreshed: ${error.message}`);
    }
  } catch (error) {
    showToast(error.message);
  } finally {
    submitButton.disabled = false;
  }
});
document.querySelector('#exportBtn').addEventListener('click', () => {
  const headers = ['Tenant','Unit','Period','Amount due','Paid','Balance','Status'];
  const csvCell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
  const rows = data.ledgers.map(row => [
    row.tenant, row.unit, row.period, row.due, row.paid, row.balance, row.status
  ]);
  const csv = [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\n');
  const link = document.createElement('a');
  link.href = URL.createObjectURL(new Blob([csv], { type:'text/csv' }));
  link.download = 'mapesa-rent-ledger.csv';
  link.click();
  URL.revokeObjectURL(link.href);
  showToast('Ledger exported as CSV');
});

async function refreshLedger() {
  try {
    await loadLedger();
  } catch (error) {
    if (!data.summary.refreshedAt) {
      document.querySelector('#ledgerBody').innerHTML = '';
      document.querySelector('#emptyState').hidden = false;
      document.querySelector('#emptyState').textContent = error.message;
      document.querySelector('#recordCount').textContent = 'Tenant rent data unavailable';
      showToast(error.message);
    }
  }
}

refreshLedger();
window.setInterval(() => {
  if (!document.hidden) refreshLedger();
}, 30000);
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) refreshLedger();
});
