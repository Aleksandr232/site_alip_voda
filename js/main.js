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

  const form = document.getElementById("contact-form");
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const data = new FormData(form);
      const payload = {
        name: data.get("name"),
        phone: data.get("phone"),
        type: data.get("type"),
        message: data.get("message"),
      };

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Отправка…";
      }

      try {
        const apiUrl = new URL("api/requests", window.location.href).pathname;
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify(payload),
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
          throw new Error(result.message || "Не удалось отправить заявку");
        }

        alert(result.message || `Спасибо, ${payload.name}! Заявка принята.`);
        form.reset();
      } catch (error) {
        alert(error.message || "Ошибка отправки. Попробуйте позже или позвоните нам.");
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Отправить заявку";
        }
      }
    });
  }

  document.querySelectorAll(".blog-filter").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".blog-filter").forEach((b) => b.classList.remove("blog-filter--active"));
      btn.classList.add("blog-filter--active");
    });
  });
})();
