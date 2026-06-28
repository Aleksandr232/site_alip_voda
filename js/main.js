(function () {
  const burger = document.getElementById("burger");
  const nav = document.getElementById("nav");

  if (burger && nav) {
    burger.addEventListener("click", () => {
      const isOpen = nav.classList.toggle("is-open");
      burger.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        nav.classList.remove("is-open");
        burger.setAttribute("aria-expanded", "false");
      });
    });
  }

  document.querySelectorAll("[data-compare]").forEach((compare) => {
    const slider = compare.querySelector(".compare__slider");

    const update = (value) => {
      compare.style.setProperty("--pos", `${value}%`);
    };

    if (slider) {
      update(slider.value);
      slider.addEventListener("input", (e) => update(e.target.value));
    }
  });

  // Галерея «до/после» загружается из API в js/gallery.js

  document.querySelectorAll(".contact__form").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const formData = new FormData(form);
      const articleTitle = form.dataset.articleTitle || "";

      if (articleTitle) {
        const prefix = `Вопрос по статье «${articleTitle}»`;
        const message = String(formData.get("message") || "").trim();
        formData.set("message", message ? `${prefix}: ${message}` : prefix);
      }

      const name = String(formData.get("name") || "").trim();
      const phone = String(formData.get("phone") || "").trim();
      const captchaToken =
        (typeof window.getCaptchaToken === "function" ? window.getCaptchaToken(form) : "") ||
        String(formData.get("captcha_token") || formData.get("cf-turnstile-response") || "").trim();

      if (!name || !phone) {
        alert("Заполните имя и телефон");
        return;
      }

      if (form.dataset.captchaEnabled === "1" && !captchaToken) {
        alert("Подтвердите, что вы не робот");
        return;
      }

      if (captchaToken) {
        formData.set("captcha_token", captchaToken);
      }

      const apiSubmit =
        document.querySelector('meta[name="api-submit"]')?.content || "/api/requests.php";

      const defaultLabel = submitBtn?.dataset.defaultLabel || submitBtn?.textContent || "Отправить заявку";

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Отправка…";
      }

      try {
        const response = await fetch(apiSubmit, {
          method: "POST",
          headers: {
            Accept: "application/json",
          },
          credentials: "same-origin",
          body: formData,
        });

        const contentType = response.headers.get("content-type") || "";
        if (!contentType.includes("application/json")) {
          throw new Error(
            "Сервер не ответил JSON. Проверьте, что на хостинг загружены папки api/ и src/, и откройте /api/install"
          );
        }

        const result = await response.json();

        if (!response.ok || result.success !== true) {
          throw new Error(result.message || `Ошибка сервера (${response.status})`);
        }

        if (!result.request?.id) {
          throw new Error("Заявка не сохранилась на сервере. Откройте /api/install для проверки базы.");
        }

        alert(result.message || `Спасибо, ${name}! Заявка принята.`);
        form.reset();
        if (typeof window.refreshContactCaptcha === "function") {
          window.refreshContactCaptcha(form);
        }
      } catch (error) {
        if (typeof window.refreshContactCaptcha === "function") {
          window.refreshContactCaptcha(form);
        }
        alert(error.message || "Ошибка отправки. Попробуйте позже или позвоните нам.");
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = defaultLabel;
        }
      }
    });
  });

  document.querySelectorAll(".blog-filter").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".blog-filter").forEach((b) => b.classList.remove("blog-filter--active"));
      btn.classList.add("blog-filter--active");
    });
  });
})();
