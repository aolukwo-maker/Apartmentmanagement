(() => {
  const navigation = document.querySelector(".module-navigation");
  const isHub = !navigation && Boolean(document.querySelector(".management-hub"));
  const isTenantRegistration = Boolean(
    document.querySelector(".tenant-registration-page"),
  );
  if (!navigation && !isHub && !isTenantRegistration) return;

  const modules = {
    "Rent & payments": "rent",
    "Financial management": "finance",
    Maintenance: "maintenance",
    Utilities: "utilities",
    "Complaints & feedback": "complaints",
    Communication: "communication",
    "Reports & analytics": "reports",
  };
  const currentLink = navigation?.querySelector('[aria-current="page"]');
  const module = isHub || isTenantRegistration
    ? null
    : modules[currentLink?.textContent.trim()];
  if (!isHub && !isTenantRegistration && !module) return;
  if (!isHub && !isTenantRegistration) {
    document.body.classList.add("has-live-dashboard");
  }
  document.body.classList.add("has-management-sidebar");

  const workspaces = [
    {
      key: "rent",
      title: "Rent & payments",
      href: "Rent%20%26%20Payment%20Module/index.php",
    },
    {
      key: "finance",
      title: "Financial management",
      href: "Financial%20Management%20Module/index.html",
    },
    {
      key: "maintenance",
      title: "Maintenance",
      href: "Maintenance%20Module/index.html",
    },
    {
      key: "utilities",
      title: "Utility management",
      href: "Utility%20%20Management%20Module/index.html",
    },
    {
      key: "complaints",
      title: "Complaints & feedback",
      href: "Complaints%20%20Feedback%20Module/index.html",
    },
    {
      key: "communication",
      title: "Communication",
      href: "Communication%20%26%20Notification%20Module/index.html",
    },
    {
      key: "reports",
      title: "Reports & analytics",
      href: "Reports%20%26%20Analytics%20Module/index.html",
    },
  ];
  const moduleRoot = isHub || isTenantRegistration ? "" : "../";
  if (isHub || isTenantRegistration) {
    const content = document.querySelector(
      isHub ? ".management-hub" : ".tenant-registration-page",
    );
    const shell = document.createElement("div");
    shell.className = "app-shell";
    content.classList.add("main-content");
    content.parentNode.insertBefore(shell, content);
    shell.append(content);
  }
  const shell = document.querySelector(".app-shell");
  const sidebar = document.createElement("aside");
  sidebar.className = "sidebar management-sidebar";
  sidebar.setAttribute("aria-label", "Management navigation");
  sidebar.innerHTML = `
    <a class="management-brand" href="${moduleRoot}index.html">
      <span class="management-brand-mark">M</span>
      <span>Mapesa<small>PROPERTY MANAGEMENT</small></span>
    </a>
    <span class="management-sidebar-label">WORKSPACE</span>
    <nav class="management-sidebar-nav" aria-label="Management workspaces">
      <a class="management-nav-link${isHub ? " is-current" : ""}" href="${moduleRoot}index.html"${isHub ? ' aria-current="page"' : ""}>
        <span class="management-nav-icon" aria-hidden="true">⌂</span>
        <span>Dashboard</span>
      </a>
      <a class="management-nav-link management-nav-action${isTenantRegistration ? " is-current" : ""}" href="${moduleRoot}tenant-registration.php"${isTenantRegistration ? ' aria-current="page"' : ""}>
        <span class="management-nav-icon" aria-hidden="true">+</span>
        <span>Register tenant</span>
      </a>
      ${workspaces.map(({ key, title, href }) => `
        <a class="management-nav-link${module === key ? " is-current" : ""}" href="${moduleRoot}${href}"${module === key ? ' aria-current="page"' : ""}>
          <span class="management-nav-icon" aria-hidden="true">${title.charAt(0)}</span>
          <span>${title}</span>
        </a>`).join("")}
    </nav>`;
  shell.insertBefore(sidebar, shell.firstChild);

  const dashboard = document.createElement("section");
  dashboard.className = `live-dashboard${isHub ? " live-dashboard-hub" : ""}`;
  dashboard.setAttribute("aria-label", "Live database dashboard");
  dashboard.setAttribute("aria-live", "polite");
  dashboard.innerHTML = `
    <div class="live-dashboard-heading">
      <div>
        <span class="live-dashboard-eyebrow">Live database data</span>
        <h2>${isHub ? "Workspace activity" : "Management snapshot"}</h2>
      </div>
      <span class="live-dashboard-status" role="status">Connecting to database…</span>
    </div>
    ${isHub ? "" : '<div class="live-dashboard-metrics"></div>'}`;
  const account = document.createElement("section");
  account.className = `manager-account${isHub ? " manager-account-hub" : ""}`;
  account.setAttribute("aria-label", "Signed-in manager profile");
  account.innerHTML = `
    <div class="manager-account-details">
      <span class="manager-account-label">SIGNED IN AS</span>
      <strong class="manager-account-name">Loading account…</strong>
      <span class="manager-account-email">Loading profile details…</span>
    </div>
    <div class="manager-account-actions">
      <a class="manager-profile-link">My profile</a>
      <a class="manager-logout-link">Sign out</a>
    </div>`;
  if (isHub) {
    document.querySelector(".hub-grid").insertAdjacentElement("beforebegin", dashboard);
  } else if (!isTenantRegistration) {
    navigation.insertAdjacentElement("afterend", account);
    navigation.insertAdjacentElement("afterend", dashboard);
  }
  sidebar.append(account);

  const applicationRoot = isHub || isTenantRegistration ? "../" : "../../";
  const managementApiRoot = isHub || isTenantRegistration ? "" : "../";
  const authPath = `${applicationRoot}User%20%26%20Authentication%20Module`;
  const profileLink = account.querySelector(".manager-profile-link");
  const logoutLink = account.querySelector(".manager-logout-link");
  profileLink.href = `${authPath}/profile.php`;
  logoutLink.href = `${authPath}/logout.php`;

  const refreshManagerProfile = async () => {
    const name = account.querySelector(".manager-account-name");
    const email = account.querySelector(".manager-account-email");
    const endpoint = `${managementApiRoot}Management%20API/manager-profile.php`;

    try {
      const response = await fetch(endpoint, {
        cache: "no-store",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (response.status === 401) {
        window.location.href = `${authPath}/login.php`;
        return;
      }
      if (response.status === 403) {
        window.location.href = `${authPath}/access_denied.php`;
        return;
      }
      if (!response.ok) {
        throw new Error(payload.error || "Unable to load your manager profile.");
      }
      if (
        typeof payload.fullName !== "string"
        || typeof payload.email !== "string"
        || typeof payload.role !== "string"
      ) {
        throw new Error("The manager profile response is incomplete.");
      }

      name.textContent = payload.fullName;
      email.textContent = payload.email;
      document.querySelectorAll("[data-manager-name]").forEach((element) => {
        element.textContent = payload.fullName;
      });
      document.querySelectorAll("[data-manager-role]").forEach((element) => {
        element.textContent = payload.role;
      });
      const initials = payload.fullName
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
      document.querySelectorAll("[data-manager-initials]").forEach((element) => {
        element.textContent = initials || "?";
      });
    } catch (error) {
      name.textContent = "Profile unavailable";
      email.textContent = error instanceof Error
        ? error.message
        : "Unable to load your manager profile.";
    }
  };

  const escapeHtml = (value) =>
    String(value).replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    })[character]);

  const formatValue = (metric) => {
    if (metric.format === "currency") {
      return `KES ${Number(metric.value).toLocaleString("en-KE", {
        maximumFractionDigits: 2,
      })}`;
    }
    if (metric.format === "percent") return `${metric.value}%`;
    if (metric.format === "rating") return `${metric.value} / 5`;
    return Number(metric.value).toLocaleString("en-KE");
  };

  const hubHighlights = {
    rent: 0,
    finance: 2,
    maintenance: 0,
    utilities: 2,
    complaints: 0,
    communication: 2,
    reports: 2,
  };
  const moduleTitles = {
    rent: "Rent & payments",
    finance: "Financial management",
    maintenance: "Maintenance",
    utilities: "Utilities",
    complaints: "Complaints & feedback",
    communication: "Communication",
    reports: "Reports & analytics",
  };

  const refresh = async () => {
    const status = dashboard.querySelector(".live-dashboard-status");
    try {
      const endpoint = `${managementApiRoot}Management%20API/dashboard-data.php`;
      const response = await fetch(endpoint, {
        cache: "no-store",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload.error || "Unable to load live dashboard data.");
      }

      const metrics = isHub
        ? Object.keys(moduleTitles).map((key) => ({
            key,
            metric: payload.modules?.[key]?.[hubHighlights[key]],
          }))
        : (payload.modules?.[module] || []).map((metric) => ({ metric }));
      if (!metrics.length || metrics.some((item) => !item.metric)) {
        throw new Error("The database response is missing dashboard data.");
      }

      if (isHub) {
        metrics.forEach(({ key, metric }) => {
          const card = document.querySelector(`[data-dashboard-module="${key}"]`);
          const value = card?.querySelector("[data-dashboard-metric]");
          if (!value) {
            throw new Error(`The ${moduleTitles[key]} workspace link is missing its live metric.`);
          }
          value.textContent = `${metric.label}: ${formatValue(metric)}`;
        });
      } else {
        dashboard.querySelector(".live-dashboard-metrics").innerHTML = metrics
          .map(({ metric }) => `
            <article class="live-dashboard-card">
              <span>${escapeHtml(metric.label)}</span>
              <strong>${escapeHtml(formatValue(metric))}</strong>
            </article>`)
          .join("");
      }
      const updated = new Date(Number(payload.generatedAt) * 1000);
      if (Number.isNaN(updated.getTime())) {
        throw new Error("The database response has an invalid update time.");
      }
      status.textContent = `Updated from MySQL · ${updated.toLocaleTimeString()}`;
      dashboard.classList.remove("live-dashboard-error");
    } catch (error) {
      status.textContent = error instanceof Error
        ? error.message
        : "Unable to load live dashboard data.";
      dashboard.classList.add("live-dashboard-error");
      if (isHub) {
        document.querySelectorAll("[data-dashboard-metric]").forEach((element) => {
          element.textContent = "Live data unavailable";
        });
      }
    }
  };

  refreshManagerProfile();
  if (!isTenantRegistration) {
    refresh();
    window.setInterval(refresh, 15000);
  }
})();
