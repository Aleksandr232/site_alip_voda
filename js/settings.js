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
