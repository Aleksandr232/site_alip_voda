<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход — Алип Вода Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body class="admin-body">
  <div class="login-page">
    <div class="login-card">
      <div class="login-card__logo">
        <span class="logo__icon" aria-hidden="true">▲</span>
        Алип<span>Вода</span>
      </div>
      <h1 id="auth-title">Вход в админ-панель</h1>
      <p class="login-card__subtitle" id="auth-subtitle">Управление контентом сайта</p>

      <div class="auth-tabs">
        <button type="button" class="auth-tab auth-tab--active" data-auth-tab="login">Вход</button>
        <button type="button" class="auth-tab" data-auth-tab="register">Регистрация</button>
      </div>

      <p class="auth-error" id="auth-error" hidden></p>

      <form class="login-form auth-form auth-form--active" id="login-form" data-auth-panel="login">
        <label>
          <span>Email</span>
          <input type="email" name="email" required placeholder="admin@alip-voda.ru" autocomplete="username">
        </label>
        <label>
          <span>Пароль</span>
          <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
        </label>
        <div class="login-form__row">
          <label class="login-form__remember">
            <input type="checkbox" name="remember" id="remember-me">
            Запомнить меня
          </label>
        </div>
        <button type="submit" class="btn btn--primary btn--full" id="auth-submit">Войти</button>
      </form>

      <form class="login-form auth-form" id="register-form" data-auth-panel="register" hidden>
        <label>
          <span>Имя</span>
          <input type="text" name="name" required placeholder="Иван Иванов" autocomplete="name">
        </label>
        <label>
          <span>Email</span>
          <input type="email" name="email" required placeholder="admin@alip-voda.ru" autocomplete="username">
        </label>
        <label>
          <span>Пароль</span>
          <input type="password" name="password" required placeholder="Минимум 6 символов" autocomplete="new-password" minlength="6">
        </label>
        <label>
          <span>Повторите пароль</span>
          <input type="password" name="password_confirm" required placeholder="••••••••" autocomplete="new-password" minlength="6">
        </label>
        <button type="submit" class="btn btn--primary btn--full">Зарегистрироваться</button>
      </form>

      <p class="login-card__back">
        <a href="../">← Вернуться на сайт</a>
      </p>
    </div>
  </div>

  <script src="../admin/js/auth.js"></script>
  <script src="../admin/js/admin.js"></script>
</body>
</html>
