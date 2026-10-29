const starterComplaints = [
  { id: 1, title: "Leaking tap in kitchen", resident: "James O. · Unit 3B", category: "Maintenance", priority: "High", assignee: "Sarah N.", initials: "SN", avatar: "avatar-blue", status: "Open" },
  { id: 2, title: "Loud music after 10pm", resident: "Linda N. · Unit 2A", category: "Noise", priority: "Medium", assignee: "Brian K.", initials: "BK", avatar: "avatar-amber", status: "In progress" },
  { id: 3, title: "Parking access card not working", resident: "Michael K. · Unit 1C", category: "Security", priority: "High", assignee: "Moses W.", initials: "MW", avatar: "avatar-coral", status: "Resolved" },
  { id: 4, title: "Lift maintenance, Block A", resident: "Sarah N. · Unit 4D", category: "Maintenance", priority: "Low", assignee: "Grace A.", initials: "GA", avatar: "avatar-teal", status: "In progress" },
  { id: 5, title: "Incorrect water bill for August", resident: "David M. · Unit 5B", category: "Billing", priority: "Medium", assignee: "Unassigned", initials: "?", avatar: "avatar-neutral", status: "Open" },
  { id: 6, title: "Bin collection missed", resident: "Amina K. · Unit 2C", category: "Cleaning", priority: "Low", assignee: "Unassigned", initials: "?", avatar: "avatar-neutral", status: "Open" },
  { id: 7, title: "Hallway light needs replacement", resident: "Peter O. · Unit 6A", category: "Maintenance", priority: "Low", assignee: "Sarah N.", initials: "SN", avatar: "avatar-blue", status: "Resolved" }
];

const storedComplaints = localStorage.getItem("haven-complaints");
let complaints = storedComplaints ? JSON.parse(storedComplaints) : starterComplaints;
const table = document.querySelector("#complaintsTable");
const emptyState = document.querySelector("#emptyState");
const resultCount = document.querySelector("#resultCount");
const modal = document.querySelector("#complaintModal");
const form = document.querySelector("#complaintForm");
const toast = document.querySelector("#toast");

function initialsFor(name) {
  if (name === "Unassigned") return "?";
  return name.split(" ").map((part) => part[0]).join("").slice(0, 2);
}

function avatarClass(name) {
  const classes = ["avatar-teal", "avatar-blue", "avatar-amber", "avatar-coral"];
  return classes[name.charCodeAt(0) % classes.length];
}

function renderComplaints() {
  const query = document.querySelector("#searchInput").value.toLowerCase().trim();
  const status = document.querySelector("#statusFilter").value;
  const category = document.querySelector("#categoryFilter").value;
  const filtered = complaints.filter((item) => {
    const matchesQuery = [item.title, item.resident, item.category, item.assignee].join(" ").toLowerCase().includes(query);
    return matchesQuery && (status === "all" || item.status === status) && (category === "all" || item.category === category);
  });

  table.innerHTML = filtered.slice(0, 5).map((item) => `
    <tr>
      <td><span class="complaint-name">${item.title}</span><span class="complaint-meta">${item.resident}</span></td>
      <td class="category">${item.category}</td>
      <td><span class="priority ${item.priority.toLowerCase()}">${item.priority}</span></td>
      <td><span class="assignee"><span class="avatar mini-avatar ${item.assignee === "Unassigned" ? "avatar-neutral" : item.avatar || avatarClass(item.assignee)}">${item.initials || initialsFor(item.assignee)}</span>${item.assignee}</span></td>
      <td><span class="status ${item.status.toLowerCase().replace(" ", "-")}">${item.status}</span></td>
      <td><button class="icon-button row-action" aria-label="More options for ${item.title}"><i data-lucide="more-horizontal"></i></button></td>
    </tr>`).join("");

  emptyState.classList.toggle("hidden", filtered.length > 0);
  table.classList.toggle("hidden", filtered.length === 0);
  resultCount.textContent = filtered.length ? `Showing ${Math.min(filtered.length, 5)} of ${filtered.length} complaints` : "No matching complaints";
  document.querySelector("#openMetric").textContent = complaints.filter((item) => item.status !== "Resolved").length;
  document.querySelector("#unassignedMetric").textContent = complaints.filter((item) => item.assignee === "Unassigned").length;
  lucide.createIcons();
}

function saveComplaints() {
  localStorage.setItem("haven-complaints", JSON.stringify(complaints));
}

function openModal() { modal.classList.remove("hidden"); document.body.style.overflow = "hidden"; document.querySelector("[name=title]").focus(); }
function closeModal() { modal.classList.add("hidden"); document.body.style.overflow = ""; form.reset(); }
function showToast(message) { toast.querySelector("span").textContent = message; toast.classList.remove("hidden"); setTimeout(() => toast.classList.add("hidden"), 3200); }

document.querySelector("#openComplaintModal").addEventListener("click", openModal);
document.querySelector("#closeComplaintModal").addEventListener("click", closeModal);
document.querySelector("#cancelComplaint").addEventListener("click", closeModal);
modal.addEventListener("click", (event) => { if (event.target === modal) closeModal(); });
document.addEventListener("keydown", (event) => { if (event.key === "Escape" && !modal.classList.contains("hidden")) closeModal(); });
["#searchInput", "#statusFilter", "#categoryFilter"].forEach((selector) => document.querySelector(selector).addEventListener("input", renderComplaints));

document.querySelector("#viewAllButton").addEventListener("click", () => { document.querySelector("#statusFilter").value = "all"; document.querySelector("#categoryFilter").value = "all"; document.querySelector("#searchInput").value = ""; renderComplaints(); showToast("Showing all complaints"); });
document.querySelector("#activityButton").addEventListener("click", () => showToast("Activity log is up to date"));
document.querySelector("#feedbackButton").addEventListener("click", () => showToast("Feedback insights are coming into focus"));

form.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const assignee = data.get("assignee");
  complaints.unshift({ id: Date.now(), title: data.get("title"), resident: data.get("resident"), category: data.get("category"), priority: data.get("priority"), assignee, initials: initialsFor(assignee), avatar: avatarClass(assignee), status: "Open" });
  saveComplaints();
  renderComplaints();
  closeModal();
  showToast("Complaint submitted successfully.");
});

lucide.createIcons();
renderComplaints();
