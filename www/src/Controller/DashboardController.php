<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Response;

/**
 * Lightweight marketing page for the website monitoring service.
 */
final class DashboardController
{
    public function index(): Response
    {
        $response = Response::html(<<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f7f9fc">
<meta name="description" content="Мониторинг сайтов: проверки доступности, времени ответа и SSL-сертификатов каждые 30 секунд.">
<title>2adm — мониторинг сайтов</title>
<style>
  :root {
    color-scheme: light;
    --bg: #f7f9fc;
    --surface: #ffffff;
    --text: #0f172a;
    --muted: #5b6b82;
    --border: #e2e8f0;
    --accent: #2563eb;
    --accent-press: #1d4ed8;
    --accent-soft: #eff4ff;
    --ok: #047857;
    --ok-bg: #ecfdf5;
    --warn: #b45309;
    --warn-bg: #fffbeb;
    --shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px rgba(15, 23, 42, .06);
    --radius: 14px;
  }
  * { box-sizing: border-box; }
  [hidden] { display: none !important; }
  html { scroll-behavior: smooth; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  body.modal-open { overflow: hidden; }
  a { color: var(--accent); text-decoration: none; }
  a:hover { text-decoration: underline; }
  .wrap { width: min(1080px, 100% - 40px); margin: 0 auto; }
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 42px; padding: 0 18px; border: 1px solid transparent; border-radius: 10px;
    font: inherit; font-weight: 600; cursor: pointer; white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
  }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-press); }
  .btn-ghost { background: var(--surface); border-color: var(--border); color: var(--text); }
  .btn-ghost:hover { border-color: #cbd5e1; background: #fbfdff; }
  .btn-block { width: 100%; }
  .muted { color: var(--muted); }

  header.site {
    position: sticky; top: 0; z-index: 10;
    background: rgba(247, 249, 252, .88);
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(10px);
  }
  .header-inner {
    display: flex; align-items: center; gap: 24px;
    min-height: 64px;
  }
  .brand { display: inline-flex; align-items: center; gap: 9px; color: var(--text); font-weight: 700; font-size: 18px; }
  .brand:hover { text-decoration: none; }
  .brand-mark {
    width: 26px; height: 26px; border-radius: 8px;
    background: linear-gradient(135deg, #2563eb, #38bdf8);
    position: relative; flex: none;
  }
  .brand-mark::after {
    content: ""; position: absolute; inset: 7px;
    border: 2px solid #fff; border-radius: 50%;
    border-bottom-color: transparent;
  }
  nav.main { display: flex; gap: 22px; margin-left: 8px; }
  nav.main a { color: var(--muted); font-size: 15px; }
  nav.main a:hover { color: var(--text); text-decoration: none; }
  .header-actions { display: flex; align-items: center; gap: 12px; margin-left: auto; }
  .status-pill {
    display: inline-flex; align-items: center; gap: 7px;
    height: 30px; padding: 0 11px; border-radius: 999px;
    background: #eef2f7; color: var(--muted); font-size: 13px; font-weight: 600;
  }
  .status-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: #94a3b8; flex: none; }
  .status-pill.ok { background: var(--ok-bg); color: var(--ok); }
  .status-pill.ok .dot { background: #10b981; }
  .status-pill.bad { background: var(--warn-bg); color: var(--warn); }
  .status-pill.bad .dot { background: #f59e0b; }

  .hero { padding: 72px 0 56px; }
  .hero-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 48px; align-items: center; }
  .eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 5px 12px; border-radius: 999px;
    background: var(--accent-soft); color: var(--accent);
    font-size: 13px; font-weight: 600;
  }
  h1 { margin: 18px 0 16px; font-size: clamp(32px, 4.4vw, 48px); line-height: 1.1; letter-spacing: -.02em; }
  .hero p.lead { margin: 0 0 28px; max-width: 34em; font-size: 18px; color: var(--muted); }
  .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
  .hero-actions .btn { height: 46px; padding: 0 22px; font-size: 16px; }
  .stats { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 36px; padding-top: 26px; border-top: 1px solid var(--border); }
  .stat strong { display: block; font-size: 22px; line-height: 1.2; }
  .stat span { font-size: 13px; color: var(--muted); }

  .preview {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); overflow: hidden;
  }
  .preview-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--border); }
  .preview-head strong { font-size: 14px; }
  .preview-head .tag { font-size: 11px; color: var(--muted); }
  .site-row { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #eef2f7; }
  .site-row:last-child { border-bottom: 0; }
  .site-row .name { font-weight: 600; font-size: 14px; }
  .site-row .sub { font-size: 12px; color: var(--muted); }
  .site-row .meta { margin-left: auto; text-align: right; font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; }
  .badge-ok { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 999px; background: var(--ok-bg); color: var(--ok); font-size: 12px; font-weight: 600; }
  .badge-ok::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
  .preview-foot { padding: 12px 18px; background: #fbfdff; border-top: 1px solid var(--border); font-size: 12px; color: var(--muted); }

  section.block { padding: 56px 0; }
  .section-head { max-width: 40em; margin-bottom: 30px; }
  .section-head h2 { margin: 0 0 8px; font-size: 28px; letter-spacing: -.01em; }
  .section-head p { margin: 0; color: var(--muted); }
  .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
  .card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 24px; box-shadow: var(--shadow);
  }
  .card .icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; margin-bottom: 16px; border-radius: 11px;
    background: var(--accent-soft); color: var(--accent);
  }
  .card h3 { margin: 0 0 8px; font-size: 17px; }
  .card p { margin: 0; color: var(--muted); font-size: 14px; }

  .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; counter-reset: step; }
  .step { position: relative; padding: 22px 22px 22px 64px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
  .step::before {
    counter-increment: step; content: counter(step);
    position: absolute; left: 22px; top: 22px;
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--accent); color: #fff; font-size: 13px; font-weight: 700;
  }
  .step h3 { margin: 0 0 6px; font-size: 16px; }
  .step p { margin: 0; color: var(--muted); font-size: 14px; }

  .cta {
    display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap;
    padding: 32px; border-radius: var(--radius);
    background: linear-gradient(135deg, #eef4ff, #f3f8ff);
    border: 1px solid #dbe7ff;
  }
  .cta h2 { margin: 0 0 6px; font-size: 22px; }
  .cta p { margin: 0; color: var(--muted); }

  footer.site { padding: 28px 0 40px; border-top: 1px solid var(--border); }
  .footer-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 13px; }

  .modal[hidden] { display: none; }
  .modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px); }
  .modal-dialog {
    position: relative; width: min(400px, 100%);
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
    padding: 28px;
  }
  .modal-dialog h2 { margin: 0 0 6px; font-size: 22px; }
  .modal-dialog .sub { margin: 0 0 22px; color: var(--muted); font-size: 14px; }
  .modal-close {
    position: absolute; top: 12px; right: 12px;
    width: 34px; height: 34px; border: 0; border-radius: 9px;
    background: transparent; color: var(--muted); font-size: 22px; line-height: 1; cursor: pointer;
  }
  .modal-close:hover { background: #f1f5f9; color: var(--text); }
  .field { display: block; margin-bottom: 16px; }
  .field span { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
  .field input {
    width: 100%; height: 42px; padding: 0 12px;
    border: 1px solid var(--border); border-radius: 10px;
    font: inherit; font-size: 15px; color: var(--text); background: #fff;
  }
  .field input:focus { outline: 2px solid rgba(37, 99, 235, .35); outline-offset: 1px; border-color: var(--accent); }
  .form-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 20px; font-size: 13px; }
  .checkbox { display: inline-flex; align-items: center; gap: 7px; color: var(--muted); cursor: pointer; }
  .checkbox input { width: 15px; height: 15px; accent-color: var(--accent); }
  .form-note { min-height: 20px; margin: 14px 0 0; font-size: 13px; color: var(--muted); text-align: center; }
  .form-note.error { color: #b91c1c; }
  .switch { margin: 16px 0 0; font-size: 13px; color: var(--muted); text-align: center; }
  .account { border: 1px solid var(--border); border-radius: 12px; background: #fbfdff; padding: 16px; margin-bottom: 18px; }
  .account .label { font-size: 12px; color: var(--muted); }
  .account .name { font-size: 16px; font-weight: 600; }
  .account .email { font-size: 14px; color: var(--muted); word-break: break-all; }
  .user-chip { display: inline-flex; align-items: center; gap: 10px; font-size: 14px; color: var(--muted); }
  .user-chip strong { max-width: 140px; overflow: hidden; color: var(--text); font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
  .link-button { padding: 0; border: 0; background: none; color: var(--accent); font: inherit; font-size: 13px; cursor: pointer; }
  .link-button:hover { text-decoration: underline; }
  .toast {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 200;
    max-width: calc(100% - 32px); padding: 10px 16px; border-radius: 10px;
    background: #0f172a; color: #fff; font-size: 14px; box-shadow: 0 12px 30px rgba(15, 23, 42, .25);
  }
  .btn[disabled] { opacity: .7; cursor: default; }

  @media (max-width: 860px) {
    .hero-grid { grid-template-columns: 1fr; gap: 32px; }
    .cards, .steps { grid-template-columns: 1fr; }
    nav.main { display: none; }
    .status-pill { display: none; }
    .hero { padding: 48px 0 40px; }
  }
  @media (max-width: 480px) {
    .wrap { width: calc(100% - 28px); }
    .header-actions { gap: 8px; }
    .header-actions .btn { height: 38px; padding: 0 12px; font-size: 14px; }
    .stats { gap: 18px; }
  }
</style>
</head>
<body>
<header class="site">
  <div class="wrap header-inner">
    <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span>2adm</a>
    <nav class="main" aria-label="Основная навигация">
      <a href="#features">Возможности</a>
      <a href="#how">Как работает</a>
      <a href="#start">Начать</a>
    </nav>
    <div class="header-actions">
      <span class="status-pill" id="status"><span class="dot"></span><span id="statusText">Проверка…</span></span>
      <button class="btn btn-ghost" type="button" data-register id="headerRegister">Регистрация</button>
      <button class="btn btn-primary" type="button" data-login id="headerLogin">Войти</button>
      <span class="user-chip" id="userChip" hidden>
        <strong id="userName"></strong>
        <button class="link-button" type="button" data-logout>Выйти</button>
      </span>
    </div>
  </div>
</header>

<main>
  <section class="hero">
    <div class="wrap hero-grid">
      <div>
        <span class="eyebrow">Мониторинг сайтов 24/7</span>
        <h1>Проблемы на сайте видно раньше, чем пользователи</h1>
        <p class="lead">
          Проверяем доступность, время ответа и SSL-сертификаты каждые 30 секунд.
          Сообщаем об инцидентах сразу, а не после звонка клиента.
        </p>
        <div class="hero-actions">
          <button class="btn btn-primary" type="button" data-login>Войти в панель</button>
          <a class="btn btn-ghost" href="#features">Возможности</a>
        </div>
        <div class="stats">
          <div class="stat"><strong>99,98%</strong><span>средний uptime</span></div>
          <div class="stat"><strong>30 сек</strong><span>интервал проверок</span></div>
          <div class="stat"><strong>&lt; 1 мин</strong><span>до оповещения</span></div>
        </div>
      </div>
      <div class="preview" aria-label="Пример панели мониторинга">
        <div class="preview-head">
          <strong>Сводка</strong>
          <span class="tag">пример</span>
        </div>
        <div class="site-row">
          <div>
            <div class="name">example.com</div>
            <div class="sub">HTTPS · каждые 30 сек</div>
          </div>
          <div class="meta">
            <span class="badge-ok">200 OK</span>
            <div>142 мс</div>
          </div>
        </div>
        <div class="site-row">
          <div>
            <div class="name">shop.example.com</div>
            <div class="sub">HTTPS · каждые 30 сек</div>
          </div>
          <div class="meta">
            <span class="badge-ok">200 OK</span>
            <div>238 мс</div>
          </div>
        </div>
        <div class="site-row">
          <div>
            <div class="name">api.example.com</div>
            <div class="sub">HTTPS · каждые 30 сек</div>
          </div>
          <div class="meta">
            <span class="badge-ok">200 OK</span>
            <div>89 мс</div>
          </div>
        </div>
        <div class="preview-foot">Все проверки пройдены · только что</div>
      </div>
    </div>
  </section>

  <section class="block" id="features">
    <div class="wrap">
      <div class="section-head">
        <h2>Всё, что нужно для спокойного дежурства</h2>
        <p>Никаких сложных настроек: добавьте сайт, выберите интервал и получайте уведомления.</p>
      </div>
      <div class="cards">
        <article class="card">
          <span class="icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 8-4-16-3 8H2"/></svg>
          </span>
          <h3>Доступность</h3>
          <p>HTTP и HTTPS проверки, контроль статус-кодов, редиректов и времени ответа сервера.</p>
        </article>
        <article class="card">
          <span class="icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
          </span>
          <h3>Скорость</h3>
          <p>История времени ответа, тренды и сравнение с базовой линией, чтобы замечать деградацию.</p>
        </article>
        <article class="card">
          <span class="icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
          </span>
          <h3>Оповещения</h3>
          <p>Email, Telegram или webhook при сбое и восстановлении. Без ложных срабатываний.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="block" id="how">
    <div class="wrap">
      <div class="section-head">
        <h2>Как это работает</h2>
        <p>Три шага до первого отчёта — обычно меньше пяти минут.</p>
      </div>
      <div class="steps">
        <article class="step">
          <h3>Добавьте сайт</h3>
          <p>Укажите адрес и способ проверки: страница, API или редирект.</p>
        </article>
        <article class="step">
          <h3>Выберите интервал</h3>
          <p>От 30 секунд до 5 минут. Настройте пороги времени ответа.</p>
        </article>
        <article class="step">
          <h3>Получайте оповещения</h3>
          <p>Мы сообщим о сбое, а после восстановления — пришлём подтверждение.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="block" id="start">
    <div class="wrap">
      <div class="cta">
        <div>
          <h2>Начните следить за сайтом уже сегодня</h2>
          <p>Войдите в панель, чтобы добавить первый сайт и настроить уведомления.</p>
        </div>
        <button class="btn btn-primary" type="button" data-login>Войти</button>
      </div>
    </div>
  </section>
</main>

<footer class="site">
  <div class="wrap footer-inner">
    <span>© 2026 2adm.ru · мониторинг сайтов</span>
    <span><a href="/api/v1/health">Статус API</a></span>
  </div>
</footer>

<div class="modal" id="loginModal" hidden>
  <div class="modal-backdrop" data-close></div>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
    <button class="modal-close" type="button" data-close aria-label="Закрыть">&times;</button>
    <h2 id="loginTitle">Вход</h2>
    <p class="sub" id="loginSub">Войдите, чтобы управлять мониторингом сайтов.</p>
    <form id="loginForm" novalidate>
      <label class="field">
        <span>Email или логин</span>
        <input type="text" name="login" autocomplete="username" placeholder="you@example.com или admin" required>
      </label>
      <label class="field">
        <span>Пароль</span>
        <input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
      </label>
      <div class="form-row">
        <label class="checkbox"><input type="checkbox" name="remember" checked> Запомнить меня</label>
        <a href="#" data-forgot>Забыли пароль?</a>
      </div>
      <button class="btn btn-primary btn-block" type="submit" id="loginSubmit">Войти</button>
      <p class="form-note" id="loginNote" role="status"></p>
      <p class="switch">Нет аккаунта? <a href="#" data-show-register>Регистрация</a></p>
    </form>
    <form id="registerForm" hidden novalidate>
      <label class="field">
        <span>Имя</span>
        <input type="text" name="name" autocomplete="name" placeholder="Как к вам обращаться">
      </label>
      <label class="field">
        <span>Компания</span>
        <input type="text" name="company" autocomplete="organization" placeholder="Название компании" required>
      </label>
      <label class="field">
        <span>Email</span>
        <input type="email" name="email" autocomplete="email" placeholder="you@example.com" required>
      </label>
      <label class="field">
        <span>Пароль</span>
        <input type="password" name="password" autocomplete="new-password" placeholder="Минимум 8 символов" required>
      </label>
      <button class="btn btn-primary btn-block" type="submit" id="registerSubmit">Создать аккаунт</button>
      <p class="form-note" id="registerNote" role="status"></p>
      <p class="switch">Уже есть аккаунт? <a href="#" data-show-login>Войти</a></p>
    </form>
    <div id="accountView" hidden>
      <div class="account">
        <div class="label">Вы вошли как</div>
        <div class="name" id="accountName"></div>
        <div class="email" id="accountEmail"></div>
      </div>
      <a class="btn btn-primary btn-block" href="/dash/">Перейти в панель</a>
      <button class="btn btn-ghost btn-block" type="button" data-logout style="margin-top:10px">Выйти</button>
    </div>
  </div>
</div>

<div class="toast" id="toast" role="status" hidden></div>

<script>
(function () {
  var modal = document.getElementById('loginModal');
  var form = document.getElementById('loginForm');
  var note = document.getElementById('loginNote');
  var loginInput = form.querySelector('input[name="login"]');
  var passwordInput = form.querySelector('input[name="password"]');
  var submitButton = document.getElementById('loginSubmit');
  var headerLogin = document.getElementById('headerLogin');
  var headerRegister = document.getElementById('headerRegister');
  var userChip = document.getElementById('userChip');
  var userName = document.getElementById('userName');
  var accountView = document.getElementById('accountView');
  var accountName = document.getElementById('accountName');
  var accountEmail = document.getElementById('accountEmail');
  var loginTitle = document.getElementById('loginTitle');
  var loginSub = document.getElementById('loginSub');
  var registerForm = document.getElementById('registerForm');
  var registerNote = document.getElementById('registerNote');
  var registerSubmit = document.getElementById('registerSubmit');
  var toast = document.getElementById('toast');
  var loginButtons = Array.prototype.slice.call(document.querySelectorAll('[data-login]'));
  var registerButtons = Array.prototype.slice.call(document.querySelectorAll('[data-register]'));
  var logoutButtons = Array.prototype.slice.call(document.querySelectorAll('[data-logout]'));
  var lastFocus = null;
  var toastTimer = null;
  var modalMode = 'login';
  var registerNameInput = registerForm.querySelector('input[name="name"]');

  var TOKEN_KEY = 'adm_token';
  var state = { token: readToken(), user: null };

  loginButtons.concat(registerButtons).forEach(function (button) {
    button.dataset.defaultLabel = button.textContent;
  });

  function readToken() {
    try {
      return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
    } catch (error) {
      return null;
    }
  }

  function clearToken() {
    try {
      localStorage.removeItem(TOKEN_KEY);
      sessionStorage.removeItem(TOKEN_KEY);
    } catch (error) {
      /* storage unavailable */
    }
  }

  function saveToken(token, remember) {
    clearToken();
    try {
      (remember ? localStorage : sessionStorage).setItem(TOKEN_KEY, token);
    } catch (error) {
      /* keep the session in memory only */
    }
  }

  function setNote(message, isError) {
    note.textContent = message;
    note.className = 'form-note' + (isError ? ' error' : '');
  }

  function setRegisterNote(message, isError) {
    registerNote.textContent = message;
    registerNote.className = 'form-note' + (isError ? ' error' : '');
  }

  function showToast(message) {
    toast.textContent = message;
    toast.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, 3200);
  }

  function api(path, options) {
    options = options || {};

    var headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    if (state.token) headers.Authorization = 'Bearer ' + state.token;
    options.headers = headers;

    return fetch(path, options).then(function (response) {
      return response.text().then(function (text) {
        var data = {};

        if (text !== '') {
          try {
            data = JSON.parse(text);
          } catch (error) {
            data = {};
          }
        }

        if (!response.ok) {
          var failure = new Error(data.message || 'Request failed');
          failure.status = response.status;
          failure.data = data;
          throw failure;
        }

        return data;
      });
    });
  }

  function updateAuthUi() {
    var user = state.user;
    var authed = !!(user && state.token);

    if (authed) {
      userName.textContent = user.name || user.email;
      accountName.textContent = user.name || user.email;
      accountEmail.textContent = user.email;
    }

    headerLogin.hidden = authed;
    headerRegister.hidden = authed;
    userChip.hidden = !authed;
    form.hidden = authed || modalMode !== 'login';
    registerForm.hidden = authed || modalMode !== 'register';
    accountView.hidden = !authed;

    if (authed) {
      loginTitle.textContent = 'Аккаунт';
      loginSub.textContent = 'Вы вошли в панель мониторинга.';
    } else if (modalMode === 'register') {
      loginTitle.textContent = 'Регистрация';
      loginSub.textContent = 'Создайте аккаунт, чтобы следить за сайтами.';
    } else {
      loginTitle.textContent = 'Вход';
      loginSub.textContent = 'Войдите, чтобы управлять мониторингом сайтов.';
    }

    loginButtons.forEach(function (button) {
      button.textContent = authed ? 'Мой аккаунт' : button.dataset.defaultLabel;
    });

    registerButtons.forEach(function (button) {
      button.textContent = button.dataset.defaultLabel;
    });
  }

  function openModal(mode) {
    lastFocus = document.activeElement;
    modalMode = mode === 'register' ? 'register' : 'login';
    updateAuthUi();
    modal.hidden = false;
    document.body.classList.add('modal-open');
    setNote('');
    setRegisterNote('');

    if (!state.user) {
      var input = modalMode === 'register' ? registerNameInput : loginInput;
      window.setTimeout(function () { input.focus(); }, 0);
    }
  }

  function switchMode(mode) {
    modalMode = mode === 'register' ? 'register' : 'login';
    updateAuthUi();
    setNote('');
    setRegisterNote('');
    (modalMode === 'register' ? registerNameInput : loginInput).focus();
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    form.reset();
    registerForm.reset();
    setNote('');
    setRegisterNote('');

    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function setBusy(busy) {
    submitButton.disabled = busy;
    submitButton.textContent = busy ? 'Вход…' : 'Войти';
  }

  function setRegisterBusy(busy) {
    registerSubmit.disabled = busy;
    registerSubmit.textContent = busy ? 'Создание…' : 'Создать аккаунт';
  }

  function logout() {
    var finish = function () {
      clearToken();
      state.token = null;
      state.user = null;
      updateAuthUi();
      if (!modal.hidden) closeModal();
      showToast('Вы вышли из аккаунта.');
    };

    if (!state.token) {
      finish();
      return;
    }

    api('/api/v1/auth/logout', { method: 'POST' }).then(finish, finish);
  }

  loginButtons.forEach(function (button) {
    button.addEventListener('click', function () { openModal('login'); });
  });

  registerButtons.forEach(function (button) {
    button.addEventListener('click', function () { openModal('register'); });
  });

  Array.prototype.forEach.call(modal.querySelectorAll('[data-close]'), function (node) {
    node.addEventListener('click', closeModal);
  });

  logoutButtons.forEach(function (button) {
    button.addEventListener('click', logout);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    setNote('');

    var login = loginInput.value.trim();
    var password = passwordInput.value;
    var remember = form.querySelector('input[name="remember"]').checked;

    if (login === '' || password === '') {
      setNote('Введите логин и пароль.', true);
      return;
    }

    state.token = null;
    setBusy(true);

    api('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login: login, password: password })
    }).then(function (data) {
      saveToken(data.token, remember);
      state.token = data.token;
      state.user = data.user;
      window.location.assign(data.user.group === 'administrators' ? '/admin/' : '/dash/');
    }).catch(function (error) {
      if (error.status === 401) {
        setNote('Неверный логин или пароль.', true);
      } else if (error.status === 429) {
        setNote('Слишком много попыток. Попробуйте позже.', true);
      } else {
        setNote('Не удалось войти. Попробуйте ещё раз.', true);
      }
    }).finally(function () {
      setBusy(false);
    });
  });

  registerForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setRegisterNote('');

    var name = registerNameInput.value.trim();
    var company = registerForm.querySelector('input[name="company"]').value.trim();
    var email = registerForm.querySelector('input[name="email"]').value.trim();
    var password = registerForm.querySelector('input[name="password"]').value;

    if (company === '' || email === '' || password.length < 8) {
      setRegisterNote('Укажите компанию, email и пароль не короче 8 символов.', true);
      return;
    }

    state.token = null;
    setRegisterBusy(true);

    api('/api/v1/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name, company: company, email: email, password: password })
    }).then(function (data) {
      saveToken(data.token, true);
      state.token = data.token;
      state.user = data.user;
      window.location.assign('/dash/');
    }).catch(function (error) {
      if (error.status === 409) {
        setRegisterNote('Аккаунт с таким email уже существует.', true);
      } else if (error.status === 422) {
        setRegisterNote('Проверьте компанию, email и пароль (минимум 8 символов).', true);
      } else if (error.status === 429) {
        setRegisterNote('Слишком много попыток. Попробуйте позже.', true);
      } else {
        setRegisterNote('Не удалось зарегистрироваться. Попробуйте ещё раз.', true);
      }
    }).finally(function () {
      setRegisterBusy(false);
    });
  });

  var showRegister = modal.querySelector('[data-show-register]');
  if (showRegister) {
    showRegister.addEventListener('click', function (event) {
      event.preventDefault();
      switchMode('register');
    });
  }

  var showLogin = modal.querySelector('[data-show-login]');
  if (showLogin) {
    showLogin.addEventListener('click', function (event) {
      event.preventDefault();
      switchMode('login');
    });
  }

  var forgot = modal.querySelector('[data-forgot]');
  if (forgot) {
    forgot.addEventListener('click', function (event) {
      event.preventDefault();
      setNote('Восстановление пароля пока не подключено.');
    });
  }

  var statusEl = document.getElementById('status');
  var statusText = document.getElementById('statusText');

  fetch('/api/v1/health', { headers: { Accept: 'application/json' } })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      var ok = data && data.status === 'ok';
      statusEl.className = 'status-pill ' + (ok ? 'ok' : 'bad');
      statusText.textContent = ok ? 'Все системы работают' : 'Частичный сбой';
    })
    .catch(function () {
      statusEl.className = 'status-pill bad';
      statusText.textContent = 'Сервис недоступен';
    });

  var nextParam = new URLSearchParams(window.location.search).get('next');
  var pendingAdminLogin = nextParam === '/admin' || nextParam === '/admin/';

  updateAuthUi();

  if (pendingAdminLogin && readToken() === null) {
    window.setTimeout(function () { openModal('login'); }, 0);
  }

  if (state.token) {
    api('/api/v1/auth/me').then(function (data) {
      state.user = data.user;
      updateAuthUi();

      if (pendingAdminLogin) {
        window.location.assign(data.user.group === 'administrators' ? '/admin/' : '/dash/');
      }
    }).catch(function (error) {
      if (error.status === 401) {
        clearToken();
        state.token = null;
        state.user = null;
        updateAuthUi();

        if (pendingAdminLogin) {
          window.setTimeout(function () { openModal('login'); }, 0);
        }
      }
    });
  }
})();
</script>
</body>
</html>
HTML);

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
