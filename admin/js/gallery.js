(function () {
  let items = [];
  let editingId = null;

  const grid = document.getElementById("gallery-grid");
  const form = document.getElementById("gallery-form");
  const modal = document.getElementById("modal-gallery");
  const modalTitle = document.getElementById("gallery-modal-title");
  const beforeInput = document.getElementById("gallery-before");
  const afterInput = document.getElementById("gallery-after");
  const beforePreview = document.getElementById("gallery-before-preview");
  const afterPreview = document.getElementById("gallery-after-preview");

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function statusLabel(status) {
    return status === "published" ? "Опубликовано" : "Скрыто";
  }

  function openModal(item = null) {
    editingId = item?.id ?? null;

    if (modalTitle) {
      modalTitle.textContent = item ? "Изменить пару фото" : "Добавить пару фото";
    }

    if (form) {
      form.reset();
      form.elements.title.value = item?.title ?? "";
      form.elements.description.value = item?.description ?? "";
      form.elements.sort.value = item?.sort_order ?? items.length + 1;
      form.elements.status.value = item?.status ?? "published";
    }

    if (beforeInput) beforeInput.required = !item;
    if (afterInput) afterInput.required = !item;

    if (beforePreview) {
      beforePreview.innerHTML = item?.before_image
        ? `<img src="${escapeHtml(item.before_image)}" alt="До">`
        : "";
    }

    if (afterPreview) {
      afterPreview.innerHTML = item?.after_image
        ? `<img src="${escapeHtml(item.after_image)}" alt="После">`
        : "";
    }

    modal?.classList.add("is-open");
  }

  function closeModal() {
    modal?.classList.remove("is-open");
    editingId = null;
  }

  function renderGrid() {
    if (!grid) return;

    if (!items.length) {
      grid.innerHTML =
        '<p style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted)">Пока нет фото. Нажмите «Добавить пару фото».</p>';
      return;
    }

    grid.innerHTML = items
      .map((item) => {
        const desc = item.description
          ? `<p class="admin-item-card__desc">${escapeHtml(item.description)}</p>`
          : "";

        return `
          <article class="admin-item-card" data-id="${item.id}">
            <div class="admin-item-card__image admin-item-card__image--pair">
              <img src="${escapeHtml(item.before_image)}" alt="До: ${escapeHtml(item.title)}">
              <img src="${escapeHtml(item.after_image)}" alt="После: ${escapeHtml(item.title)}">
            </div>
            <div class="admin-item-card__body">
              <h3>${escapeHtml(item.title)}</h3>
              ${desc}
              <p class="admin-item-card__meta">Порядок: ${item.sort_order} · ${statusLabel(item.status)}</p>
              <div class="admin-item-card__actions">
                <button class="btn btn--ghost btn--sm" type="button" data-edit="${item.id}">Изменить</button>
                <button class="btn btn--danger btn--sm" type="button" data-delete="${item.id}">Удалить</button>
              </div>
            </div>
          </article>
        `;
      })
      .join("");
  }

  async function loadItems() {
    const data = await Auth.apiRequest("/gallery.php?admin=1");
    items = data.items || [];
    renderGrid();
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
      const data = await Auth.apiUpload("/gallery.php", formData);
      const saved = data.item;

      if (editingId) {
        items = items.map((item) => (item.id === saved.id ? saved : item));
      } else {
        items.push(saved);
        items.sort((a, b) => a.sort_order - b.sort_order || b.id - a.id);
      }

      renderGrid();
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
    if (!confirm("Удалить эту пару фото?")) return;

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", String(id));

    try {
      await Auth.apiUpload("/gallery.php", formData);
      items = items.filter((item) => item.id !== id);
      renderGrid();
    } catch (error) {
      alert(error.message);
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!grid) return;

    document.querySelectorAll("[data-gallery-open]").forEach((btn) => {
      btn.addEventListener("click", () => openModal());
    });

    form?.addEventListener("submit", saveItem);

    grid.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit]");
      if (editBtn) {
        const id = Number(editBtn.getAttribute("data-edit"));
        const item = items.find((row) => row.id === id);
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
      grid.innerHTML =
        '<p style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text-muted)">Не удалось загрузить галерею. Войдите снова.</p>';
      console.error(error);
    }
  });
})();
