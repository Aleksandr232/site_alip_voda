const Auth = (() => {
  const TOKEN_KEY = "alip_auth_token";
  const USER_KEY = "alip_auth_user";
  const REMEMBER_KEY = "alip_auth_remember";

  const ADMIN_PAGES = new Set([
    "dashboard",
    "requests",
    "posts",
    "reviews",
    "gallery",
    "partners",
    "settings",
  ]);

  function currentSlug() {
    const path = window.location.pathname.replace(/\\/g, "/");
    const parts = path.split("/").filter(Boolean);
    return parts[parts.length - 1] || "";
  }

  function apiBase() {
    return new URL("../api", window.location.href).pathname.replace(/\/$/, "");
  }

  function getToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
  }

  function getUser() {
    const raw = localStorage.getItem(USER_KEY) || sessionStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  }

  function saveSession(token, user, remember) {
    const storage = remember ? localStorage : sessionStorage;
    const other = remember ? sessionStorage : localStorage;

    storage.setItem(TOKEN_KEY, token);
    storage.setItem(USER_KEY, JSON.stringify(user));
    localStorage.setItem(REMEMBER_KEY, remember ? "1" : "0");

    other.removeItem(TOKEN_KEY);
    other.removeItem(USER_KEY);
  }

  function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    localStorage.removeItem(REMEMBER_KEY);
  }

  async function apiRequest(endpoint, options = {}) {
    const headers = {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(options.headers || {}),
    };

    const token = getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`${apiBase()}${endpoint}`, {
      ...options,
      headers,
    });

    let data = {};
    try {
      data = await response.json();
    } catch {
      data = {};
    }

    if (!response.ok) {
      throw new Error(data.message || "Ошибка запроса к API");
    }

    return data;
  }

  async function login(email, password, remember = false) {
    const data = await apiRequest("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });

    saveSession(data.token, data.user, remember);
    return data;
  }

  async function register(name, email, password) {
    const data = await apiRequest("/auth/register", {
      method: "POST",
      body: JSON.stringify({ name, email, password }),
    });

    saveSession(data.token, data.user, true);
    return data;
  }

  async function me() {
    return apiRequest("/auth/me");
  }

  async function logout() {
    try {
      await apiRequest("/auth/logout", { method: "POST" });
    } catch {
      // ignore
    }
    clearSession();
  }

  function isLoginPage() {
    return currentSlug() === "login";
  }

  function isAdminPage() {
    return ADMIN_PAGES.has(currentSlug());
  }

  function redirectToLogin() {
    window.location.href = "login";
  }

  async function guardAdminPages() {
    if (isLoginPage()) {
      const token = getToken();
      if (token) {
        try {
          await me();
          window.location.href = "dashboard";
        } catch {
          clearSession();
        }
      }
      return;
    }

    if (!isAdminPage()) {
      return;
    }

    const token = getToken();
    if (!token) {
      redirectToLogin();
      return;
    }

    try {
      const data = await me();
      updateUserUI(data.user);
    } catch {
      clearSession();
      redirectToLogin();
    }
  }

  function updateUserUI(user) {
    if (!user) return;

    const nameEl = document.querySelector(".admin-sidebar__name");
    const roleEl = document.querySelector(".admin-sidebar__role");
    const avatarEl = document.querySelector(".admin-sidebar__avatar");

    if (nameEl) nameEl.textContent = user.name;
    if (roleEl) roleEl.textContent = user.email;

    if (avatarEl) {
      const parts = user.name.trim().split(/\s+/);
      const initials = parts
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || "")
        .join("");
      avatarEl.textContent = initials || "АД";
    }
  }

  function showError(message) {
    const errorEl = document.getElementById("auth-error");
    if (!errorEl) return;
    errorEl.textContent = message;
    errorEl.hidden = !message;
  }

  function initAuthForms() {
    const loginForm = document.getElementById("login-form");
    const registerForm = document.getElementById("register-form");
    const tabs = document.querySelectorAll("[data-auth-tab]");
    const title = document.getElementById("auth-title");
    const subtitle = document.getElementById("auth-subtitle");

    function setActiveTab(mode) {
      const isLogin = mode === "login";

      tabs.forEach((tab) => {
        tab.classList.toggle("auth-tab--active", tab.getAttribute("data-auth-tab") === mode);
      });

      if (loginForm) {
        loginForm.hidden = !isLogin;
        loginForm.classList.toggle("auth-form--active", isLogin);
      }

      if (registerForm) {
        registerForm.hidden = isLogin;
        registerForm.classList.toggle("auth-form--active", !isLogin);
      }

      if (title) {
        title.textContent = isLogin ? "Вход в админ-панель" : "Регистрация";
      }

      if (subtitle) {
        subtitle.textContent = isLogin
          ? "Управление контентом сайта"
          : "Первый пользователь получит роль администратора";
      }

      showError("");
    }

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        setActiveTab(tab.getAttribute("data-auth-tab"));
      });
    });

    setActiveTab("login");

    if (loginForm) {
      loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        showError("");

        const formData = new FormData(loginForm);
        const submitBtn = document.getElementById("auth-submit");
        submitBtn.disabled = true;
        submitBtn.textContent = "Вход…";

        try {
          await login(
            formData.get("email"),
            formData.get("password"),
            formData.get("remember") === "on"
          );
          window.location.href = "dashboard";
        } catch (error) {
          showError(error.message);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = "Войти";
        }
      });
    }

    if (registerForm) {
      registerForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        showError("");

        const formData = new FormData(registerForm);
        const password = formData.get("password");
        const confirm = formData.get("password_confirm");

        if (password !== confirm) {
          showError("Пароли не совпадают");
          return;
        }

        const submitBtn = registerForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = "Регистрация…";

        try {
          await register(formData.get("name"), formData.get("email"), password);
          window.location.href = "dashboard";
        } catch (error) {
          showError(error.message);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = "Зарегистрироваться";
        }
      });
    }
  }

  function initLogout() {
    document.querySelectorAll(".admin-sidebar__logout").forEach((link) => {
      link.addEventListener("click", async (e) => {
        e.preventDefault();
        await logout();
        redirectToLogin();
      });
    });
  }

  return {
    guardAdminPages,
    initAuthForms,
    initLogout,
    getUser,
    logout,
    apiRequest,
    apiBase,
  };
})();

document.addEventListener("DOMContentLoaded", () => {
  Auth.initAuthForms();
  Auth.initLogout();
  Auth.guardAdminPages();
});
