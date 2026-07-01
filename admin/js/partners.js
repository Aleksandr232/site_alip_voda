(function () {
  let items = [];
  let editingId = null;

  const DEFAULT_BG = "#F4F7FB";

  const tbody = document.getElementById("partners-tbody");
  const form = document.getElementById("partners-form");
  const modal = document.getElementById("modal-partner");
  const modalTitle = document.getElementById("partners-modal-title");
  const logoInput = document.getElementById("partners-logo");
  const logoPreview = document.getElementById("partners-logo-preview");
  const bgPicker = document.getElementById("partners-bg-picker");
  const bgText = document.getElementById("partners-bg-text");
  const bgReset = document.getElementById("partners-bg-reset");

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function isValidHexColor(value) {
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(String(value || "").trim());
  }

  function normalizeHexColor(value) {
    const color = String(value || "").trim();
    if (!isValidHexColor(color)) {
      return "";
    }

    if (color.length === 4) {
      return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`.toUpperCase();
    }

    return color.toUpperCase();
  }

  function logoBackgroundStyle(color) {
    const normalized = normalizeHexColor(color);
    return normalized ? ` style="background-color:${normalized}"` : "";
  }

  function updatePreviewBackground() {
    if (!logoPreview) return;

    const color = normalizeHexColor(bgText?.value || "");
    logoPreview.style.backgroundColor = color || DEFAULT_BG;
  }

  function setBackgroundValue(color) {
    const normalized = normalizeHexColor(color);

    if (bgText) {
      bgText.value = normalized;
    }

    if (bgPicker) {
      bgPicker.value = normalized || DEFAULT_BG;
    }

    updatePreviewBackground();
  }

  function statusLabel(status) {
    return status === "published" ? "На сайте" : "Скрыт";
  }

  function displayUrl(url) {
    return String(url).replace(/^https?:\/\//, "");
  }

  function renderLogoPreview(src) {
    if (!logoPreview) return;

    logoPreview.innerHTML = src ? `<img src="${escapeHtml(src)}" alt="">` : "";
    updatePreviewBackground();
  }

  function openModal(item = null) {
    editingId = item?.id ?? null;

    if (modalTitle) {
      modalTitle.textContent = item ? "Изменить клиента" : "Добавить клиента";
    }

    if (form) {
      form.reset();
      form.elements.name.value = item?.name ?? "";
      form.elements.website.value = item?.website ?? "";
      form.elements.sort.value = item?.sort_order ?? items.length + 1;
      form.elements.status.value = item?.status ?? "published";
    }

    setBackgroundValue(item?.logo_background || "");

    if (logoInput) logoInput.required = !item;

    renderLogoPreview(item?.logo_image || "");

    modal?.classList.add("is-open");
  }

  function closeModal() {
    modal?.classList.remove("is-open");
    editingId = null;
  }

  function renderTable() {
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Клиентов пока нет</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map(
        (item) => `
      <tr data-id="${item.id}">
        <td>
          <div class="admin-table__thumb admin-table__thumb--partner"${logoBackgroundStyle(item.logo_background)}>
            <img src="${escapeHtml(item.logo_image)}" alt="${escapeHtml(item.name)}">
          </div>
        </td>
        <td><strong>${escapeHtml(item.name)}</strong></td>
        <td>${item.website ? `<a href="${escapeHtml(item.website)}" target="_blank" rel="noopener">${escapeHtml(displayUrl(item.website))}</a>` : "—"}</td>
        <td>${item.sort_order}</td>
        <td>${statusLabel(item.status)}</td>
        <td>
          <div class="admin-table__actions">
            <button class="btn--icon" type="button" title="Изменить" data-edit="${item.id}">✎</button>
            <button class="btn--icon btn--icon-danger" type="button" title="Удалить" data-delete="${item.id}">✕</button>
          </div>
        </td>
      </tr>
    `
      )
      .join("");
  }

  async function loadItems() {
    const data = await Auth.apiRequest("/partners.php?admin=1");
    items = data.partners || [];
    renderTable();
  }

  async function saveItem(event) {
    event.preventDefault();

    const submitBtn = form?.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    if (!normalizeHexColor(bgText?.value || "")) {
      formData.set("logo_background", "");
    }

    if (editingId) {
      formData.append("action", "update");
      formData.append("id", String(editingId));
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Сохранение…";
    }

    try {
      const data = await Auth.apiUpload("/partners.php", formData);
      const saved = data.partner;

      if (editingId) {
        items = items.map((item) => (item.id === saved.id ? saved : item));
      } else {
        items.push(saved);
        items.sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
      }

      renderTable();
      closeModal();
    } catch (error) {
      alert(error.message);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Сохранить";
      }
    }
  }

  async function deleteItem(id) {
    if (!confirm("Удалить этого клиента?")) return;

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", String(id));

    try {
      await Auth.apiUpload("/partners.php", formData);
      items = items.filter((item) => item.id !== id);
      renderTable();
    } catch (error) {
      alert(error.message);
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!tbody) return;

    bgPicker?.addEventListener("input", () => {
      if (bgText) {
        bgText.value = bgPicker.value.toUpperCase();
      }
      updatePreviewBackground();
    });

    bgText?.addEventListener("input", () => {
      const normalized = normalizeHexColor(bgText.value);
      if (normalized && bgPicker) {
        bgPicker.value = normalized;
      }
      updatePreviewBackground();
    });

    bgReset?.addEventListener("click", () => {
      setBackgroundValue("");
    });

    logoInput?.addEventListener("change", () => {
      const file = logoInput.files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = () => {
        renderLogoPreview(String(reader.result || ""));
      };
      reader.readAsDataURL(file);
    });

    document.querySelectorAll("[data-partners-open]").forEach((btn) => {
      btn.addEventListener("click", () => openModal());
    });

    form?.addEventListener("submit", saveItem);

    tbody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit]");
      if (editBtn) {
        const item = items.find((row) => row.id === Number(editBtn.getAttribute("data-edit")));
        if (item) openModal(item);
        return;
      }

      const deleteBtn = e.target.closest("[data-delete]");
      if (deleteBtn) {
        deleteItem(Number(deleteBtn.getAttribute("data-delete")));
      }
    });

    try {
      await loadItems();
    } catch (error) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Не удалось загрузить клиентов</td></tr>';
      console.error(error);
    }
  });
})();
