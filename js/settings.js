(function () {
  function settingsApiUrl() {
    return document.querySelector('meta[name="api-settings"]')?.content || "/api/settings.php";
  }

  function phoneHref(phone) {
    const digits = String(phone).replace(/\D/g, "");
    if (!digits) return "#";

    if (digits.length === 11 && digits.startsWith("8")) {
      return `tel:+7${digits.slice(1)}`;
    }

    if (digits.length === 10) {
      return `tel:+7${digits}`;
    }

    return `tel:+${digits}`;
  }

  function isVisible(value) {
    return value === "1" || value === 1 || value === true || value === "true";
  }

  function isVideoPath(path) {
    return /\.(mp4|webm|mov)(\?.*)?$/i.test(String(path));
  }

  function applyHeroMedia(element, url) {
    if (!element || !url) return;

    const alt = element.getAttribute("alt") || "";
    const isVideo = isVideoPath(url);

    if (isVideo && element.tagName === "IMG") {
      const video = document.createElement("video");
      video.id = element.id;
      video.src = url;
      video.autoplay = true;
      video.muted = true;
      video.loop = true;
      video.playsInline = true;
      video.setAttribute("playsinline", "");
      element.replaceWith(video);
      return;
    }

    if (!isVideo && element.tagName === "VIDEO") {
      const img = document.createElement("img");
      img.id = element.id;
      img.src = url;
      img.alt = alt;
      element.replaceWith(img);
      return;
    }

    element.src = url;
  }

  function applySettings(settings) {
    if (!settings) return;

    const heroTitle = document.getElementById("hero-title");
    if (heroTitle && settings.hero_title) {
      heroTitle.textContent = settings.hero_title;
    }

    const heroLead = document.getElementById("hero-lead");
    if (heroLead && settings.hero_lead) {
      heroLead.textContent = settings.hero_lead;
    }

    const statYears = document.getElementById("hero-stat-years");
    if (statYears && settings.stat_years) {
      statYears.textContent = settings.stat_years;
    }

    const statObjects = document.getElementById("hero-stat-objects");
    if (statObjects && settings.stat_objects) {
      statObjects.textContent = settings.stat_objects;
    }

    const phoneItem = document.getElementById("site-phone-item");
    if (phoneItem) {
      if (isVisible(settings.phone_visible)) {
        phoneItem.hidden = false;
        const phoneEl = document.getElementById("site-phone");
        if (phoneEl && settings.phone) {
          phoneEl.textContent = settings.phone;
          phoneEl.href = phoneHref(settings.phone);
        }
      } else {
        phoneItem.hidden = true;
      }
    }

    const emailEl = document.getElementById("site-email");
    if (emailEl && settings.email) {
      emailEl.textContent = settings.email;
      emailEl.href = `mailto:${settings.email}`;
    }

    const hoursEl = document.getElementById("site-hours");
    if (hoursEl && settings.hours) {
      hoursEl.textContent = settings.hours;
    }

    const heroMain = document.getElementById("hero-image-main");
    if (heroMain && settings.hero_image_main) {
      applyHeroMedia(heroMain, settings.hero_image_main);
    }

    const heroFloat = document.getElementById("hero-image-float");
    if (heroFloat && settings.hero_image_float) {
      applyHeroMedia(heroFloat, settings.hero_image_float);
    }
  }

  async function loadSettings() {
    try {
      const response = await fetch(settingsApiUrl(), {
        headers: { Accept: "application/json" },
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Ошибка загрузки");
      }

      applySettings(data.settings);
    } catch (error) {
      console.error(error);
    }
  }

  document.addEventListener("DOMContentLoaded", loadSettings);
})();
