(function () {
  const sidebar = document.getElementById("admin-sidebar");
  const burger = document.getElementById("admin-burger");

  if (burger && sidebar) {
    burger.addEventListener("click", () => {
      sidebar.classList.toggle("is-open");
    });

    document.addEventListener("click", (e) => {
      if (sidebar.classList.contains("is-open") && !sidebar.contains(e.target) && !burger.contains(e.target)) {
        sidebar.classList.remove("is-open");
      }
    });
  }

  document.querySelectorAll("[data-modal-open]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-modal-open");
      const modal = document.getElementById(id);
      if (modal) modal.classList.add("is-open");
    });
  });

  document.querySelectorAll("[data-modal-close]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const modal = btn.closest(".admin-modal-overlay");
      if (modal) modal.classList.remove("is-open");
    });
  });

  document.querySelectorAll(".admin-modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) overlay.classList.remove("is-open");
    });
  });

  document.querySelectorAll(".admin-form[data-demo-submit]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const modal = form.closest(".admin-modal-overlay");
      if (modal) modal.classList.remove("is-open");
      alert("Сохранено (демо). Подключите PHP-бэкенд для реального сохранения.");
    });
  });
})();
