(function () {
  function formatDate(iso) {
    const date = new Date(iso.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return iso;
    return date.toLocaleDateString("ru-RU");
  }

  function formatPhone(phone) {
    const digits = phone.replace(/\D/g, "");
    if (digits.length === 11 && digits.startsWith("7")) {
      return `+7 ${digits.slice(1, 4)} ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
    }
    return phone;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderRows(items) {
    const tbody = document.getElementById("clients-tbody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">Клиентов пока нет</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((item) => {
        const phone = formatPhone(item.phone);
        return `
          <tr>
            <td><strong>${escapeHtml(item.name)}</strong></td>
            <td><a href="tel:${escapeHtml(item.phone)}">${escapeHtml(phone)}</a></td>
            <td>${escapeHtml(item.email || "—")}</td>
            <td>${formatDate(item.created_at)}</td>
            <td>${item.requests_count}</td>
          </tr>
        `;
      })
      .join("");
  }

  function applySearch(items) {
    const query = (document.getElementById("clients-search")?.value || "").trim().toLowerCase();
    if (!query) return items;

    return items.filter(
      (item) =>
        item.name.toLowerCase().includes(query) ||
        item.phone.toLowerCase().includes(query) ||
        (item.email || "").toLowerCase().includes(query)
    );
  }

  let allClients = [];

  async function loadClients() {
    const data = await Auth.apiRequest("/clients.php");
    allClients = data.clients || [];
    renderRows(applySearch(allClients));
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!document.getElementById("clients-tbody")) return;

    const search = document.getElementById("clients-search");
    if (search) {
      search.addEventListener("input", () => {
        renderRows(applySearch(allClients));
      });
    }

    try {
      await loadClients();
    } catch (error) {
      const tbody = document.getElementById("clients-tbody");
      if (tbody) {
        tbody.innerHTML =
          '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">Не удалось загрузить клиентов. Выйдите и войдите снова.</td></tr>';
      }
      console.error(error);
    }
  });
})();
