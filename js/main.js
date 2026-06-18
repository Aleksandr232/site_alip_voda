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
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const data = new FormData(form);
      const name = data.get("name");
      alert(`Спасибо, ${name}! Заявка принята. Мы свяжемся с вами в ближайшее время.`);
      form.reset();
    });
  }

  document.querySelectorAll(".blog-filter").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".blog-filter").forEach((b) => b.classList.remove("blog-filter--active"));
      btn.classList.add("blog-filter--active");
    });
  });
})();
