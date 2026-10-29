const outstanding = [
  { unit: 'Unit 6A', tenant: 'Brian Otieno', due: '05 Sep 2026', amount: 42000, days: 12, status: 'Overdue' },
  { unit: 'Unit 2C', tenant: 'Njeri Wambui', due: '05 Sep 2026', amount: 38500, days: 12, status: 'Overdue' },
  { unit: 'Unit 5A', tenant: 'David Kamau', due: '10 Sep 2026', amount: 40000, days: 7, status: 'Reminder sent' },
  { unit: 'Unit 1B', tenant: 'Amina Hassan', due: '15 Sep 2026', amount: 35000, days: 2, status: 'Reminder sent' }
];
let transactions = [];
let transactionLoadError = null;
let transactionLoading = true;
let paymentOptions = [];
let paymentOptionsError = null;
let paymentOptionsLoading = true;
let properties = [];
let propertiesError = null;
let activeView = 'dashboard';
const money = value => `KES ${Number(value).toLocaleString('en-KE')}`;
const formatDate = value => new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const totals = () => transactions.reduce((acc, item) => { acc[item.type] += item.amount; return acc; }, { income: 0, expense: 0 });
const pageLabel = { dashboard: 'Overview', transactions: 'Transactions', outstanding: 'Outstanding payments', statements: 'Financial statements' };

function render() {
  document.querySelector('#page-label').textContent = pageLabel[activeView];
  document.querySelectorAll('.nav-item').forEach(item => item.classList.toggle('active', item.dataset.view === activeView));
  document.querySelector('#app-view').innerHTML = views[activeView]();
  bindViewEvents();
}
function layout(title, description, action = true) { return `<div class="page"><div class="page-heading"><div><span class="eyebrow">FINANCIAL MANAGEMENT</span><h1>${title}</h1><p>${description}</p></div>${action ? '<button class="button primary" id="open-modal">＋ Record transaction</button>' : ''}</div>`; }
function dashboardView() {
  const total = totals(); const net = total.income - total.expense;
  const recent = [...transactions].sort((a,b) => b.date.localeCompare(a.date)).slice(0, 5);
  return `${layout('Good morning, Manager', 'Here is the financial pulse of Mapesa Apartments for September 2026.')}
  <div class="metric-grid"><div class="metric-card"><span class="metric-icon">↗</span><span class="metric-label">TOTAL INCOME</span><div class="metric-value">${money(total.income)}</div><span class="metric-note up">↑ 8.4% <span>vs last month</span></span></div><div class="metric-card"><span class="metric-icon">↘</span><span class="metric-label">TOTAL EXPENSES</span><div class="metric-value">${money(total.expense)}</div><span class="metric-note">↓ 3.1% <span>vs last month</span></span></div><div class="metric-card"><span class="metric-icon">◇</span><span class="metric-label">NET OPERATING INCOME</span><div class="metric-value">${money(net)}</div><span class="metric-note up">Healthy cash position</span></div><div class="metric-card"><span class="metric-icon">◷</span><span class="metric-label">OUTSTANDING PAYMENTS</span><div class="metric-value">${money(outstanding.reduce((s,x)=>s+x.amount,0))}</div><span class="metric-note alert">${outstanding.length} tenants need attention</span></div></div>
  <div class="dashboard-grid"><section class="panel"><div class="panel-header"><div><h2>Cash flow this month</h2><p>Income and expenses by week</p></div><button class="text-link" data-view-link="statements">View statement →</button></div>${cashFlowChart()}<div class="legend"><span><i></i>Income</span><span><i class="orange"></i>Expenses</span></div></section><section class="panel"><div class="panel-header"><div><h2>Income breakdown</h2><p>September revenue mix</p></div><button class="text-link" data-view-link="transactions">Details →</button></div><div class="donut-wrap"><div class="donut"></div><div class="breakdown"><div class="breakdown-row"><i></i><span>Rent revenue</span><b>71%</b></div><div class="breakdown-row"><i></i><span>Service charges</span><b>16%</b></div><div class="breakdown-row"><i></i><span>Late fees</span><b>9%</b></div><div class="breakdown-row"><i></i><span>Other income</span><b>4%</b></div></div></div></section></div>
  <section class="panel table-panel"><div class="panel-header"><div><h2>Recent activity</h2><p>Latest entries in your ledger</p></div><button class="text-link" data-view-link="transactions">All transactions →</button></div>${transactionTable(recent)}</section></div>`;
}
function cashFlowChart() { const points = [{i:52,e:15,l:'W1'},{i:77,e:29,l:'W2'},{i:63,e:23,l:'W3'},{i:90,e:38,l:'W4'},{i:70,e:25,l:'W5'}]; return `<div class="chart"><div class="chart-y"><span>100k</span><span>75k</span><span>50k</span><span>25k</span><span>0</span></div><div class="chart-area">${points.map((p,i)=>`<div class="chart-column ${i===4?'current':''}"><div class="bar" style="height:${p.i}%"></div><div class="bar expense" style="height:${p.e}%"></div></div>`).join('')}</div></div><div class="chart-labels">${points.map(p=>`<span>${p.l}</span>`).join('')}</div>`; }
function transactionTable(rows) { if (!rows.length) return `<div class="empty-state"${transactionLoadError ? ' role="alert"' : ''}>${transactionLoading ? 'Loading database transactions…' : transactionLoadError ? escapeHtml(transactionLoadError) : 'No transactions match your filters.'}</div>`; return `<div class="table-wrap"><table><thead><tr><th>DESCRIPTION</th><th>PROPERTY</th><th>DATE</th><th>CATEGORY</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>${rows.map(item=>`<tr><td><strong>${escapeHtml(item.description)}</strong></td><td>${escapeHtml(item.property || 'Unassigned')}</td><td>${escapeHtml(formatDate(item.date))}</td><td><span class="tag ${item.type==='expense'?'warning':''}">${escapeHtml(item.category)}</span></td><td class="amount ${item.type}">${item.type==='expense'?'−':'+'}${money(item.amount)}</td><td><span class="status">● ${escapeHtml(item.status)}</span></td></tr>`).join('')}</tbody></table></div>`; }
function transactionsView() { return `${layout('Transactions', 'A complete record of income and expenses across the property.')}
<section class="panel"><div class="view-toolbar"><input class="search" id="transaction-search" placeholder="Search transactions..."/><select class="filter-select" id="transaction-filter"><option value="all">All types</option><option value="income">Income only</option><option value="expense">Expenses only</option></select><div class="toolbar-spacer"></div><span class="metric-note">${transactions.length} entries</span></div><div id="transactions-table">${transactionTable(transactions)}</div></section></div>`; }
function outstandingView() { const total = outstanding.reduce((s,x)=>s+x.amount,0); return `${layout('Outstanding payments', 'Follow up on rent and other balances that still need to be collected.', false)}<div class="outstanding-grid"><div class="panel"><div class="panel-header"><div><h2>Payment follow-up</h2><p>${outstanding.length} accounts require attention</p></div><button class="button ghost" id="export-outstanding">Export list</button></div><div class="table-wrap"><table><thead><tr><th>UNIT / TENANT</th><th>DUE DATE</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>${outstanding.map(item=>`<tr><td><strong>${item.unit}</strong><br><span class="metric-note">${item.tenant}</span></td><td>${item.due}<br><span class="metric-note">${item.days} days late</span></td><td class="amount expense">${money(item.amount)}</td><td><span class="tag warning">${item.status}</span></td></tr>`).join('')}</tbody></table></div></div><div><div class="total-callout"><span class="eyebrow">TOTAL OUTSTANDING</span><strong>${money(total)}</strong><p>Expected rent collection for September</p></div><section class="panel"><div class="panel-header"><div><h2>Collection health</h2><p>Rent collection progress</p></div></div><div class="summary-box"><small>COLLECTED THIS MONTH</small><strong>86.4%</strong></div><div class="summary-box"><small>ON-TIME PAYMENTS</small><strong>74 / 82</strong></div></section></div></div></div>`; }
function statementsView() { const total = totals(); return `${layout('Financial statements', 'Generate a clear monthly view for owners, auditors, and management.', false)}<section class="panel"><div class="statement-header"><div><span class="eyebrow">MAPESA APARTMENTS</span><h2>Income statement</h2><p>For the month ended 30 September 2026</p></div><button class="button primary" id="print-statement">Print statement <span>↗</span></button></div><div class="summary-row"><div class="summary-box"><small>GROSS INCOME</small><strong>${money(total.income)}</strong></div><div class="summary-box"><small>TOTAL EXPENSES</small><strong>${money(total.expense)}</strong></div><div class="summary-box"><small>NET OPERATING INCOME</small><strong>${money(total.income-total.expense)}</strong></div></div><table><thead><tr><th>LINE ITEM</th><th>DETAIL</th><th style="text-align:right">AMOUNT (KES)</th></tr></thead><tbody><tr><td><strong>Income</strong></td><td>Rent revenue</td><td class="amount income" style="text-align:right">${total.income.toLocaleString()}</td></tr><tr><td></td><td>Other income</td><td class="amount income" style="text-align:right">0</td></tr><tr><td><strong>Expenses</strong></td><td>Maintenance and repairs</td><td class="amount expense" style="text-align:right">${transactions.filter(x=>x.category==='Maintenance').reduce((s,x)=>s+x.amount,0).toLocaleString()}</td></tr><tr><td></td><td>Utilities and operations</td><td class="amount expense" style="text-align:right">${transactions.filter(x=>x.type==='expense'&&x.category!=='Maintenance').reduce((s,x)=>s+x.amount,0).toLocaleString()}</td></tr><tr><td><strong>Net operating income</strong></td><td></td><td class="amount" style="text-align:right"><strong>${(total.income-total.expense).toLocaleString()}</strong></td></tr></tbody></table></section></div>`; }
const views = { dashboard: dashboardView, transactions: transactionsView, outstanding: outstandingView, statements: statementsView };
function openModal() {
  const form = document.querySelector('#transaction-form');
  document.querySelector('#transaction-modal').hidden = false;
  document.querySelector('#transaction-form-message').hidden = true;
  const today = new Date();
  form.elements.date.value = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0')
  ].join('-');
  updateTransactionForm();
}
function closeModal() { document.querySelector('#transaction-modal').hidden = true; }
function updateTransactionForm() {
  const form = document.querySelector('#transaction-form');
  const isExpense = form.elements.type.value === 'expense';
  form.classList.toggle('expense-mode', isExpense);
  const propertySelect = form.elements.propertyId;
  const selectedPropertyId = propertySelect.value;
  const setField = (id, name, visible, required = false) => {
    document.querySelector(id).hidden = !visible;
    const field = form.elements[name];
    field.disabled = !visible;
    field.required = required;
  };
  setField('#income-charge-field', 'ledgerId', !isExpense, !isExpense);
  setField('#expense-description-field', 'description', isExpense, isExpense);
  setField('#expense-category-field', 'category', isExpense, isExpense);
  setField('#income-reference-field', 'reference', !isExpense);
  const chargeSelect = form.elements.ledgerId;
  const propertyCharges = paymentOptions.filter(item => String(item.propertyId) === selectedPropertyId);
  chargeSelect.disabled = isExpense || propertyCharges.length === 0;
  chargeSelect.required = !isExpense;
  document.querySelector('#modal-title').textContent = isExpense ? 'Record an expense' : 'Record rent income';
  document.querySelector('#transaction-intro').textContent = isExpense
    ? 'Add a property cost to the expenses table. The saved entry will appear in your financial transactions.'
    : 'Record rent received against an open tenant charge. This creates a payment in the rent payments table.';
  document.querySelector('#save-transaction-label').textContent = isExpense ? 'Save expense' : 'Save rent payment';
  document.querySelector('#amount-label').textContent = isExpense ? 'Amount paid (KES)' : 'Amount received (KES)';
  document.querySelector('#date-label').textContent = isExpense ? 'Expense date' : 'Payment date';
  document.querySelector('#transaction-type-help').textContent = isExpense
    ? 'Expenses are saved as expense records and included in the recent transactions list.'
    : paymentOptionsLoading
      ? 'Loading open rent charges. You can switch to expense while charges load.'
      : properties.length === 0
        ? propertiesError || 'Load a property before recording rent income.'
        : propertyCharges.length
          ? 'Select the tenant charge receiving this payment. The payment cannot exceed its remaining balance.'
          : paymentOptionsLoading
            ? 'Loading open rent charges.'
            : selectedPropertyId
              ? `There are no unpaid rent charges available for ${propertySelect.selectedOptions[0]?.textContent || 'this property'}.`
              : 'Select a property to see its rent charges.';
  if (!isExpense && propertyCharges.length === 0) {
      const optionText = properties.length === 0
        ? propertiesError || 'No properties available'
        : !selectedPropertyId
          ? 'Select a property first'
          : paymentOptionsLoading
        ? 'Loading unpaid rent charges…'
          : 'No unpaid rent charges for this property';
      chargeSelect.innerHTML = `<option value="">${optionText}</option>`;
  } else if (!isExpense) {
      populatePaymentOptions(propertyCharges);
  }
  const selectedCharge = paymentOptions.find(item => item.target === chargeSelect.value);
  const amountInput = form.elements.amount;
  amountInput.max = !isExpense && selectedCharge ? String(selectedCharge.maxAmount) : '';
  document.querySelector('#amount-help').textContent = isExpense
    ? 'Enter the amount paid. Use up to two decimal places.'
    : selectedCharge
      ? `Maximum for this charge: ${money(selectedCharge.maxAmount)}.`
      : 'Enter an amount received. The maximum is the selected charge balance.';
  const saveButton = form.querySelector('[type="submit"]');
  if (!saveButton.dataset.submitting) {
    saveButton.disabled = properties.length === 0 || (!isExpense && propertyCharges.length === 0);
  }
}
function populatePaymentOptions(options = paymentOptions) {
  const form = document.querySelector('#transaction-form');
  const select = form.elements.ledgerId;
  const selectedTarget = select.value;
  select.replaceChildren(new Option('Select a rent charge', ''));
  options.forEach(item => {
    select.add(new Option(item.details, item.target));
  });
  if (options.some(item => item.target === selectedTarget)) select.value = selectedTarget;
  select.disabled = options.length === 0;
}
function populateProperties() {
  const select = document.querySelector('#transaction-form').elements.propertyId;
  const selectedId = select.value;
  select.replaceChildren(new Option(
    properties.length ? 'Select a property' : (propertiesError || 'No properties available'),
    ''
  ));
  properties.forEach(property => {
    select.add(new Option(property.name, String(property.id)));
  });
  if (properties.some(property => String(property.id) === selectedId)) {
    select.value = selectedId;
  }
  select.disabled = properties.length === 0;
}
function bindViewEvents() { document.querySelector('#open-modal')?.addEventListener('click', openModal); document.querySelectorAll('[data-view-link]').forEach(el=>el.addEventListener('click',()=>{activeView=el.dataset.viewLink;render()})); document.querySelector('#print-statement')?.addEventListener('click',()=>window.print()); document.querySelector('#export-outstanding')?.addEventListener('click',()=>alert('Outstanding payment list is ready to export.')); const search=document.querySelector('#transaction-search'); const filter=document.querySelector('#transaction-filter'); const update=()=>{const q=(search?.value||'').toLowerCase();const type=filter?.value||'all';document.querySelector('#transactions-table').innerHTML=transactionTable(transactions.filter(x=>(type==='all'||x.type===type)&&`${x.description} ${x.category}`.toLowerCase().includes(q)))}; search?.addEventListener('input',update);filter?.addEventListener('change',update); }
document.querySelectorAll('.nav-item').forEach(item=>item.addEventListener('click',()=>{activeView=item.dataset.view;render()}));
document.querySelector('#close-modal').addEventListener('click',closeModal); document.querySelector('#cancel-modal').addEventListener('click',closeModal); document.querySelector('#transaction-modal').addEventListener('click',e=>{if(e.target.id==='transaction-modal')closeModal()});
document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', updateTransactionForm));
document.querySelector('#transaction-form').elements.ledgerId.addEventListener('change', updateTransactionForm);
document.querySelector('#transaction-form').elements.propertyId.addEventListener('change', updateTransactionForm);
document.querySelector('#transaction-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const submitButton = form.querySelector('[type="submit"]');
  const message = document.querySelector('#transaction-form-message');
  if (window.location.protocol === 'file:') {
    message.textContent = 'Database recording requires XAMPP Apache. Open this page through http://localhost/Mapesa%20Apartment%20Management%20System/management%20module/Financial%20Management%20Module/index.html, then sign in and retry.';
    message.hidden = false;
    return;
  }
  const transactionType = form.elements.type.value;
  const endpoint = transactionType === 'income'
    ? '../Rent%20%26%20Payment%20Module/index.php?api=1'
    : '../Management%20API/finance-transactions.php';
  submitButton.dataset.submitting = 'true';
  submitButton.disabled = true;
  message.hidden = true;
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      body: new FormData(form)
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'The transaction could not be saved.');

    form.reset();
    closeModal();
    const results = await Promise.all([loadTransactions(), loadPaymentOptions()]);
    if (!results[0] || !results[1]) {
      window.alert('The transaction was saved, but some database records could not be refreshed. Reload the page to try again.');
    }
  } catch (error) {
    message.textContent = error instanceof Error ? error.message : 'The transaction could not be saved.';
    message.hidden = false;
  } finally {
    delete submitButton.dataset.submitting;
    updateTransactionForm();
  }
});
async function loadTransactions() {
  if (window.location.protocol === 'file:') {
    transactionLoading = false;
    transactionLoadError = 'Database transactions are unavailable in the local file preview. Open this page through XAMPP Apache to view and record database entries.';
    render();
    return false;
  }
  try {
    const response = await fetch('../Management%20API/finance-transactions.php', {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Unable to load financial transactions.');
    if (!Array.isArray(payload.transactions)) throw new Error('The database response is missing transactions.');
    if (!Array.isArray(payload.properties)) throw new Error('The database response is missing properties.');
    properties = payload.properties.map(property => {
      const id = Number(property?.id);
      if (!Number.isInteger(id) || id < 1 || typeof property.name !== 'string') {
        throw new Error('The database returned an invalid property record.');
      }
      return { id, name: property.name };
    });
    propertiesError = null;
    transactions = payload.transactions.map(item => {
      if (!item || !['income', 'expense'].includes(item.type)
          || !Number.isFinite(Number(item.amount)) || !/^\d{4}-\d{2}-\d{2}$/.test(item.date)) {
        throw new Error('The database returned an invalid transaction record.');
      }
      return {
        ...item,
        amount: Number(item.amount),
        date: item.date
      };
    });
    transactionLoadError = null;
  } catch (error) {
    transactionLoadError = error instanceof Error
      ? `Could not load database transactions: ${error.message}`
      : 'Could not load database transactions.';
    properties = [];
    propertiesError = transactionLoadError;
  } finally {
    transactionLoading = false;
    populateProperties();
  }
  render();
  return !transactionLoadError;
}
async function loadPaymentOptions() {
  let loaded = false;
  if (window.location.protocol === 'file:') {
    paymentOptions = [];
    paymentOptionsError = 'Database recording requires XAMPP Apache.';
    paymentOptionsLoading = false;
    populatePaymentOptions();
    updateTransactionForm();
    return false;
  }
  try {
    const response = await fetch('../Rent%20%26%20Payment%20Module/index.php?api=1', {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Unable to load rent charges.');
    if (!Array.isArray(payload.paymentOptions)) throw new Error('The rent payment response is missing payment options.');
    paymentOptions = payload.paymentOptions.map(item => {
      const maxAmount = Number(item?.maxAmount);
      const propertyId = Number(item?.propertyId);
      if (!item || typeof item.target !== 'string' || typeof item.details !== 'string'
          || !Number.isFinite(maxAmount) || maxAmount <= 0
          || !Number.isInteger(propertyId) || propertyId < 1) {
        throw new Error('The rent charge response contains an invalid payment option.');
      }
      return { target: item.target, details: item.details, maxAmount, propertyId };
    });
    paymentOptionsError = paymentOptions.length ? null : 'No unpaid rent charges available';
    loaded = true;
  } catch (error) {
    paymentOptions = [];
    paymentOptionsError = error instanceof Error
      ? `Could not load rent charges: ${error.message}`
      : 'Could not load rent charges.';
  } finally {
    paymentOptionsLoading = false;
  }
  populatePaymentOptions();
  updateTransactionForm();
  return loaded;
}
render();
Promise.all([loadTransactions(), loadPaymentOptions()]);
