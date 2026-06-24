(function () {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function partnersApiUrl() {
    return document.querySelector('meta[name="api-partners"]')?.content || "/api/partners.php";
  }

  function logoBackgroundStyle(color) {
    const value = String(color || "").trim();
    if (!/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value)) {
      return "";
    }

    return ` style="background-color:${escapeHtml(value)}"`;
  }

  function renderPartner(partner) {
    const inner = `
      <div class="partner-card__logo"${logoBackgroundStyle(partner.logo_background)}>
        <img src="${escapeHtml(partner.logo_image)}" alt="${escapeHtml(partner.name)}" loading="lazy">
      </div>
      <p>${escapeHtml(partner.name)}</p>
    `;

    if (partner.website) {
      return `
        <a class="partner-card partner-card--link" href="${escapeHtml(partner.website)}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(partner.name)}">
          ${inner}
        </a>
      `;
    }

    return `<div class="partner-card">${inner}</div>`;
  }

  async function loadPartners() {
    const container = document.getElementById("partners-list");
    if (!container) return;

    try {
      const response = await fetch(partnersApiUrl(), {
        headers: { Accept: "application/json" },
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Ошибка загрузки");
      }

      const partners = (data.partners || []).filter((item) => item.status !== "hidden");

      if (!partners.length) {
        container.innerHTML = '<p class="partners__note">Партнёры скоро появятся.</p>';
        return;
      }

      container.innerHTML = partners.map(renderPartner).join("");
    } catch (error) {
      container.innerHTML = '<p class="partners__note">Не удалось загрузить партнёров.</p>';
      console.error(error);
    }
  }

  document.addEventListener("DOMContentLoaded", loadPartners);
})();
