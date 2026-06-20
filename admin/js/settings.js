(function () {
  const contactsForm = document.getElementById("settings-contacts-form");
  const homepageForm = document.getElementById("settings-homepage-form");
  const passwordForm = document.getElementById("settings-password-form");
  const heroMainPreview = document.getElementById("hero-main-preview");
  const heroFloatPreview = document.getElementById("hero-float-preview");
  const heroMainInput = document.getElementById("hero-image-main-input");
  const heroFloatInput = document.getElementById("hero-image-float-input");

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

      if ("value" in field) {
        field.value = value;
      }
    });
  }

  function renderHeroPreview(container, imageUrl, fallbackText) {
    if (!container) return;

    container.innerHTML = imageUrl
      ? `<img src="${escapeHtml(imageUrl)}" alt="">`
      : `<span class="admin-form__hint">${fallbackText}</span>`;
  }

  function updateHeroPreviews(settings) {
    renderHeroPreview(
      heroMainPreview,
      settings.hero_image_main,
      "Сейчас на сайте — фото по умолчанию"
    );
    renderHeroPreview(
      heroFloatPreview,
      settings.hero_image_float,
      "Сейчас на сайте — фото по умолчанию"
    );
  }

  function bindFilePreview(input, preview) {
    input?.addEventListener("change", () => {
      const file = input.files?.[0];
      if (!file) return;

      const url = URL.createObjectURL(file);
      preview.innerHTML = `<img src="${url}" alt="">`;
    });
  }

  async function loadSettings() {
    const data = await Auth.apiRequest("/settings.php");
    const settings = data.settings || {};

    fillForm(contactsForm, settings);
    fillForm(homepageForm, settings);
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
    bindFilePreview(heroMainInput, heroMainPreview);
    bindFilePreview(heroFloatInput, heroFloatPreview);

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

    passwordForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitJsonForm(passwordForm, "change_password", "Пароль изменён");
    });
  });
})();
