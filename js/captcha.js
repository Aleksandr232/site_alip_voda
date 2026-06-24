(function () {
  const apiUrl =
    document.querySelector('meta[name="api-captcha"]')?.content || "/api/captcha.php";

  function createCaptchaBlock() {
    const block = document.createElement("div");
    block.className = "contact__captcha";
    block.innerHTML = `
      <label class="contact__captcha-field">
        <span class="contact__captcha-label">Сколько будет <strong data-captcha-question>…</strong>?</span>
        <input
          type="text"
          name="captcha_answer"
          inputmode="numeric"
          pattern="[0-9]*"
          autocomplete="off"
          required
          placeholder="Ответ"
          aria-label="Ответ на проверочный вопрос"
        >
      </label>
      <button
        type="button"
        class="contact__captcha-refresh"
        data-captcha-refresh
        title="Другой пример"
        aria-label="Обновить проверочный вопрос"
      >↻</button>
    `;
    return block;
  }

  async function loadCaptcha(block) {
    const questionEl = block.querySelector("[data-captcha-question]");
    const answerInput = block.querySelector('input[name="captcha_answer"]');
    const refreshBtn = block.querySelector("[data-captcha-refresh]");

    if (!questionEl || !answerInput) {
      return;
    }

    if (refreshBtn) {
      refreshBtn.disabled = true;
    }

    try {
      const response = await fetch(apiUrl, {
        headers: { Accept: "application/json" },
        cache: "no-store",
        credentials: "same-origin",
      });

      const result = await response.json();
      if (!response.ok || result.success !== true || !result.question) {
        throw new Error(result.message || "Не удалось загрузить проверку");
      }

      questionEl.textContent = result.question;
      answerInput.value = "";
      block.classList.remove("contact__captcha--error");
    } catch (error) {
      questionEl.textContent = "?";
      block.classList.add("contact__captcha--error");
      console.error(error);
    } finally {
      if (refreshBtn) {
        refreshBtn.disabled = false;
      }
    }
  }

  function initForm(form) {
    if (form.dataset.captchaReady === "1") {
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) {
      return;
    }

    form.dataset.captchaReady = "1";

    const block = createCaptchaBlock();
    form.insertBefore(block, submitBtn);

    block.querySelector("[data-captcha-refresh]")?.addEventListener("click", () => {
      loadCaptcha(block);
    });

    form.addEventListener("reset", () => {
      window.setTimeout(() => loadCaptcha(block), 0);
    });

    loadCaptcha(block);
  }

  document.querySelectorAll(".contact__form").forEach(initForm);

  window.refreshContactCaptcha = function refreshContactCaptcha(form) {
    const block = form?.querySelector(".contact__captcha");
    if (block) {
      loadCaptcha(block);
    }
  };
})();
