const data = {
  properties: {
    all: { units: 120, occupied: 113, collected: 4860000, outstanding: 782000, openMaintenance: 18 },
    palm: { units: 48, occupied: 46, collected: 1980000, outstanding: 226000, openMaintenance: 6 },
    ridge: { units: 40, occupied: 38, collected: 1560000, outstanding: 318000, openMaintenance: 7 },
    terrace: { units: 32, occupied: 29, collected: 1320000, outstanding: 238000, openMaintenance: 5 }
  },
  occupancy: [
    ['Palm Court', 96, 48], ['Riverside Ridge', 95, 40], ['Maple Terrace', 91, 32], ['Total portfolio', 94, 120]
  ],
  collection: [
    ['Palm Court Apartments', 'KES 2.16M', 'KES 1.98M', '92%', 'On track'], ['Riverside Ridge', 'KES 1.72M', 'KES 1.56M', '91%', 'On track'], ['Maple Terrace', 'KES 1.42M', 'KES 1.32M', '93%', 'On track']
  ],
  outstanding: [
    ['John Kamau', 'Palm Court · A-104', 'KES 86,000', '18 days', 'Overdue'], ['Njeri Wambui', 'Riverside Ridge · B-207', 'KES 74,000', '12 days', 'Overdue'], ['Brian Otieno', 'Maple Terrace · C-301', 'KES 63,000', '8 days', 'Follow up'], ['Faith Achieng', 'Palm Court · A-212', 'KES 52,000', '5 days', 'Follow up']
  ],
  tenants: [
    ['Palm Court Apartments', '46 / 48', '97%', 'KES 4.1M', '2'], ['Riverside Ridge', '38 / 40', '95%', 'KES 3.3M', '2'], ['Maple Terrace', '29 / 32', '91%', 'KES 2.8M', '3']
  ],
  maintenance: [
    ['Plumbing', '8', 'KES 142,000', '4.2 days'], ['Electrical', '5', 'KES 98,000', '2.8 days'], ['Appliances', '3', 'KES 76,000', '5.1 days'], ['Structural', '2', 'KES 210,000', '8.4 days']
  ],
  utilities: [
    ['Water', '1,248 m³', 'KES 186,400', '↓ 8.2%', 'Efficient'], ['Electricity', '42,680 kWh', 'KES 642,100', '↑ 3.6%', 'Monitor'], ['Waste', '18.4 tonnes', 'KES 96,800', '↓ 2.1%', 'Efficient']
  ],
  income: [
    ['Rent income', 'KES 4,860,000', '82.4%', 'Income'], ['Service charges', 'KES 618,000', '10.5%', 'Income'], ['Parking income', 'KES 124,000', '2.1%', 'Income'], ['Maintenance & repairs', 'KES 412,000', '28.8%', 'Expense'], ['Utilities', 'KES 286,000', '20.0%', 'Expense'], ['Operations', 'KES 731,000', '51.2%', 'Expense']
  ]
};

const reports = {
  occupancy: { title: 'Occupancy report', description: 'Occupied units and availability across your portfolio.', headers: ['Property', 'Occupancy', 'Units', 'Vacant'], rows: () => data.occupancy.map(([name, rate, units]) => [name, `${rate}%`, units, Math.round(units * (1 - rate / 100))]), chart: true, insight: ['94.2%', 'Portfolio occupancy is up 2.4% from last month.'] },
  collection: { title: 'Rent collection report', description: 'Collection performance against expected rent for the period.', headers: ['Property', 'Expected', 'Collected', 'Collection rate', 'Status'], rows: () => data.collection, chart: false, insight: ['KES 4.86M', 'Collected this period, with 86% of expected rent received.'] },
  outstanding: { title: 'Outstanding rent report', description: 'Tenants requiring follow-up on unpaid balances.', headers: ['Tenant', 'Unit', 'Balance', 'Age', 'Status'], rows: () => data.outstanding, chart: false, insight: ['KES 782K', 'Total outstanding across 24 tenants. Prioritize balances older than 14 days.'] },
  tenants: { title: 'Tenant report', description: 'Tenant and lease summary by property.', headers: ['Property', 'Occupied units', 'Occupancy', 'Monthly rent', 'Renewals due'], rows: () => data.tenants, chart: false, insight: ['113 tenants', 'Across three properties, with 7 lease renewals due this quarter.'] },
  maintenance: { title: 'Maintenance report', description: 'Open work orders, spend, and average completion time.', headers: ['Category', 'Open requests', 'Spend this month', 'Avg. resolution'], rows: () => data.maintenance, chart: false, insight: ['4.2 days', 'Average resolution time. Plumbing remains the highest-volume category.'] },
  utilities: { title: 'Utility consumption report', description: 'Consumption and costs across key building utilities.', headers: ['Utility', 'Consumption', 'Cost', 'Change', 'Signal'], rows: () => data.utilities, chart: false, insight: ['KES 925K', 'Total utility spend this period. Electricity is the largest cost driver.'] },
  income: { title: 'Income & expense report', description: 'A clear view of operating income and expenses.', headers: ['Line item', 'Amount', 'Share', 'Type'], rows: () => data.income, chart: false, insight: ['KES 4.17M', 'Net operating income this period after operating expenses.'] }
};

let activeReport = 'occupancy';
let tableMode = false;
const money = value => `KES ${(value / 1000000).toFixed(2)}M`;
const reportContent = document.querySelector('#reportContent');

function getPropertyData() {
  return data.properties[document.querySelector('#propertySelect').value];
}

function updateMetrics() {
  const selected = getPropertyData();
  document.querySelector('#occupancyMetric').textContent = `${((selected.occupied / selected.units) * 100).toFixed(1)}%`;
  document.querySelector('#collectedMetric').textContent = money(selected.collected);
  document.querySelector('#outstandingMetric').textContent = money(selected.outstanding);
  document.querySelector('#maintenanceMetric').textContent = selected.openMaintenance;
}

function renderChart() {
  const bars = [68, 81, 73, 88, 84, 94, 91, 97];
  return `<div class="chart-layout"><div><div class="bar-chart">${bars.map((height, index) => `<div class="bar-group"><div class="bar" style="height:${height}%"></div><div class="bar alt" style="height:${Math.max(20, height - 22)}%"></div><span class="bar-label">${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'][index]}</span></div>`).join('')}</div><div class="legend"><span><i class="teal"></i>Occupied</span><span><i class="orange"></i>Available</span></div></div><aside class="insight"><p class="eyebrow">At a glance</p><strong>${reports[activeReport].insight[0]}</strong><p>${reports[activeReport].insight[1]}</p></aside></div>`;
}

function renderTable(report) {
  return `<div class="table-wrap"><table class="report-table"><thead><tr>${report.headers.map(header => `<th>${header}</th>`).join('')}</tr></thead><tbody>${report.rows().map(row => `<tr>${row.map((cell, index) => `<td>${index === row.length - 1 && ['On track', 'Overdue', 'Follow up', 'Efficient', 'Monitor', 'Income', 'Expense'].includes(cell) ? `<span class="pill ${cell === 'Overdue' ? 'alert' : cell === 'Follow up' || cell === 'Monitor' || cell === 'Expense' ? 'warn' : ''}">${cell}</span>` : cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}

function renderReport() {
  const report = reports[activeReport];
  reportContent.innerHTML = `<article class="report-card"><div class="report-card-head"><div><h3>${report.title}</h3><p>${report.description}</p></div><div class="report-tools"><button type="button" id="downloadReport">↓ Download</button><button type="button" id="refreshReport">↻ Refresh</button></div></div>${!tableMode && report.chart ? renderChart() : renderTable(report)}</article>`;
  document.querySelector('#downloadReport').addEventListener('click', () => downloadCsv(report));
  document.querySelector('#refreshReport').addEventListener('click', renderReport);
}

function downloadCsv(report) {
  const csv = [report.headers, ...report.rows()].map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(',')).join('\n');
  const link = document.createElement('a');
  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  link.download = `${activeReport}-report.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

document.querySelectorAll('.report-tab').forEach(tab => tab.addEventListener('click', () => {
  document.querySelectorAll('.report-tab').forEach(item => item.classList.remove('active'));
  tab.classList.add('active');
  activeReport = tab.dataset.report;
  renderReport();
}));
document.querySelector('#propertySelect').addEventListener('change', updateMetrics);
document.querySelector('#chartView').addEventListener('click', event => { tableMode = false; document.querySelector('#tableView').classList.remove('active'); event.currentTarget.classList.add('active'); renderReport(); });
document.querySelector('#tableView').addEventListener('click', event => { tableMode = true; document.querySelector('#chartView').classList.remove('active'); event.currentTarget.classList.add('active'); renderReport(); });
document.querySelector('#exportButton').addEventListener('click', () => downloadCsv(reports[activeReport]));
document.querySelector('#printButton').addEventListener('click', () => window.print());
updateMetrics();
renderReport();
