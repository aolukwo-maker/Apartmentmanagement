const readings = [
  { unit: 'A-101', meter: 'W-101', utility: 'Water', previous: 117.4, current: 128.2, captured: 'Today, 09:42', status: 'Captured' },
  { unit: 'A-101', meter: 'E-101', utility: 'Electricity', previous: 8450, current: 8730, captured: 'Today, 09:42', status: 'Captured' },
  { unit: 'A-204', meter: 'W-204', utility: 'Water', previous: 98.1, current: 106.8, captured: 'Today, 09:18', status: 'Captured' },
  { unit: 'A-204', meter: 'E-204', utility: 'Electricity', previous: 7240, current: 7522, captured: 'Yesterday', status: 'Captured' },
  { unit: 'B-302', meter: 'W-302', utility: 'Water', previous: 141.8, current: 153.4, captured: 'Yesterday', status: 'Captured' },
  { unit: 'B-302', meter: 'E-302', utility: 'Electricity', previous: 9680, current: 10020, captured: 'Yesterday', status: 'Captured' },
  { unit: 'C-401', meter: 'W-401', utility: 'Water', previous: 82.6, current: 91.2, captured: '16 Sep', status: 'Captured' },
  { unit: 'C-401', meter: 'E-401', utility: 'Electricity', previous: 6120, current: 6398, captured: '16 Sep', status: 'Captured' },
  { unit: 'C-208', meter: 'W-208', utility: 'Water', previous: 76.5, current: 83.9, captured: '15 Sep', status: 'Captured' },
  { unit: 'C-208', meter: 'E-208', utility: 'Electricity', previous: 5010, current: 5274, captured: '15 Sep', status: 'Captured' },
  { unit: 'B-106', meter: 'W-106', utility: 'Water', previous: 112.3, current: 120.1, captured: '14 Sep', status: 'Captured' },
  { unit: 'B-106', meter: 'E-106', utility: 'Electricity', previous: 7860, current: 8145, captured: '14 Sep', status: 'Captured' }
];

const statements = [
  { id: 'ST-2026-091', unit: 'A-101', resident: 'Nadia Wanjiku', amount: 'KES 4,820', due: '30 Sep 2026', status: 'Paid' },
  { id: 'ST-2026-090', unit: 'A-204', resident: 'Brian Otieno', amount: 'KES 5,460', due: '30 Sep 2026', status: 'Pending' },
  { id: 'ST-2026-089', unit: 'B-302', resident: 'Mercy Njeri', amount: 'KES 6,120', due: '30 Sep 2026', status: 'Paid' },
  { id: 'ST-2026-088', unit: 'C-401', resident: 'David Kamau', amount: 'KES 7,840', due: '15 Sep 2026', status: 'Overdue' },
  { id: 'ST-2026-087', unit: 'C-208', resident: 'Amina Hassan', amount: 'KES 4,250', due: '30 Sep 2026', status: 'Paid' },
  { id: 'ST-2026-086', unit: 'B-106', resident: 'Sam Mwangi', amount: 'KES 5,080', due: '30 Sep 2026', status: 'Pending' }
];

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function consumption(reading) {
  return reading.utility === 'Water' ? (reading.current - reading.previous).toFixed(1) : Math.round(reading.current - reading.previous).toLocaleString();
}

function statusBadge(status) {
  return `<span class="status-badge ${status.toLowerCase()}">${status}</span>`;
}

function renderRows() {
  $('#recent-reading-rows').innerHTML = readings.slice(0, 5).map((reading) => `<tr><td><strong>${reading.unit}</strong></td><td>${reading.utility}</td><td>${reading.current.toLocaleString()} ${reading.utility === 'Water' ? 'm³' : 'kWh'}</td><td>${reading.captured}</td><td>${statusBadge(reading.status)}</td></tr>`).join('');
  const query = ($('#reading-search')?.value || '').toLowerCase();
  const utility = $('#utility-filter')?.value || 'all';
  const filtered = readings.filter((reading) => (reading.unit + reading.meter + reading.utility).toLowerCase().includes(query) && (utility === 'all' || reading.utility.toLowerCase() === utility));
  $('#reading-count').textContent = `${filtered.length} readings in the current cycle`;
  $('#all-reading-rows').innerHTML = filtered.map((reading) => `<tr><td><strong>${reading.unit}</strong></td><td>${reading.meter}</td><td>${reading.utility}</td><td>${reading.previous.toLocaleString()}</td><td>${reading.current.toLocaleString()}</td><td><strong>${consumption(reading)} ${reading.utility === 'Water' ? 'm³' : 'kWh'}</strong></td><td>${reading.captured}</td><td>${statusBadge(reading.status)}</td></tr>`).join('') || '<tr><td colspan="8">No readings match your search.</td></tr>';
}

function renderStatements() {
  const query = ($('#statement-search')?.value || '').toLowerCase();
  const filtered = statements.filter((statement) => Object.values(statement).join(' ').toLowerCase().includes(query));
  $('#statement-rows').innerHTML = filtered.map((statement) => `<tr><td><strong>${statement.id}</strong></td><td>${statement.unit}</td><td>${statement.resident}</td><td><strong>${statement.amount}</strong></td><td>${statement.due}</td><td>${statusBadge(statement.status)}</td><td><button class="row-action" data-statement="${statement.id}">View →</button></td></tr>`).join('');
}

function renderChart(type = 'combined') {
  const values = { water: [42, 47, 51, 57, 62, 68], power: [65, 70, 63, 77, 81, 86], combined: [56, 61, 59, 70, 74, 81] }[type];
  $('#chart-bars').innerHTML = values.map((value) => `<div class="bar-group"><span class="bar water-bar" style="height:${type === 'combined' ? value * .58 : type === 'water' ? value : 0}%"></span><span class="bar power-bar" style="height:${type === 'combined' ? value : type === 'power' ? value : 0}%"></span></div>`).join('');
  if (type !== 'combined') $('.chart-legend').style.opacity = '.72'; else $('.chart-legend').style.opacity = '1';
}

function showView(view) {
  $$('.nav-item').forEach((item) => item.classList.toggle('active', item.dataset.view === view));
  $$('.page-view').forEach((page) => page.classList.toggle('active', page.id === `${view}-view`));
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openModal() { $('#reading-modal').classList.add('open'); $('#reading-modal').setAttribute('aria-hidden', 'false'); $('#reading-input').focus(); }
function closeModal() { $('#reading-modal').classList.remove('open'); $('#reading-modal').setAttribute('aria-hidden', 'true'); $('#reading-form').reset(); }
function toast(message) { const element = $('#toast'); element.textContent = message; element.classList.add('show'); setTimeout(() => element.classList.remove('show'), 2600); }

$$('.nav-item').forEach((item) => item.addEventListener('click', () => showView(item.dataset.view)));
$$('[data-view-target]').forEach((item) => item.addEventListener('click', () => showView(item.dataset.viewTarget)));
$('#open-reading').addEventListener('click', openModal);
$('#open-reading-2').addEventListener('click', openModal);
$('#close-modal').addEventListener('click', closeModal);
$('#cancel-modal').addEventListener('click', closeModal);
$('#reading-modal').addEventListener('click', (event) => { if (event.target.id === 'reading-modal') closeModal(); });
$$('[data-chart]').forEach((button) => button.addEventListener('click', () => { $$('[data-chart]').forEach((item) => item.classList.remove('active')); button.classList.add('active'); renderChart(button.dataset.chart); }));
$('#reading-search').addEventListener('input', renderRows);
$('#utility-filter').addEventListener('change', renderRows);
$('#statement-search').addEventListener('input', renderStatements);
$('#export-readings').addEventListener('click', () => toast('Reading log exported as CSV'));
$('#print-statements').addEventListener('click', () => window.print());
$('#generate-statement').addEventListener('click', () => toast('Statement generation queued for all active units'));

document.addEventListener('click', (event) => {
  const statementButton = event.target.closest('[data-statement]');
  if (statementButton) toast(`${statementButton.dataset.statement} opened for review`);
});

$('#reading-form').addEventListener('submit', (event) => {
  event.preventDefault();
  const unit = $('#unit-input').value;
  const utility = $('#utility-input').value;
  const current = Number($('#reading-input').value);
  const existing = readings.find((reading) => reading.unit === unit && reading.utility === utility);
  const previous = existing ? existing.current : 0;
  readings.unshift({ unit, meter: `${utility === 'Water' ? 'W' : 'E'}-${unit.replace('-', '')}`, utility, previous, current, captured: 'Just now', status: 'Captured' });
  if (utility === 'Water') $('#water-total').innerHTML = '18,430 <small>m³</small>';
  if (utility === 'Electricity') $('#power-total').innerHTML = '42,870 <small>kWh</small>';
  renderRows();
  closeModal();
  toast(`${utility} reading saved for ${unit}`);
});

renderRows();
renderStatements();
renderChart();
