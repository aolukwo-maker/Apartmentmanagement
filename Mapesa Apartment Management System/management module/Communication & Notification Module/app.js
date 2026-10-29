const modal = document.querySelector('#composeModal');
const form = document.querySelector('#messageForm');
const toast = document.querySelector('#toast');
const activityList = document.querySelector('#activityList');
const templates = {
  Announcement: { title: "Residents' committee meeting", body: 'Join us for the monthly residents\' committee meeting this Thursday at 6:30 PM in the common room.' },
  Reminder: { title: 'September rent reminder', body: 'A friendly reminder that September rent is due by the 5th. Please reach out if you need assistance.' },
  Maintenance: { title: 'Water supply interruption', body: 'Water supply will be temporarily interrupted tomorrow from 10:00 AM to 1:00 PM while we complete essential maintenance.' },
  Emergency: { title: 'Important security update', body: 'Please use the east entrance until further notice. Our security team is assisting with an incident at the main gate.' },
  Payment: { title: 'Rent payment received', body: 'Thank you. Your rent payment has been received and your account is now up to date.' }
};

function openModal(type = 'Announcement') {
  const template = templates[type];
  document.querySelector('#messageType').value = type;
  document.querySelector('#messageTitle').value = template.title;
  document.querySelector('#messageBody').value = template.body;
  modal.hidden = false;
  document.querySelector('#messageTitle').focus();
}

function closeModal() { modal.hidden = true; }

document.querySelector('#composeTop').addEventListener('click', () => openModal());
document.querySelector('#closeModal').addEventListener('click', closeModal);
modal.addEventListener('click', event => { if (event.target === modal) closeModal(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
document.querySelectorAll('[data-template]').forEach(button => button.addEventListener('click', () => openModal(button.dataset.template)));

form.addEventListener('submit', event => {
  event.preventDefault();
  const type = document.querySelector('#messageType').value;
  const title = document.querySelector('#messageTitle').value;
  const row = document.createElement('article');
  row.className = 'activity-row';
  row.dataset.type = type;
  row.innerHTML = `<span class="activity-icon announcement">✦</span><div class="activity-detail"><div><strong>${title}</strong><span class="status sent">Sent</span></div><p>${type} · All residents</p><span class="activity-time">Just now</span></div><span class="channel-tag">SMS + email</span>`;
  activityList.prepend(row);
  closeModal();
  toast.classList.add('show');
  window.setTimeout(() => toast.classList.remove('show'), 3200);
});

document.querySelector('#scheduleButton').addEventListener('click', () => {
  closeModal();
  toast.querySelector('strong').textContent = 'Message scheduled';
  toast.querySelector('small').textContent = 'It will be sent at the selected time.';
  toast.classList.add('show');
  window.setTimeout(() => { toast.classList.remove('show'); toast.querySelector('strong').textContent = 'Message sent'; toast.querySelector('small').textContent = 'Your communication is on its way.'; }, 3200);
});

document.querySelector('#filterButton').addEventListener('click', event => {
  const options = ['All types', 'Announcement', 'Reminder', 'Payment', 'Maintenance', 'Emergency'];
  const current = event.currentTarget.dataset.filter || 'All types';
  const next = options[(options.indexOf(current) + 1) % options.length];
  event.currentTarget.dataset.filter = next;
  event.currentTarget.innerHTML = `${next} <span>⌄</span>`;
  document.querySelectorAll('.activity-row').forEach(row => { row.hidden = next !== 'All types' && row.dataset.type !== next; });
});

document.querySelector('#viewScheduled').addEventListener('click', () => {
  document.querySelector('#filterButton').click();
  document.querySelector('#filterButton').click();
  document.querySelector('#filterButton').click();
  document.querySelector('#filterButton').click();
  document.querySelector('#filterButton').click();
});