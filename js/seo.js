(function () {
  function siteOrigin() {
    const meta = document.querySelector('meta[name="site-url"]');
    const configured = meta?.content?.trim();
    if (configured) {
      return configured.replace(/\/$/, "");
    }
    return window.location.origin;
  }

  function absoluteUrl(path) {
    const origin = siteOrigin();
    if (!path) return origin + "/apple-touch-icon.png";
    if (/^https?:\/\//i.test(path)) return path;
    return origin + (path.startsWith("/") ? path : "/" + path);
  }

  function canonicalUrl(path) {
    if (!path || path === "/") {
      return siteOrigin() + "/";
    }
    return absoluteUrl(path).replace(/\/$/, "");
  }

  function setMeta(selector, content) {
    if (!content) return;
    let node = document.querySelector(selector);
    if (!node) {
      node = document.createElement("meta");
      const isProperty = selector.includes("property=");
      if (isProperty) {
        node.setAttribute("property", selector.match(/property="([^"]+)"/)[1]);
      } else {
        node.setAttribute("name", selector.match(/name="([^"]+)"/)[1]);
      }
      document.head.appendChild(node);
    }
    node.setAttribute("content", content);
  }

  function setCanonical(url) {
    let link = document.querySelector('link[rel="canonical"]');
    if (!link) {
      link = document.createElement("link");
      link.setAttribute("rel", "canonical");
      document.head.appendChild(link);
    }
    link.setAttribute("href", url);
  }

  function applyOpenGraph(meta) {
    const image = absoluteUrl(meta.image || "/apple-touch-icon.png");
    const url = meta.url || canonicalUrl(window.location.pathname);

    document.title = meta.title || document.title;
    setMeta('meta[name="description"]', meta.description || "");
    setCanonical(url);

    setMeta('meta[property="og:type"]', meta.type || "website");
    setMeta('meta[property="og:site_name"]', meta.siteName || "СкайКлин");
    setMeta('meta[property="og:locale"]', "ru_RU");
    setMeta('meta[property="og:title"]', meta.title || "");
    setMeta('meta[property="og:description"]', meta.description || "");
    setMeta('meta[property="og:url"]', url);
    setMeta('meta[property="og:image"]', image);
    setMeta('meta[name="twitter:card"]', "summary_large_image");
    setMeta('meta[name="twitter:title"]', meta.title || "");
    setMeta('meta[name="twitter:description"]', meta.description || "");
    setMeta('meta[name="twitter:image"]', image);

    if (meta.publishedTime) {
      setMeta('meta[property="article:published_time"]', meta.publishedTime);
    }
    if (meta.modifiedTime) {
      setMeta('meta[property="article:modified_time"]', meta.modifiedTime);
    }
  }

  function toIso8601(value) {
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? "" : date.toISOString();
  }

  function applyArticleMeta(post) {
    if (!post) return;

    const title = `${post.title} — СкайКлин`;
    const description = post.description || "Статья блога СкайКлин";
    const url = canonicalUrl(`/article/${post.slug}`);

    applyOpenGraph({
      title,
      description,
      url,
      image: post.cover_image || "/apple-touch-icon.png",
      type: "article",
      publishedTime: toIso8601(post.created_at),
      modifiedTime: toIso8601(post.updated_at || post.created_at),
    });
  }

  window.SkyClinSeo = {
    applyOpenGraph,
    applyArticleMeta,
  };
})();
