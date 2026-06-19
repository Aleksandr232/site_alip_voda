(function () {
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderPartner(partner) {
    return `
      <div class="partner-card">
        <div class="partner-card__logo">
          <img src="${escapeHtml(partner.logo_image)}" alt="${escapeHtml(partner.name)}">
        </div>
        <p>${escapeHtml(partner.name)}</p>
      </div>
    `;
  }

  async function loadPartners() {
    const container = document.getElementById("partners-list");
    if (!container) return;

    try {
      const response = await fetch("/api/partners.php", {
        headers: { Accept: "application/json" },
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Ошибка загрузки");
      }

      const partners = data.partners || [];

      if (!partners.length) {
        container.innerHTML =
          '<p class="partners__note">Партнёры скоро появятся.</p>';
        return;
      }

      container.innerHTML = partners.map(renderPartner).join("");
    } catch (error) {
      container.innerHTML =
        '<p class="partners__note">Не удалось загрузить партнёров.</p>';
      console.error(error);
    }
  }

  document.addEventListener("DOMContentLoaded", loadPartners);
})();
