const seedRequests = [
  { id: 'MW-1048', title: 'Kitchen sink is leaking', icon: '⌁', iconClass: 'plumbing', unit: 'A-204', tenant: 'Njeri Kamau', priority: 'High', assignee: 'Brian Otieno', initials: 'BO', status: 'In Progress', cost: 8500, reported: 'Today, 09:42' },
  { id: 'MW-1047', title: 'Bedroom light not working', icon: '☼', iconClass: 'electrical', unit: 'B-110', tenant: 'David Ochieng', priority: 'Medium', assignee: 'Wanjiku M.', initials: 'WM', status: 'Assigned', cost: 0, reported: 'Today, 08:15' },
  { id: 'MW-1046', title: 'Air conditioner service', icon: '❋', iconClass: 'climate', unit: 'C-301', tenant: 'Amina Hassan', priority: 'Low', assignee: 'Peter Mwangi', initials: 'PM', status: 'In Progress', cost: 12000, reported: 'Yesterday, 16:30' },
  { id: 'MW-1045', title: 'Bathroom door handle loose', icon: '◈', iconClass: 'general', unit: 'A-102', tenant: 'James Wambui', priority: 'Low', assignee: 'Unassigned', initials: '', status: 'Reported', cost: 0, reported: 'Yesterday, 14:05' },
  { id: 'MW-1044', title: 'Water pressure is low', icon: '⌁', iconClass: 'plumbing', unit: 'B-205', tenant: 'Mariam Ali', priority: 'High', assignee: 'Brian Otieno', initials: 'BO', status: 'In Progress', cost: 4800, reported: '16 Sep, 11:24' },
  { id: 'MW-1043', title: 'Replace hallway bulb', icon: '☼', iconClass: 'electrical', unit: 'Common area', tenant: 'Property team', priority: 'Medium', assignee: 'Wanjiku M.', initials: 'WM', status: 'Reported', cost: 0, reported: '16 Sep, 09:10' },
  { id: 'MW-1042', title: 'Balcony drain blocked', icon: '⌁', iconClass: 'plumbing', unit: 'C-208', tenant: 'Omar Said', priority: 'Medium', assignee: 'Unassigned', initials: '', status: 'Reported', cost: 0, reported: '15 Sep, 15:55' },
  { id: 'MW-1041', title: 'Wardrobe hinge repair', icon: '◈', iconClass: 'general', unit: 'A-305', tenant: 'Lucy Njeri', priority: 'Low', assignee: 'Peter Mwangi', initials: 'PM', status: 'Assigned', cost: 2750, reported: '15 Sep, 10:21' }
];
const stored = localStorage.getItem('mapesaMaintenanceRequests');
let requests = stored ? JSON.parse(stored) : seedRequests;
const rows = document.querySelector('#requestRows');
const emptyState = document.querySelector('#emptyState');
const reportModal = document.querySelector('#reportModal');
const reportForm = document.querySelector('#reportForm');
const toast = document.querySelector('#toast');
const statusFilter = document.querySelector('#statusFilter');
const priorityFilter = document.querySelector('#priorityFilter');

function persist() { localStorage.setItem('mapesaMaintenanceRequests', JSON.stringify(requests)); }
function money(value) { return value ? `KES ${value.toLocaleString()}` : '—'; }
function statusClass(status) { return status.toLowerCase().replaceAll(' ', '-'); }
function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, character => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}
function renderRows() {
  const status = statusFilter.value;
  const priority = priorityFilter.value;
  const filtered = requests.filter(request => (status === 'all' || request.status === status) && (priority === 'all' || request.priority === priority));
  rows.innerHTML = filtered.map(request => `<tr data-id="${escapeHtml(request.id)}"><td><div class="request-cell"><span class="issue-icon ${escapeHtml(request.iconClass)}">${escapeHtml(request.icon)}</span><div><strong>${escapeHtml(request.title)}</strong><small>${escapeHtml(request.id)} · ${escapeHtml(request.reported)}</small></div></div></td><td><div class="tenant-cell"><strong>${escapeHtml(request.unit)}</strong><small>${escapeHtml(request.tenant)}</small></div></td><td><span class="priority ${escapeHtml(request.priority.toLowerCase())}">${escapeHtml(request.priority)}</span></td><td><div class="assignee ${request.assignee === 'Unassigned' ? 'unassigned' : ''}">${request.initials ? `<span class="mini-avatar">${escapeHtml(request.initials)}</span>` : ''}<span>${escapeHtml(request.assignee)}</span></div></td><td><button class="status ${escapeHtml(statusClass(request.status))}" data-action="advance" title="Advance status">${escapeHtml(request.status)}</button></td><td><span class="cost">${escapeHtml(money(request.cost))}</span></td><td><button class="row-menu" aria-label="More actions">···</button></td></tr>`).join('');
  emptyState.classList.toggle('show', filtered.length === 0);
  document.querySelector('#showingCount').textContent = filtered.length;
  document.querySelector('#requestCount').textContent = `${filtered.length} active request${filtered.length === 1 ? '' : 's'}`;
  updateSummary();
}
function updateSummary() {
  const active = requests.filter(request => request.status !== 'Completed');
  const progress = requests.filter(request => request.status === 'In Progress').length;
  const reported = requests.filter(request => request.status === 'Reported').length;
  const assigned = requests.filter(request => request.status === 'Assigned').length;
  document.querySelector('#openCount').textContent = active.length;
  document.querySelector('#progressCount').textContent = progress;
  document.querySelector('#reportedFlow').textContent = `${reported} request${reported === 1 ? '' : 's'}`;
  document.querySelector('#assignedFlow').textContent = `${assigned} request${assigned === 1 ? '' : 's'}`;
  document.querySelector('#progressFlow').textContent = `${progress} request${progress === 1 ? '' : 's'}`;
  document.querySelector('#completedFlow').textContent = `${requests.filter(request => request.status === 'Completed').length + 14} this month`;
  const total = requests.reduce((sum, request) => sum + request.cost, 42850 - seedRequests.reduce((sum, request) => sum + request.cost, 0));
  document.querySelector('#costTotal').textContent = money(total);
}
function openModal() { reportModal.classList.add('open'); reportModal.setAttribute('aria-hidden', 'false'); document.querySelector('[name="title"]').focus(); }
function closeModal() { reportModal.classList.remove('open'); reportModal.setAttribute('aria-hidden', 'true'); reportForm.reset(); document.querySelector('#photoPreview').innerHTML = ''; }
function showToast(title, message, isError = false) {
  toast.classList.toggle('error', isError);
  toast.querySelector('span').textContent = isError ? '!' : '✓';
  toast.querySelector('strong').textContent = title;
  toast.querySelector('small').textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 4000);
}

document.querySelector('#openReport').addEventListener('click', openModal);
document.querySelector('#closeReport').addEventListener('click', closeModal);
document.querySelector('#cancelReport').addEventListener('click', closeModal);
reportModal.addEventListener('click', event => { if (event.target === reportModal) closeModal(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape' && reportModal.classList.contains('open')) closeModal(); });
document.querySelector('#photoUpload').addEventListener('change', event => { document.querySelector('#photoPreview').innerHTML = ''; [...event.target.files].slice(0, 4).forEach(file => { const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.alt = file.name; document.querySelector('#photoPreview').append(image); }); });
reportForm.addEventListener('submit', async event => {
  event.preventDefault();
  const submitButton = reportForm.querySelector('[type="submit"]');
  submitButton.disabled = true;

  try {
    const response = await fetch('../Management%20API/maintenance-requests.php', {
      method: 'POST',
      body: new FormData(reportForm),
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error || 'Unable to save the maintenance request.');
    }
    if (!Number.isSafeInteger(payload.requestId) || payload.requestId < 1) {
      throw new Error('The server did not confirm the saved maintenance request.');
    }

    const data = new FormData(reportForm);
    requests.unshift({ id: `MW-${payload.requestId}`, title: data.get('title'), icon: '◈', iconClass: 'general', unit: data.get('unit'), tenant: 'New request', priority: data.get('priority'), assignee: 'Unassigned', initials: '', status: 'Reported', cost: 0, reported: 'Just now' });
    try {
      persist();
    } catch (storageError) {
      console.error('The saved maintenance request could not be cached locally.', storageError);
    }
    renderRows();
    closeModal();
    showToast('Report submitted', 'Your maintenance request is now in the queue.');
  } catch (error) {
    showToast(
      'Report not saved',
      error instanceof Error ? error.message : 'Unable to save the maintenance request.',
      true,
    );
  } finally {
    submitButton.disabled = false;
  }
});
rows.addEventListener('click', event => { const button = event.target.closest('[data-action="advance"]'); if (!button) return; const request = requests.find(item => item.id === button.closest('tr').dataset.id); const stages = ['Reported', 'Assigned', 'In Progress', 'Completed']; const next = stages[stages.indexOf(request.status) + 1]; if (next) { request.status = next; if (next === 'Assigned') request.assignee = 'Wanjiku M.'; if (next === 'In Progress') { request.assignee = 'Brian Otieno'; request.initials = 'BO'; } persist(); renderRows(); } });
document.querySelector('#filterButton').addEventListener('click', () => document.querySelector('#filterPanel').classList.toggle('show'));
[statusFilter, priorityFilter].forEach(filter => filter.addEventListener('change', () => { document.querySelector('#filterBadge').textContent = [statusFilter, priorityFilter].filter(item => item.value !== 'all').length; document.querySelector('#filterBadge').classList.toggle('visible', statusFilter.value !== 'all' || priorityFilter.value !== 'all'); renderRows(); }));
document.querySelector('#clearFilters').addEventListener('click', () => { statusFilter.value = 'all'; priorityFilter.value = 'all'; document.querySelector('#filterBadge').classList.remove('visible'); renderRows(); });
document.querySelector('#viewHistory').addEventListener('click', () => { statusFilter.value = 'Completed'; document.querySelector('#filterPanel').classList.add('show'); document.querySelector('#filterBadge').textContent = '1'; document.querySelector('#filterBadge').classList.add('visible'); renderRows(); document.querySelector('#requests').scrollIntoView({ behavior: 'smooth' }); });
renderRows();
