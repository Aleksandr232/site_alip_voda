(function () {
  let items = [];
  let editingId = null;

  const tbody = document.getElementById("partners-tbody");
  const form = document.getElementById("partners-form");
  const modal = document.getElementById("modal-partner");
  const modalTitle = document.getElementById("partners-modal-title");
  const logoInput = document.getElementById("partners-logo");
  const logoPreview = document.getElementById("partners-logo-preview");

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function statusLabel(status) {
    return status === "published" ? "На сайте" : "Скрыт";
  }

  function displayUrl(url) {
    return String(url).replace(/^https?:\/\//, "");
  }

  function openModal(item = null) {
    editingId = item?.id ?? null;

    if (modalTitle) {
      modalTitle.textContent = item ? "Изменить партнёра" : "Добавить партнёра";
    }

    if (form) {
      form.reset();
      form.elements.name.value = item?.name ?? "";
      form.elements.website.value = item?.website ?? "";
      form.elements.sort.value = item?.sort_order ?? items.length + 1;
      form.elements.status.value = item?.status ?? "published";
    }

    if (logoInput) logoInput.required = !item;

    if (logoPreview) {
      logoPreview.innerHTML = item?.logo_image
        ? `<img src="${escapeHtml(item.logo_image)}" alt="">`
        : "";
    }

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
        '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Партнёров пока нет</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map(
        (item) => `
      <tr data-id="${item.id}">
        <td>
          <div class="admin-table__thumb">
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
    if (!confirm("Удалить этого партнёра?")) return;

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
        '<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">Не удалось загрузить партнёров</td></tr>';
      console.error(error);
    }
  });
})();
