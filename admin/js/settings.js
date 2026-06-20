(function () {
  const contactsForm = document.getElementById("settings-contacts-form");
  const homepageForm = document.getElementById("settings-homepage-form");
  const passwordForm = document.getElementById("settings-password-form");

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

  async function loadSettings() {
    const data = await Auth.apiRequest("/settings.php");
    const settings = data.settings || {};

    fillForm(contactsForm, settings);
    fillForm(homepageForm, settings);
  }

  async function submitForm(form, action, successMessage) {
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    const payload = { action };

    Array.from(form.elements).forEach((field) => {
      if (!field.name || field.type === "submit") return;

      if (field.type === "checkbox") {
        payload[field.name] = field.checked ? "1" : "0";
        return;
      }

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

  document.addEventListener("DOMContentLoaded", async () => {
    try {
      await loadSettings();
    } catch (error) {
      console.error(error);
    }

    contactsForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitForm(contactsForm, "contacts", "Контакты сохранены");
    });

    homepageForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitForm(homepageForm, "homepage", "Настройки главной сохранены");
    });

    passwordForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      submitForm(passwordForm, "change_password", "Пароль изменён");
    });
  });
})();
