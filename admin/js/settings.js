(function () {
  const contactsForm = document.getElementById("settings-contacts-form");
  const homepageForm = document.getElementById("settings-homepage-form");
  const calculatorForm = document.getElementById("settings-calculator-form");
  const passwordForm = document.getElementById("settings-password-form");
  const heroMainPreview = document.getElementById("hero-main-preview");
  const heroFloatPreview = document.getElementById("hero-float-preview");
  const heroMainInput = document.getElementById("hero-image-main-input");
  const heroFloatInput = document.getElementById("hero-image-float-input");
  const heroMainRemoveWrap = document.getElementById("hero-main-remove-wrap");
  const heroFloatRemoveWrap = document.getElementById("hero-float-remove-wrap");
  const heroMainRemoveBtn = document.getElementById("hero-main-remove");
  const heroFloatRemoveBtn = document.getElementById("hero-float-remove");

  const heroFallbackText = "Сейчас на сайте — изображение по умолчанию";

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function showMessage(text, isError = false) {
    alert(isError ? text : text);
  }

  function fillForm(form, settings) {
    if (!form || !settings) return;

    Object.entries(settings).forEach(([name, value]) => {
      const field = form.elements.namedItem(name);
      if (!field) return;

      if (field.type === "checkbox") {
        field.checked = value === "1" || value === 1 || value === true;
        return;
      }

      if (field.type === "file") {
        return;
      }

      if ("value" in field) {
        field.value = value;
      }
    });
  }

  function isVideoPath(path) {
    return /\.(mp4|webm|mov)(\?.*)?$/i.test(String(path));
  }

  function isVideoFile(file) {
    return file?.type?.startsWith("video/") || isVideoPath(file?.name);
  }

  function hasCustomHeroMedia(settings, key) {
    return Boolean(settings?.[key]);
  }

  function resetHeroRemoveFlags() {
    if (homepageForm?.elements.remove_hero_image_main) {
      homepageForm.elements.remove_hero_image_main.value = "0";
    }
    if (homepageForm?.elements.remove_hero_image_float) {
      homepageForm.elements.remove_hero_image_float.value = "0";
    }
  }

  function updateHeroRemoveButtons(settings) {
    if (heroMainRemoveWrap) {
      heroMainRemoveWrap.hidden = !hasCustomHeroMedia(settings, "hero_image_main");
    }
    if (heroFloatRemoveWrap) {
      heroFloatRemoveWrap.hidden = !hasCustomHeroMedia(settings, "hero_image_float");
    }
  }

  function markHeroMediaRemoved(fieldName, preview, removeWrap) {
    if (homepageForm?.elements[fieldName]) {
      homepageForm.elements[fieldName].value = "1";
    }
    if (preview) {
      preview.innerHTML = `<span class="admin-form__hint">${heroFallbackText}</span>`;
    }
    if (removeWrap) {
      removeWrap.hidden = true;
    }
  }

  function renderHeroPreview(container, mediaUrl, fallbackText) {
    if (!container) return;

    if (!mediaUrl) {
      container.innerHTML = `<span class="admin-form__hint">${fallbackText}</span>`;
      return;
    }

    container.innerHTML = isVideoPath(mediaUrl)
      ? `<video src="${escapeHtml(mediaUrl)}" controls></video>`
      : `<img src="${escapeHtml(mediaUrl)}" alt="">`;
  }

  function updateHeroPreviews(settings) {
    renderHeroPreview(heroMainPreview, settings.hero_image_main, heroFallbackText);
    renderHeroPreview(heroFloatPreview, settings.hero_image_float, heroFallbackText);
    updateHeroRemoveButtons(settings);
    resetHeroRemoveFlags();
  }

  function bindFilePreview(input, preview, removeFieldName, removeWrap) {
    input?.addEventListener("change", () => {
      const file = input.files?.[0];
      if (!file) return;

      if (homepageForm?.elements[removeFieldName]) {
        homepageForm.elements[removeFieldName].value = "0";
      }

      const url = URL.createObjectURL(file);
      preview.innerHTML = isVideoFile(file)
        ? `<video src="${url}" controls></video>`
        : `<img src="${url}" alt="">`;

      if (removeWrap) {
        removeWrap.hidden = false;
      }
    });
  }

  async function loadSettings() {
    const data = await Auth.apiRequest("/settings.php");
    const settings = data.settings || {};

    fillForm(contactsForm, settings);
    fillForm(homepageForm, settings);
    fillForm(calculatorForm, settings);
    updateHeroPreviews(settings);
  }

  async function submitJsonForm(form, action, successMessage) {
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    const payload = { action };

    Array.from(form.elements).forEach((field) => {
      if (!field.name || field.type === "submit") return;

      if (field.type === "checkbox") {
        payload[field.name] = field.checked ? "1" : "0";
        return;
      }

      if (field.type === "file") return;

      payload[field.name] = field.value;
    });

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.textContent;
      submitBtn.textContent = "Сохранение…";
    }

    try {
      const data = await Auth.apiRequest("/settings.php", {
        method: "POST",
        body: JSON.stringify(payload),
      });

      if (data.settings) {
        fillForm(contactsForm, data.settings);
        fillForm(homepageForm, data.settings);
        fillForm(calculatorForm, data.settings);
        updateHeroPreviews(data.settings);
      }

      if (action === "change_password") {
        form.reset();
      }

      showMessage(data.message || successMessage);
    } catch (error) {
      showMessage(error.message, true);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.originalText || "Сохранить";
      }
    }
  }

  async function submitHomepageForm(event) {
    event.preventDefault();
    if (!homepageForm) return;

    const submitBtn = homepageForm.querySelector('button[type="submit"]');
    const formData = new FormData(homepageForm);
    formData.set("action", "homepage");

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.textContent;
      submitBtn.textContent = "Сохранение…";
    }

    try {
      const data = await Auth.apiUpload("/settings.php", formData);

      if (data.settings) {
        fillForm(homepageForm, data.settings);
        updateHeroPreviews(data.settings);
      }

      if (heroMainInput) heroMainInput.value = "";
      if (heroFloatInput) heroFloatInput.value = "";

      showMessage(data.message || "Настройки главной сохранены");
    } catch (error) {
      showMessage(error.message, true);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtn.dataset.originalText || "Сохранить";
      }
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    bindFilePreview(heroMainInput, heroMainPreview, "remove_hero_image_main", heroMainRemoveWrap);
    bindFilePreview(heroFloatInput, heroFloatPreview, "remove_hero_image_float", heroFloatRemoveWrap);

    heroMainRemoveBtn?.addEventListener("click", () => {
      if (heroMainInput) heroMainInput.value = "";
      markHeroMediaRemoved("remove_hero_image_main", heroMainPreview, heroMainRemoveWrap);
    });

    heroFloatRemoveBtn?.addEventListener("click", () => {
      if (heroFloatInput) heroFloatInput.value = "";
      markHeroMediaRemoved("remove_hero_image_float", heroFloatPreview, heroFloatRemoveWrap);
    });

    try {
      await loadSettings();
    } catch (error) {
      console.error(error);
    }

    contactsForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitJsonForm(contactsForm, "contacts", "Контакты сохранены");
    });

    homepageForm?.addEventListener("submit", submitHomepageForm);

    calculatorForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitJsonForm(calculatorForm, "calculator", "Цены калькулятора сохранены");
    });

    passwordForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitJsonForm(passwordForm, "change_password", "Пароль изменён");
    });
  });
})();
