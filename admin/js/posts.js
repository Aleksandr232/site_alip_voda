(function () {
  const TRANSLIT = {
    а: "a", б: "b", в: "v", г: "g", д: "d", е: "e", ё: "e", ж: "zh", з: "z", и: "i", й: "y",
    к: "k", л: "l", м: "m", н: "n", о: "o", п: "p", р: "r", с: "s", т: "t", у: "u", ф: "f",
    х: "h", ц: "ts", ч: "ch", ш: "sh", щ: "sch", ъ: "", ы: "y", ь: "", э: "e", ю: "yu", я: "ya",
  };

  let posts = [];
  let editingId = null;
  let slugManual = false;

  const tbody = document.getElementById("posts-tbody");
  const form = document.getElementById("posts-form");
  const modal = document.getElementById("modal-blog");
  const modalTitle = document.getElementById("posts-modal-title");
  const titleInput = document.getElementById("posts-title");
  const slugInput = document.getElementById("posts-slug");
  const coverPreview = document.getElementById("posts-cover-preview");
  const videoPreview = document.getElementById("posts-video-preview");
  const removeVideoWrap = document.getElementById("posts-remove-video-wrap");

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function slugify(text) {
    const lower = String(text).trim().toLowerCase();
    let result = "";

    for (const char of lower) {
      if (TRANSLIT[char]) {
        result += TRANSLIT[char];
      } else if (/[a-z0-9]/.test(char)) {
        result += char;
      } else {
        result += "-";
      }
    }

    return result.replace(/-+/g, "-").replace(/^-|-$/g, "") || "post";
  }

  function formatDate(iso) {
    const date = new Date(iso.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return iso;
    return date.toLocaleDateString("ru-RU");
  }

  function statusBadge(status) {
    return status === "published"
      ? '<span class="badge badge--success">Опубликовано</span>'
      : '<span class="badge badge--muted">Черновик</span>';
  }

  function openModal(post = null) {
    editingId = post?.id ?? null;
    slugManual = Boolean(post?.slug);

    if (modalTitle) {
      modalTitle.textContent = post ? "Редактировать статью" : "Новая статья";
    }

    if (form) {
      form.reset();
      form.elements.title.value = post?.title ?? "";
      form.elements.slug.value = post?.slug ?? "";
      form.elements.description.value = post?.description ?? "";
      form.elements.keywords.value = post?.keywords ?? "";
      form.elements.content.value = post?.content ?? "";
      form.elements.status.value = post?.status ?? "draft";
      form.elements.remove_video.value = "0";
    }

    if (coverPreview) {
      coverPreview.innerHTML = post?.cover_image
        ? `<img src="${escapeHtml(post.cover_image)}" alt="">`
        : "";
    }

    if (videoPreview) {
      videoPreview.innerHTML = post?.video_path
        ? `<video src="${escapeHtml(post.video_path)}" controls></video>`
        : "";
    }

    if (removeVideoWrap) {
      removeVideoWrap.hidden = !post?.video_path;
    }

    modal?.classList.add("is-open");
  }

  function closeModal() {
    modal?.classList.remove("is-open");
    editingId = null;
    slugManual = false;
  }

  function renderTable() {
    if (!tbody) return;

    if (!posts.length) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">Статей пока нет</td></tr>';
      return;
    }

    tbody.innerHTML = posts
      .map(
        (post) => `
      <tr data-id="${post.id}">
        <td>
          <strong>${escapeHtml(post.title)}</strong>
          ${post.description ? `<br><small style="color:var(--text-muted)">${escapeHtml(post.description.slice(0, 80))}${post.description.length > 80 ? "…" : ""}</small>` : ""}
        </td>
        <td><code>${escapeHtml(post.slug)}</code></td>
        <td>${formatDate(post.created_at)}</td>
        <td>${statusBadge(post.status)}</td>
        <td>
          <div class="admin-table__actions">
            <button class="btn--icon" type="button" title="Редактировать" data-edit="${post.id}">✎</button>
            <a class="btn--icon" href="/article/${escapeHtml(post.slug)}" target="_blank" title="Открыть">↗</a>
            <button class="btn--icon btn--icon-danger" type="button" title="Удалить" data-delete="${post.id}">✕</button>
          </div>
        </td>
      </tr>
    `
      )
      .join("");
  }

  async function loadPosts() {
    const data = await Auth.apiRequest("/posts.php?admin=1");
    posts = data.posts || [];
    renderTable();
  }

  async function savePost(event) {
    event.preventDefault();

    const submitBtn = form?.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    if (!formData.get("slug")) {
      formData.set("slug", slugify(String(formData.get("title") || "")));
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
      const data = await Auth.apiUpload("/posts.php", formData);
      const saved = data.post;

      if (editingId) {
        posts = posts.map((p) => (p.id === saved.id ? saved : p));
      } else {
        posts.unshift(saved);
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

  async function deletePost(id) {
    if (!confirm("Удалить эту статью?")) return;

    const formData = new FormData();
    formData.append("action", "delete");
    formData.append("id", String(id));

    try {
      await Auth.apiUpload("/posts.php", formData);
      posts = posts.filter((p) => p.id !== id);
      renderTable();
    } catch (error) {
      alert(error.message);
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (!tbody) return;

    document.querySelectorAll("[data-posts-open]").forEach((btn) => {
      btn.addEventListener("click", () => openModal());
    });

    titleInput?.addEventListener("input", () => {
      if (!slugManual && slugInput) {
        slugInput.value = slugify(titleInput.value);
      }
    });

    slugInput?.addEventListener("input", () => {
      slugManual = slugInput.value.trim() !== "";
    });

    form?.addEventListener("submit", savePost);

    document.getElementById("posts-remove-video")?.addEventListener("click", () => {
      if (form) form.elements.remove_video.value = "1";
      if (videoPreview) videoPreview.innerHTML = "";
      if (removeVideoWrap) removeVideoWrap.hidden = true;
    });

    tbody.addEventListener("click", (e) => {
      const editBtn = e.target.closest("[data-edit]");
      if (editBtn) {
        const post = posts.find((p) => p.id === Number(editBtn.getAttribute("data-edit")));
        if (post) openModal(post);
        return;
      }

      const deleteBtn = e.target.closest("[data-delete]");
      if (deleteBtn) {
        deletePost(Number(deleteBtn.getAttribute("data-delete")));
      }
    });

    try {
      await loadPosts();
    } catch (error) {
      tbody.innerHTML =
        '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">Не удалось загрузить статьи</td></tr>';
      console.error(error);
    }
  });
})();
