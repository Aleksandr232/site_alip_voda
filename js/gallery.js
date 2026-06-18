(function () {
  function initCompareSliders(root) {
    root.querySelectorAll("[data-compare]").forEach((compare) => {
      const slider = compare.querySelector(".compare__slider");
      if (!slider || compare.dataset.compareReady === "1") return;

      const update = (value) => {
        compare.style.setProperty("--pos", `${value}%`);
      };

      update(slider.value);
      slider.addEventListener("input", (e) => update(e.target.value));
      compare.dataset.compareReady = "1";
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderItem(item) {
    const title = escapeHtml(item.title);
    const description = item.description
      ? `<p>${escapeHtml(item.description)}</p>`
      : "";

    return `
      <div class="compare-wrap">
        <div class="compare" data-compare>
          <img class="compare__img compare__img--after" src="${escapeHtml(item.after_image)}" alt="${title} — после">
          <img class="compare__img compare__img--before" src="${escapeHtml(item.before_image)}" alt="${title} — до">
          <input type="range" class="compare__slider" min="0" max="100" value="50" aria-label="Сравнение до и после: ${title}">
          <div class="compare__handle" aria-hidden="true"><span></span></div>
          <span class="compare__label compare__label--before">До</span>
          <span class="compare__label compare__label--after">После</span>
        </div>
        <div class="compare__caption">
          <h3>${title}</h3>
          ${description}
        </div>
      </div>
    `;
  }

  async function loadGallery() {
    const container = document.getElementById("gallery-container");
    if (!container) return;

    try {
      const response = await fetch("/api/gallery.php", {
        headers: { Accept: "application/json" },
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Ошибка загрузки");
      }

      const items = data.items || [];

      if (!items.length) {
        container.innerHTML =
          '<p class="gallery__loading">Фотографии работ скоро появятся.</p>';
        return;
      }

      container.innerHTML = items.map(renderItem).join("");
      initCompareSliders(container);
    } catch (error) {
      container.innerHTML =
        '<p class="gallery__loading">Не удалось загрузить галерею.</p>';
      console.error(error);
    }
  }

  document.addEventListener("DOMContentLoaded", loadGallery);

  window.SkyClinGallery = { initCompareSliders };
})();
