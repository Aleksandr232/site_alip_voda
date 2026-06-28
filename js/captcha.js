(function () {
  const apiUrl =
    document.querySelector('meta[name="api-captcha"]')?.content || "/api/captcha.php";

  const TURNSTILE_SCRIPT = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";

  let siteKey = "";
  let scriptLoading = null;

  function loadTurnstileScript() {
    if (window.turnstile) {
      return Promise.resolve();
    }

    if (scriptLoading) {
      return scriptLoading;
    }

    scriptLoading = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = TURNSTILE_SCRIPT;
      script.async = true;
      script.defer = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error("Не удалось загрузить Cloudflare Turnstile"));
      document.head.appendChild(script);
    });

    return scriptLoading;
  }

  async function renderWidget(form, block) {
    await loadTurnstileScript();

    if (!window.turnstile || !siteKey) {
      throw new Error("Cloudflare Turnstile недоступен");
    }

    const widgetId = window.turnstile.render(block, {
      sitekey: siteKey,
      theme: "light",
      callback: (token) => {
        form.dataset.captchaToken = token;
      },
      "expired-callback": () => {
        form.dataset.captchaToken = "";
      },
      "error-callback": () => {
        form.dataset.captchaToken = "";
      },
    });

    form.dataset.captchaWidgetId = String(widgetId);
  }

  async function initForm(form) {
    if (form.dataset.captchaReady === "1") {
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) {
      return;
    }

    form.dataset.captchaReady = "1";

    if (!siteKey) {
      form.dataset.captchaEnabled = "0";
      return;
    }

    form.dataset.captchaEnabled = "1";

    const block = document.createElement("div");
    block.className = "contact__captcha-widget";
    form.insertBefore(block, submitBtn);

    try {
      await renderWidget(form, block);
    } catch (error) {
      block.innerHTML = '<p class="contact__captcha-error">Не удалось загрузить капчу</p>';
      console.error(error);
    }
  }

  async function bootstrap() {
    try {
      const response = await fetch(apiUrl, {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const result = await response.json();

      if (response.ok && result.success === true && result.enabled === true && result.site_key) {
        siteKey = result.site_key;
      }
    } catch (error) {
      console.error(error);
    }

    await Promise.all(
      Array.from(document.querySelectorAll(".contact__form")).map((form) => initForm(form))
    );
  }

  window.getCaptchaToken = function getCaptchaToken(form) {
    if (!form || form.dataset.captchaEnabled !== "1") {
      return "";
    }

    const widgetId = form.dataset.captchaWidgetId;
    const fromWidget =
      widgetId && window.turnstile ? window.turnstile.getResponse(widgetId) || "" : "";

    return String(form.dataset.captchaToken || fromWidget || "").trim();
  };

  window.refreshContactCaptcha = function refreshContactCaptcha(form) {
    if (!form || form.dataset.captchaEnabled !== "1" || !window.turnstile) {
      return;
    }

    form.dataset.captchaToken = "";

    const widgetId = form.dataset.captchaWidgetId;
    if (widgetId) {
      window.turnstile.reset(widgetId);
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    bootstrap().catch((error) => console.error(error));
  });
})();
