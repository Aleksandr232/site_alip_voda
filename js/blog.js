(function () {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatDate(iso) {
    const date = new Date(iso.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return { display: iso, datetime: iso };
    return {
      display: date.toLocaleDateString("ru-RU", { day: "numeric", month: "long", year: "numeric" }),
      datetime: date.toISOString().slice(0, 10),
    };
  }

  function getSlugFromUrl() {
    const path = window.location.pathname.replace(/\\/g, "/");
    const match = path.match(/\/article\/([a-z0-9][a-z0-9-]*)\/?$/i);
    if (match) return match[1].toLowerCase();

    return new URLSearchParams(window.location.search).get("slug") || "";
  }

  function renderContent(text) {
    if (!text) return "";
    if (/<[a-z][\s\S]*>/i.test(text)) {
      return text;
    }

    return text
      .split(/\n\n+/)
      .map((block) => `<p>${escapeHtml(block).replace(/\n/g, "<br>")}</p>`)
      .join("");
  }

  function renderBlogCard(post, large = false) {
    const { display, datetime } = formatDate(post.created_at);
    const image = post.cover_image
      ? `<img src="${escapeHtml(post.cover_image)}" alt="${escapeHtml(post.title)}">`
      : `<div class="blog-card__placeholder"></div>`;
    const largeClass = large ? " blog-card--large" : "";
    const link = `/article/${encodeURIComponent(post.slug)}`;

    return `
      <article class="blog-card${largeClass}">
        <a href="${link}" class="blog-card__image">${image}</a>
        <div class="blog-card__body">
          <div class="blog-card__meta">
            <time class="blog-card__date" datetime="${datetime}">${display}</time>
          </div>
          <h2><a href="${link}">${escapeHtml(post.title)}</a></h2>
          ${post.description ? `<p>${escapeHtml(post.description)}</p>` : ""}
          <a href="${link}" class="blog-card__link">${large ? "Читать статью →" : "Читать →"}</a>
        </div>
      </article>
    `;
  }

  async function fetchPosts() {
    const response = await fetch("/api/posts.php", { headers: { Accept: "application/json" } });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || "Ошибка загрузки");
    }
    return data.posts || [];
  }

  async function fetchPost(slug) {
    const response = await fetch(`/api/posts.php?slug=${encodeURIComponent(slug)}`, {
      headers: { Accept: "application/json" },
    });
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || "Статья не найдена");
    }
    return data.post;
  }

  async function loadBlogList() {
    const grid = document.getElementById("blog-grid");
    if (!grid) return;

    try {
      const posts = await fetchPosts();

      if (!posts.length) {
        grid.innerHTML = '<p class="gallery__loading">Статей пока нет.</p>';
        return;
      }

      const [first, ...rest] = posts;
      grid.innerHTML = renderBlogCard(first, true) + rest.map((p) => renderBlogCard(p)).join("");
    } catch (error) {
      grid.innerHTML = '<p class="gallery__loading">Не удалось загрузить статьи.</p>';
      console.error(error);
    }
  }

  async function loadBlogPreview() {
    const grid = document.getElementById("blog-preview-grid");
    if (!grid) return;

    try {
      const posts = (await fetchPosts()).slice(0, 2);
      if (!posts.length) {
        grid.innerHTML = "";
        return;
      }
      grid.innerHTML = posts.map((p) => renderBlogCard(p)).join("");
    } catch (error) {
      console.error(error);
    }
  }

  async function loadArticle() {
    const slug = getSlugFromUrl();
    if (!slug || !document.getElementById("article-root")) return;

    try {
      const post = await fetchPost(slug);
      const { display, datetime } = formatDate(post.created_at);

      document.title = `${post.title} — СкайКлин`;

      const metaDesc = document.querySelector('meta[name="description"]');
      if (metaDesc && post.description) {
        metaDesc.setAttribute("content", post.description);
      }

      const metaKeywords = document.querySelector('meta[name="keywords"]');
      if (metaKeywords && post.keywords) {
        metaKeywords.setAttribute("content", post.keywords);
      }

      const titleEl = document.getElementById("article-title");
      const leadEl = document.getElementById("article-lead");
      const dateEl = document.getElementById("article-date");
      const coverEl = document.getElementById("article-cover");
      const videoEl = document.getElementById("article-video");
      const contentEl = document.getElementById("article-content");

      if (titleEl) titleEl.textContent = post.title;
      if (leadEl) leadEl.textContent = post.description || "";
      if (dateEl) {
        dateEl.textContent = display;
        dateEl.setAttribute("datetime", datetime);
      }

      if (coverEl) {
        if (post.cover_image) {
          coverEl.innerHTML = `<img src="${escapeHtml(post.cover_image)}" alt="${escapeHtml(post.title)}">`;
          coverEl.hidden = false;
        } else {
          coverEl.innerHTML = "";
          coverEl.hidden = true;
        }
      }

      if (videoEl) {
        if (post.video_path) {
          videoEl.innerHTML = `<video controls playsinline src="${escapeHtml(post.video_path)}"></video>`;
          videoEl.hidden = false;
        } else {
          videoEl.innerHTML = "";
          videoEl.hidden = true;
        }
      }

      if (contentEl) {
        contentEl.innerHTML = renderContent(post.content || "");
      }
    } catch (error) {
      const root = document.getElementById("article-root");
      if (root) {
        root.innerHTML =
          '<div class="container" style="padding:80px 0;text-align:center"><h1>Статья не найдена</h1><p><a href="/blog">← Все статьи</a></p></div>';
      }
      console.error(error);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    loadBlogList();
    loadBlogPreview();
    loadArticle();
  });
})();
