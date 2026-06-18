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

  const form = document.getElementById("contact-form");
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const data = new FormData(form);
      const payload = {
        name: String(data.get("name") || "").trim(),
        phone: String(data.get("phone") || "").trim(),
        type: String(data.get("type") || "").trim(),
        message: String(data.get("message") || "").trim(),
      };

      if (!payload.name || !payload.phone) {
        alert("Заполните имя и телефон");
        return;
      }

      const apiSubmit =
        document.querySelector('meta[name="api-submit"]')?.content || "/api/requests.php";

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Отправка…";
      }

      try {
        const response = await fetch(apiSubmit, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify(payload),
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
