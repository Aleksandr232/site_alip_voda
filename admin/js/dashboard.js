(function () {
  const STATUS_BADGES = {
    new: "badge--warning",
    in_progress: "badge--primary",
    done: "badge--success",
  };

  const STATUS_LABELS = {
    new: "Новая",
    in_progress: "В работе",
    done: "Обработана",
  };

  async function loadDashboardStats() {
    const newCountEl = document.getElementById("stat-new-requests");
    const clientsEl = document.getElementById("stat-clients");
    const badgeEl = document.getElementById("nav-requests-badge");
    const tbody = document.getElementById("dashboard-requests-tbody");

    if (!newCountEl && !tbody) return;

    try {
      const data = await Auth.apiRequest("/requests.php?stats=1");
      const stats = data.stats || {};

      if (newCountEl) newCountEl.textContent = String(stats.new ?? 0);
      if (clientsEl) clientsEl.textContent = String(stats.clients ?? 0);

      if (badgeEl) {
        const count = stats.new ?? 0;
        badgeEl.textContent = String(count);
        badgeEl.hidden = count === 0;
      }

      if (tbody) {
        const list = await Auth.apiRequest("/requests.php");
        const items = (list.requests || []).slice(0, 5);

        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">Заявок пока нет</td></tr>';
          return;
        }

        tbody.innerHTML = items
          .map((item) => {
            const date = new Date(item.created_at.replace(" ", "T")).toLocaleDateString("ru-RU");
            const badgeClass = STATUS_BADGES[item.status] || "badge--warning";
            const label = STATUS_LABELS[item.status] || item.status;

            return `
              <tr>
                <td>${escapeHtml(item.client_name)}</td>
                <td>${escapeHtml(item.service_label)}</td>
                <td>${date}</td>
                <td><span class="badge ${badgeClass}">${label}</span></td>
              </tr>
            `;
          })
          .join("");
      }
    } catch (error) {
      console.error(error);
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  document.addEventListener("DOMContentLoaded", loadDashboardStats);
})();
