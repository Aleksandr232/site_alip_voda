(function () {
  const STATUS_LABELS = {
    new: "Новая",
    in_progress: "В работе",
    done: "Обработана",
  };

  const FILTER_MAP = {
    "": "",
    all: "",
    new: "new",
    "in-progress": "in_progress",
    done: "done",
  };

  let currentFilter = "";
  let allRequests = [];

  function formatDate(iso) {
    const date = new Date(iso.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return iso;

    const day = date.toLocaleDateString("ru-RU");
    const time = date.toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit" });
    return { day, time };
  }

  function formatPhone(phone) {
    const digits = phone.replace(/\D/g, "");
    if (digits.length === 11 && digits.startsWith("7")) {
      return `+7 ${digits.slice(1, 4)} ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
    }
    return phone;
  }

  function renderRows(items) {
    const tbody = document.getElementById("requests-tbody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Заявок пока нет</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((item) => {
        const { day, time } = formatDate(item.created_at);
        const phone = formatPhone(item.client_phone);

        return `
          <tr data-id="${item.id}">
            <td>${day}<br><small style="color:var(--text-muted)">${time}</small></td>
            <td><strong>${escapeHtml(item.client_name)}</strong></td>
            <td><a href="tel:${escapeHtml(item.client_phone)}">${escapeHtml(phone)}</a></td>
            <td>${escapeHtml(item.service_label)}</td>
            <td style="max-width:200px;font-size:0.875rem;color:var(--text-muted)">${escapeHtml(item.message || "—")}</td>
            <td>
              <select class="request-status" data-id="${item.id}" style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);font:inherit">
                <option value="new"${item.status === "new" ? " selected" : ""}>${STATUS_LABELS.new}</option>
                <option value="in_progress"${item.status === "in_progress" ? " selected" : ""}>${STATUS_LABELS.in_progress}</option>
                <option value="done"${item.status === "done" ? " selected" : ""}>${STATUS_LABELS.done}</option>
              </select>
            </td>
            <td>
              <button class="btn--icon btn--icon-danger request-delete" data-id="${item.id}" title="Удалить" type="button">✕</button>
            </td>
          </tr>
        `;
      })
      .join("");
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function applyFilters() {
    const query = (document.getElementById("requests-search")?.value || "").trim().toLowerCase();

    let items = allRequests;
    if (currentFilter) {
      items = items.filter((item) => item.status === currentFilter);
    }

    if (query) {
      items = items.filter(
        (item) =>
          item.client_name.toLowerCase().includes(query) ||
          item.client_phone.toLowerCase().includes(query) ||
          (item.message || "").toLowerCase().includes(query)
      );
    }

    renderRows(items);
  }

  async function loadRequests() {
    const query = currentFilter ? `?status=${encodeURIComponent(currentFilter)}` : "";
    const data = await Auth.apiRequest(`/requests.php${query}`);
    allRequests = data.requests || [];
    applyFilters();
  }

  async function updateStatus(id, status) {
    await Auth.apiRequest("/requests.php", {
      method: "POST",
      body: JSON.stringify({ action: "update", id, status }),
    });
  }

  async function deleteRequest(id) {
    if (!confirm("Удалить эту заявку?")) return;
    await Auth.apiRequest("/requests.php", {
      method: "POST",
      body: JSON.stringify({ action: "delete", id }),
    });
    allRequests = allRequests.filter((item) => item.id !== id);
    applyFilters();
  }

  function initFilters() {
    const filters = document.getElementById("requests-filters");
    if (!filters) return;

    filters.querySelectorAll("[data-status]").forEach((btn) => {
      btn.addEventListener("click", () => {
        filters.querySelectorAll("[data-status]").forEach((b) => b.classList.remove("blog-filter--active"));
        btn.classList.add("blog-filter--active");
        currentFilter = FILTER_MAP[btn.getAttribute("data-status")] ?? "";
        applyFilters();
      });
    });
  }

  function initSearch() {
    const search = document.getElementById("requests-search");
    if (!search) return;
    search.addEventListener("input", applyFilters);
  }

  function initTableActions() {
    const tbody = document.getElementById("requests-tbody");
    if (!tbody) return;

    tbody.addEventListener("change", async (e) => {
      const select = e.target.closest(".request-status");
      if (!select) return;

      const id = Number(select.getAttribute("data-id"));
      const status = select.value;

      try {
        await updateStatus(id, status);
        const item = allRequests.find((row) => row.id === id);
        if (item) item.status = status;
      } catch (error) {
        alert(error.message);
        applyFilters();
      }
    });

    tbody.addEventListener("click", async (e) => {
      const btn = e.target.closest(".request-delete");
      if (!btn) return;

      try {
        await deleteRequest(Number(btn.getAttribute("data-id")));
      } catch (error) {
        alert(error.message);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!document.getElementById("requests-tbody")) return;

    initFilters();
    initSearch();
    initTableActions();

    try {
      await loadRequests();
    } catch (error) {
      const tbody = document.getElementById("requests-tbody");
      if (tbody) {
        tbody.innerHTML =
          '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Не удалось загрузить заявки</td></tr>';
      }
      console.error(error);
    }
  });
})();
