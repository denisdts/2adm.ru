<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

final class AdminPageController
{
    public function index(Request $request): Response
    {
        $response = Response::html(<<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f7f9fc">
<meta name="robots" content="noindex">
<title>Администрирование — 2adm</title>
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
    --danger: #b91c1c;
    --shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px rgba(15, 23, 42, .06);
    --radius: 14px;
  }
  * { box-sizing: border-box; }
  [hidden] { display: none !important; }
  body {
    margin: 0; min-height: 100vh; background: var(--bg); color: var(--text);
    font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  body.modal-open { overflow: hidden; }
  a { color: var(--accent); text-decoration: none; }
  a:hover { text-decoration: underline; }
  .wrap { width: min(1180px, 100% - 40px); margin: 0 auto; }
  .muted { color: var(--muted); }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 40px; padding: 0 16px; border: 1px solid transparent; border-radius: 10px;
    font: inherit; font-weight: 600; font-size: 15px; cursor: pointer; white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
  }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-press); }
  .btn-ghost { background: var(--surface); border-color: var(--border); color: var(--text); }
  .btn-ghost:hover { border-color: #cbd5e1; background: #fbfdff; }
  .btn[disabled] { opacity: .7; cursor: default; }

  header.topbar {
    position: sticky; top: 0; z-index: 10;
    background: rgba(247, 249, 252, .9);
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(10px);
  }
  .topbar-inner { display: flex; align-items: center; gap: 20px; min-height: 64px; }
  .brand { display: inline-flex; align-items: center; gap: 9px; color: var(--text); font-weight: 700; font-size: 18px; }
  .brand:hover { text-decoration: none; }
  .brand-mark {
    width: 26px; height: 26px; border-radius: 8px; position: relative; flex: none;
    background: linear-gradient(135deg, #2563eb, #38bdf8);
  }
  .brand-mark::after {
    content: ""; position: absolute; inset: 7px;
    border: 2px solid #fff; border-radius: 50%; border-bottom-color: transparent;
  }
  nav.main { display: flex; gap: 16px; }
  nav.main a { color: var(--muted); font-size: 14px; }
  nav.main a.active { color: var(--text); font-weight: 600; }
  nav.main a:hover { color: var(--text); text-decoration: none; }
  .topbar-actions { display: flex; align-items: center; gap: 14px; margin-left: auto; }
  .who { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
  .who strong { max-width: 180px; overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
  .who span { max-width: 180px; overflow: hidden; font-size: 12px; color: var(--muted); text-overflow: ellipsis; white-space: nowrap; }

  main { padding: 36px 0 56px; }
  .page-head { margin-bottom: 24px; }
  .page-head h1 { margin: 0 0 4px; font-size: 28px; letter-spacing: -.01em; }
  .page-head p { margin: 0; }

  .tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 22px; border-bottom: 1px solid var(--border); }
  .tab {
    padding: 10px 14px; border: 0; border-bottom: 2px solid transparent; background: transparent;
    color: var(--muted); font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
  }
  .tab:hover { color: var(--text); }
  .tab.active { color: var(--accent); border-bottom-color: var(--accent); }

  .panel { display: none; }
  .panel.active { display: block; }
  .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .panel-head h2 { margin: 0; font-size: 18px; }

  .add-admin {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); padding: 16px 20px; margin-bottom: 16px;
  }
  .add-admin summary { cursor: pointer; font-weight: 600; }
  .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin: 18px 0; }
  .field { display: block; }
  .field span { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
  .field input {
    width: 100%; height: 42px; padding: 0 12px;
    border: 1px solid var(--border); border-radius: 10px;
    font: inherit; font-size: 15px; color: var(--text); background: #fff;
  }
  .field input:focus { outline: 2px solid rgba(37, 99, 235, .35); outline-offset: 1px; border-color: var(--accent); }
  .field input[readonly] { background: var(--bg); color: var(--muted); cursor: default; }
  .field textarea {
    width: 100%; min-height: 120px; padding: 10px 12px;
    border: 1px solid var(--border); border-radius: 10px;
    font: inherit; font-size: 14px; color: var(--text); background: #fff; resize: vertical;
  }
  .field textarea:focus { outline: 2px solid rgba(37, 99, 235, .35); outline-offset: 1px; border-color: var(--accent); }
  .form-note { min-height: 20px; margin: 14px 0 0; font-size: 13px; color: var(--muted); }
  .form-note.error { color: var(--danger); }
  .form-note.ok { color: var(--ok); }
  .code-block {
    margin: 0; padding: 14px 16px; max-height: 340px; overflow: auto;
    background: #0f172a; color: #e2e8f0; border-radius: 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12.5px; line-height: 1.55; white-space: pre-wrap; word-break: break-word;
  }
  .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }

  .table-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); overflow: auto; max-height: 640px;
  }
  table { width: 100%; border-collapse: collapse; }
  th {
    position: sticky; top: 0; z-index: 1;
    padding: 11px 16px; background: #fbfdff; border-bottom: 1px solid var(--border);
    color: var(--muted); font-size: 12px; font-weight: 600; text-align: left;
    text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
  }
  td { padding: 10px 16px; border-top: 1px solid #eef2f7; font-size: 14px; vertical-align: top; }
  tbody tr:first-child td { border-top: 0; }
  td.right, th.right { text-align: right; }
  td.numeric { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .badge.ok { background: var(--ok-bg); color: var(--ok); }
  .badge.warn { background: var(--warn-bg); color: var(--warn); }
  .badge.neutral { background: #eef2f7; color: var(--muted); }
  .empty-row td { padding: 26px 16px; color: var(--muted); text-align: center; }
  .command-cell {
    max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px;
  }
  .actions-cell { width: 1%; white-space: nowrap; text-align: right; }
  .icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; padding: 0;
    border: 1px solid var(--border); border-radius: 9px;
    background: var(--surface); color: var(--muted); cursor: pointer;
  }
  .icon-btn:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }
  .icon-btn svg { display: block; width: 18px; height: 18px; }
  .info-grid { display: grid; grid-template-columns: 160px minmax(0, 1fr); gap: 10px 16px; margin: 0; }
  .checker-edit-form { margin-bottom: 22px; }
  .checker-edit-form .modal-actions { margin-top: 12px; }
  .info-grid dt { color: var(--muted); font-size: 13px; font-weight: 600; }
  .info-grid dd { margin: 0; font-size: 14px; word-break: break-word; }
  .info-grid dd code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
  .install-section { margin-top: 22px; }
  .install-section h3 { margin: 0 0 10px; font-size: 16px; }

  .checkers-table { table-layout: fixed; }
  .checkers-card { overflow-x: hidden; }
  .checkers-table th, .checkers-table td { white-space: normal; }
  .checkers-table th.col-id { width: 6%; }
  .checkers-table th.col-status { width: 11%; }
  .checkers-table th.col-meta { width: 15%; }
  .checkers-table th.col-key { width: 15%; }
  .checkers-table th.col-seen { width: 13%; }
  .checkers-table th.col-actions { width: 8%; }
  .checkers-table .checker-name {
    display: block; font-weight: 600;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .checkers-table .checker-guid {
    display: block; margin-top: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px;
  }
  .checkers-table .checker-meta { color: var(--muted); font-size: 13px; }
  .checkers-table .checker-meta div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  @media (max-width: 720px) {
    .checkers-table th:nth-child(4), .checkers-table td:nth-child(4),
    .checkers-table th:nth-child(5), .checkers-table td:nth-child(5) { display: none; }
  }

  .modal[hidden] { display: none; }
  .modal { position: fixed; inset: 0; z-index: 120; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px); }
  .modal-dialog {
    position: relative; width: min(560px, 100%);
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .22); padding: 28px;
  }
  .modal-dialog h2 { margin: 0 0 6px; font-size: 22px; }
  .modal-dialog .sub { margin: 0 0 20px; color: var(--muted); font-size: 14px; }
  .modal-dialog.scrollable { max-height: calc(100vh - 40px); overflow-y: auto; }
  .modal-close {
    position: absolute; top: 12px; right: 12px; width: 34px; height: 34px;
    border: 0; border-radius: 9px; background: transparent; color: var(--muted);
    font-size: 22px; line-height: 1; cursor: pointer;
  }
  .modal-close:hover { background: #f1f5f9; color: var(--text); }

  .gate {
    position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center;
    background: var(--bg); color: var(--muted); font-size: 15px; text-align: center; padding: 24px;
  }
  .denied {
    padding: 32px; text-align: center; background: var(--surface);
    border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow);
  }
  .denied h1 { margin: 0 0 8px; font-size: 24px; }
  .denied p { margin: 0 0 18px; color: var(--muted); }

  @media (max-width: 720px) {
    .who { display: none; }
    .form-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 520px) {
    .wrap { width: calc(100% - 28px); }
    nav.main { display: none; }
    th, td { padding-left: 12px; padding-right: 12px; }
  }
</style>
</head>
<body>
<div class="gate" id="gate">Проверяем доступ…</div>

<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="/admin"><span class="brand-mark" aria-hidden="true"></span>2adm</a>
    <nav class="main" aria-label="Основная навигация">
      <a href="/dash">Панель</a>
      <a class="active" href="/admin">Админ</a>
    </nav>
    <div class="topbar-actions">
      <span class="who">
        <strong id="userName"></strong>
        <span id="userEmail"></span>
      </span>
      <button class="btn btn-ghost" type="button" id="logoutBtn">Выйти</button>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="denied" id="denied" hidden>
    <h1>Доступ запрещён</h1>
    <p>Раздел доступен только пользователям группы administrators.</p>
    <a class="btn btn-ghost" href="/dash">Вернуться в панель</a>
  </div>

  <div id="adminContent" hidden>
    <div class="page-head">
      <h1>Администрирование</h1>
      <p class="muted">Пользователи, компании, сайты, чекеры и уведомления.</p>
    </div>

    <div class="tabs" role="tablist">
      <button class="tab active" type="button" data-tab="users">Пользователи</button>
      <button class="tab" type="button" data-tab="companies">Компании</button>
      <button class="tab" type="button" data-tab="sites">Сайты</button>
      <button class="tab" type="button" data-tab="checkers">Чекеры</button>
      <button class="tab" type="button" data-tab="notifications">Уведомления</button>
    </div>

    <section class="panel active" id="panel-users">
      <details class="add-admin">
        <summary>Добавить администратора</summary>
        <form id="adminForm" novalidate>
          <div class="form-grid">
            <label class="field">
              <span>Email</span>
              <input type="email" name="email" autocomplete="email" placeholder="admin@example.com" required>
            </label>
            <label class="field">
              <span>Логин</span>
              <input type="text" name="username" autocomplete="username" placeholder="Необязательно">
            </label>
            <label class="field">
              <span>Имя</span>
              <input type="text" name="name" autocomplete="name" placeholder="Необязательно">
            </label>
            <label class="field">
              <span>Пароль</span>
              <input type="password" name="password" autocomplete="new-password" placeholder="Минимум 8 символов" required>
            </label>
          </div>
          <button class="btn btn-primary" type="submit" id="adminSubmit">Создать администратора</button>
          <p class="form-note" id="adminNote" role="status"></p>
        </form>
      </details>
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Логин</th>
              <th>Имя</th>
              <th>Группа</th>
              <th>Компания</th>
              <th>Последний вход</th>
              <th>Создан</th>
            </tr>
          </thead>
          <tbody id="usersBody"></tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="panel-companies">
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Название</th>
              <th class="right">Пользователей</th>
              <th class="right">Сайтов</th>
              <th>Создана</th>
            </tr>
          </thead>
          <tbody id="companiesBody"></tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="panel-sites">
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Домен</th>
              <th>Название</th>
              <th>Компания</th>
              <th>SSL</th>
              <th>Домен до</th>
              <th>Создан</th>
            </tr>
          </thead>
          <tbody id="sitesBody"></tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="panel-checkers">
      <div class="panel-head">
        <h2>Чекеры</h2>
        <button class="btn btn-primary" type="button" data-add-checker>Добавить</button>
      </div>
      <div class="table-card checkers-card">
        <table class="checkers-table">
          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th class="col-status">Статус</th>
              <th>Чекер</th>
              <th class="col-meta">Хост / IP / версия</th>
              <th class="col-key">Ключ</th>
              <th class="col-seen">Активность</th>
              <th class="right col-actions" aria-label="Настройки"></th>
            </tr>
          </thead>
          <tbody id="checkersBody"></tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="panel-notifications">
      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Создано</th>
              <th>Тип</th>
              <th>Сообщение</th>
              <th>Компания</th>
              <th>Сайт</th>
              <th>Статус</th>
            </tr>
          </thead>
          <tbody id="notificationsBody"></tbody>
        </table>
      </div>
    </section>
  </div>
</main>

<div class="modal" id="checkerModal" hidden>
  <div class="modal-backdrop" data-close-checker></div>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="checkerTitle">
    <button class="modal-close" type="button" data-close-checker aria-label="Закрыть">&times;</button>
    <h2 id="checkerTitle">Добавить чекер</h2>
    <p class="sub" id="checkerSub">Название и GUID для запуска на серверах.</p>
    <form id="checkerForm" novalidate>
      <label class="field">
        <span>Название</span>
        <input type="text" name="name" autocomplete="off" placeholder="Например, site-checker-eu" required>
      </label>
      <label class="field">
        <span>GUID</span>
        <input type="text" name="guid" autocomplete="off" readonly aria-readonly="true">
      </label>
      <button class="btn btn-primary" type="submit" id="checkerSubmit">Добавить</button>
      <p class="form-note" id="checkerNote" role="status"></p>
    </form>

    <div id="checkerInstall" hidden>
      <pre class="code-block" id="checkerInstallCode"></pre>
      <p class="form-note" id="checkerInstallHint"></p>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" id="checkerInstallCopy">Скопировать</button>
        <button class="btn btn-primary" type="button" id="checkerInstallDone">Готово</button>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="checkerInfoModal" hidden>
  <div class="modal-backdrop" data-close-checker-info></div>
  <div class="modal-dialog scrollable" role="dialog" aria-modal="true" aria-labelledby="checkerInfoTitle">
    <button class="modal-close" type="button" data-close-checker-info aria-label="Закрыть">&times;</button>
    <h2 id="checkerInfoTitle">Настройки чекера</h2>
    <p class="sub">Название можно изменить, GUID и данные регистрации доступны только для чтения.</p>
    <form id="checkerEditForm" class="checker-edit-form" novalidate>
      <label class="field">
        <span>Название</span>
        <input type="text" name="name" autocomplete="off" maxlength="128" placeholder="Например, site-checker-eu" required>
      </label>
      <div class="modal-actions">
        <button class="btn btn-primary" type="submit" id="checkerEditSubmit">Сохранить</button>
      </div>
      <p class="form-note" id="checkerEditNote" role="status"></p>
    </form>
    <dl class="info-grid" id="checkerInfoBody"></dl>
    <div class="install-section">
      <h3>Установка на новом сервере</h3>
      <pre class="code-block" id="checkerInfoInstallCode"></pre>
      <p class="form-note" id="checkerInfoInstallHint"></p>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" id="checkerInfoInstallCopy">Скопировать</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var TOKEN_KEY = 'adm_token';
  var gate = document.getElementById('gate');
  var denied = document.getElementById('denied');
  var content = document.getElementById('adminContent');
  var adminForm = document.getElementById('adminForm');
  var adminNote = document.getElementById('adminNote');
  var adminSubmit = document.getElementById('adminSubmit');
  var checkerModal = document.getElementById('checkerModal');
  var checkerTitle = document.getElementById('checkerTitle');
  var checkerSub = document.getElementById('checkerSub');
  var checkerForm = document.getElementById('checkerForm');
  var checkerNote = document.getElementById('checkerNote');
  var checkerSubmit = document.getElementById('checkerSubmit');
  var checkerNameInput = checkerForm.querySelector('input[name="name"]');
  var checkerGuidInput = checkerForm.querySelector('input[name="guid"]');
  var checkerInfoModal = document.getElementById('checkerInfoModal');
  var checkerInfoBody = document.getElementById('checkerInfoBody');
  var checkerEditForm = document.getElementById('checkerEditForm');
  var checkerEditName = checkerEditForm.querySelector('input[name="name"]');
  var checkerEditSubmit = document.getElementById('checkerEditSubmit');
  var checkerEditNote = document.getElementById('checkerEditNote');
  var editingChecker = null;
  var checkerInstall = document.getElementById('checkerInstall');
  var checkerInstallCode = document.getElementById('checkerInstallCode');
  var checkerInstallHint = document.getElementById('checkerInstallHint');
  var checkerInstallCopy = document.getElementById('checkerInstallCopy');
  var checkerInstallDone = document.getElementById('checkerInstallDone');
  var checkerInfoInstallCode = document.getElementById('checkerInfoInstallCode');
  var checkerInfoInstallHint = document.getElementById('checkerInfoInstallHint');
  var checkerInfoInstallCopy = document.getElementById('checkerInfoInstallCopy');

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

  function leave() {
    clearToken();
    window.location.replace('/');
  }

  function loginRedirect() {
    clearToken();
    window.location.replace('/?next=' + encodeURIComponent('/admin/'));
  }

  function api(url, options) {
    options = options || {};

    var headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    var token = readToken();
    if (token) headers.Authorization = 'Bearer ' + token;
    options.headers = headers;

    return fetch(url, options).then(function (response) {
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

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function formatDate(value) {
    if (!value) return '—';

    var date = new Date(String(value).replace(' ', 'T'));

    return isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('ru-RU');
  }

  function formatDateTime(value) {
    if (!value) return '—';

    var text = String(value);
    var normalized = text.indexOf('T') === -1 ? text.replace(' ', 'T') + 'Z' : text;
    var date = new Date(normalized);

    if (isNaN(date.getTime())) return text;

    return date.toLocaleString('ru-RU', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function emptyRow(body, columns, text) {
    body.replaceChildren();
    var row = el('tr', 'empty-row');
    var cell = el('td', '', text);
    cell.colSpan = columns;
    row.append(cell);
    body.append(row);
  }

  function renderUsers(users) {
    var body = document.getElementById('usersBody');
    body.replaceChildren();

    if (!users.length) {
      emptyRow(body, 8, 'Пользователей нет.');
      return;
    }

    users.forEach(function (user) {
      var row = el('tr');
      row.append(el('td', 'numeric', String(user.id)));
      row.append(el('td', '', user.email));
      row.append(el('td', '', user.username || '—'));
      row.append(el('td', '', user.name));
      row.append(el('td', '', user.group_name || user.group_code || '—'));
      row.append(el('td', '', user.company_name || '—'));
      row.append(el('td', 'numeric', formatDateTime(user.last_login_at)));
      row.append(el('td', 'numeric', formatDate(user.created_at)));
      body.append(row);
    });
  }

  function renderCompanies(companies) {
    var body = document.getElementById('companiesBody');
    body.replaceChildren();

    if (!companies.length) {
      emptyRow(body, 5, 'Компаний нет.');
      return;
    }

    companies.forEach(function (company) {
      var row = el('tr');
      row.append(el('td', 'numeric', String(company.id)));
      row.append(el('td', '', company.name));
      row.append(el('td', 'numeric right', String(company.users_count)));
      row.append(el('td', 'numeric right', String(company.sites_count)));
      row.append(el('td', 'numeric', formatDate(company.created_at)));
      body.append(row);
    });
  }

  function renderSites(sites) {
    var body = document.getElementById('sitesBody');
    body.replaceChildren();

    if (!sites.length) {
      emptyRow(body, 7, 'Сайтов нет.');
      return;
    }

    sites.forEach(function (site) {
      var row = el('tr');
      var ssl = site.ssl_has_certificate === null
        ? '—'
        : (Number(site.ssl_has_certificate) === 1 ? 'Есть' : 'Нет');
      row.append(el('td', 'numeric', String(site.id)));
      row.append(el('td', '', site.slug));
      row.append(el('td', '', site.name));
      row.append(el('td', '', site.company_name || '—'));
      row.append(el('td', '', ssl));
      row.append(el('td', 'numeric', formatDate(site.domain_expires_at)));
      row.append(el('td', 'numeric', formatDate(site.created_at)));
      body.append(row);
    });
  }

  function renderCheckers(checkers) {
    var body = document.getElementById('checkersBody');
    body.replaceChildren();

    if (!checkers.length) {
      emptyRow(body, 7, 'Чекеры ещё не регистрировались.');
      return;
    }

    checkers.forEach(function (checker) {
      var row = el('tr');
      var status = el('span', 'badge ' + (checker.online ? 'ok' : 'warn'), checker.online ? 'Онлайн' : 'Офлайн');

      row.append(el('td', 'numeric', String(checker.id)));

      var statusCell = el('td');
      statusCell.append(status);
      row.append(statusCell);

      var nameCell = el('td');
      var nameLine = el('span', 'checker-name', checker.name || '—');
      nameLine.title = checker.name || '';
      var guidLine = el('span', 'checker-guid', checker.guid || 'GUID не задан');
      guidLine.title = checker.guid || '';
      nameCell.append(nameLine, guidLine);
      row.append(nameCell);

      var metaCell = el('td', 'checker-meta');
      metaCell.append(el('div', '', checker.host || '—'));
      var ipLine = el('div', '', checker.ip || '—');
      ipLine.title = checker.ip || '';
      metaCell.append(ipLine);
      metaCell.append(el('div', '', checker.version ? 'v' + checker.version : '—'));
      row.append(metaCell);

      row.append(el('td', '', checker.api_key_name || '—'));
      row.append(el('td', 'numeric', formatDateTime(checker.last_seen_at)));

      var settingsButton = el('button', 'icon-btn');
      settingsButton.type = 'button';
      settingsButton.title = 'Настройки';
      settingsButton.setAttribute('aria-label', 'Настройки чекера ' + (checker.name || ''));
      settingsButton.innerHTML = GEAR_ICON;
      settingsButton.addEventListener('click', function () {
        openCheckerInfo(checker);
      });

      var actionsCell = el('td', 'actions-cell');
      actionsCell.append(settingsButton);
      row.append(actionsCell);

      body.append(row);
    });
  }

  function renderNotifications(notifications) {
    var body = document.getElementById('notificationsBody');
    body.replaceChildren();

    if (!notifications.length) {
      emptyRow(body, 7, 'Уведомлений пока нет.');
      return;
    }

    notifications.forEach(function (notification) {
      var row = el('tr');
      row.append(el('td', 'numeric', String(notification.id)));
      row.append(el('td', 'numeric', formatDateTime(notification.created_at)));
      row.append(el('td', '', notification.type));
      row.append(el('td', '', notification.message));
      row.append(el('td', '', notification.company_name || '—'));
      row.append(el('td', '', notification.site_slug || '—'));
      row.append(el('td', '', notification.status));
      body.append(row);
    });
  }

  function loadUsers() {
    return api('/api/v1/admin/users').then(function (data) {
      renderUsers(data.users || []);
    });
  }

  function loadCheckers() {
    return api('/api/v1/admin/checkers').then(function (data) {
      renderCheckers(data.checkers || []);
    });
  }

  function loadAll() {
    Promise.all([
      api('/api/v1/admin/users'),
      api('/api/v1/admin/companies'),
      api('/api/v1/admin/sites'),
      api('/api/v1/admin/checkers'),
      api('/api/v1/admin/notifications')
    ]).then(function (results) {
      renderUsers(results[0].users || []);
      renderCompanies(results[1].companies || []);
      renderSites(results[2].sites || []);
      renderCheckers(results[3].checkers || []);
      renderNotifications(results[4].notifications || []);
      gate.hidden = true;
      content.hidden = false;
    }).catch(function (error) {
      if (error.status === 401) {
        loginRedirect();
        return;
      }

      if (error.status === 403) {
        showDenied();
        return;
      }

      gate.textContent = 'Не удалось загрузить данные. Обновите страницу.';
    });
  }

  function showDenied() {
    gate.hidden = true;
    content.hidden = true;
    denied.hidden = false;
  }

  function setAdminNote(message, state) {
    adminNote.textContent = message;
    adminNote.className = 'form-note' + (state ? ' ' + state : '');
  }

  function setAdminBusy(busy) {
    adminSubmit.disabled = busy;
    adminSubmit.textContent = busy ? 'Создание…' : 'Создать администратора';
  }

  function setCheckerNote(message, state) {
    checkerNote.textContent = message;
    checkerNote.className = 'form-note' + (state ? ' ' + state : '');
  }

  function setCheckerBusy(busy) {
    checkerSubmit.disabled = busy;
    checkerSubmit.textContent = busy ? 'Добавление…' : 'Добавить';
  }

  function setCheckerEditNote(message, state) {
    checkerEditNote.textContent = message;
    checkerEditNote.className = 'form-note' + (state ? ' ' + state : '');
  }

  function setCheckerEditBusy(busy) {
    checkerEditSubmit.disabled = busy;
    checkerEditSubmit.textContent = busy ? 'Сохранение…' : 'Сохранить';
  }

  function newGuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    var bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    var hex = Array.prototype.map.call(bytes, function (byte) {
      return ('0' + byte.toString(16)).slice(-2);
    }).join('');

    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' +
      hex.slice(16, 20) + '-' + hex.slice(20);
  }

  var INSTALL_HINT = 'MONITOR_API_KEY — существующий глобальный API-ключ чекера. Если ключа нет, создайте его на сервере приложения.';

  function checkerInstallScript(checker) {
    var base = window.location.origin;
    var guid = checker.guid || '';

    return [
      '# 1. Скачать и распаковать чекер (linux/amd64)',
      'curl -fsSL ' + base + '/2adm-checker_linux_amd64.tar.gz -o /tmp/checker.tar.gz',
      'mkdir -p /tmp/2adm-checker && tar -xzf /tmp/checker.tar.gz -C /tmp/2adm-checker',
      '',
      '# 2. Создать конфиг и указать настройки',
      'sudo install -d -m 0755 /opt/2adm-checker',
      'sudo install -m 0600 /tmp/2adm-checker/checker.env.example /opt/2adm-checker/checker.env',
      'sudo nano /opt/2adm-checker/checker.env',
      '#   MONITOR_BASE_URL=' + base,
      '#   MONITOR_API_KEY=<глобальный API-ключ>',
      '#   CHECKER_GUID=' + guid,
      '',
      '# 3. Установить и запустить сервис',
      'cd /tmp/2adm-checker && sudo ./install.sh',
      '',
      '# 4. Проверить',
      'systemctl status 2adm-checker',
      'journalctl -u 2adm-checker -f'
    ].join('\n');
  }

  function showCheckerInstall(checker) {
    checkerTitle.textContent = 'Чекер создан';
    checkerSub.textContent = 'Выполните шаги на новом сервере.';
    checkerForm.hidden = true;
    checkerInstallCode.textContent = checkerInstallScript(checker);
    checkerInstallHint.textContent = INSTALL_HINT;
    checkerInstallCopy.textContent = 'Скопировать';
    checkerInstall.hidden = false;
  }

  function fallbackCopy(text, onDone) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.append(area);
    area.select();
    document.execCommand('copy');
    area.remove();
    onDone();
  }

  function copyText(text, onDone) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(onDone, function () {
        fallbackCopy(text, onDone);
      });
      return;
    }

    fallbackCopy(text, onDone);
  }

  function bindCopyButton(button, codeNode) {
    button.addEventListener('click', function () {
      copyText(codeNode.textContent || '', function () {
        button.textContent = 'Скопировано';
        window.setTimeout(function () { button.textContent = 'Скопировать'; }, 1500);
      });
    });
  }

  function openCheckerModal() {
    checkerForm.hidden = false;
    checkerInstall.hidden = true;
    checkerTitle.textContent = 'Добавить чекер';
    checkerSub.textContent = 'Название и GUID для запуска на серверах.';
    checkerForm.reset();
    checkerGuidInput.value = newGuid();
    setCheckerNote('');
    checkerModal.hidden = false;
    document.body.classList.add('modal-open');
    window.setTimeout(function () { checkerNameInput.focus(); }, 0);
  }

  function closeCheckerModal() {
    checkerModal.hidden = true;
    document.body.classList.remove('modal-open');
    checkerForm.hidden = false;
    checkerInstall.hidden = true;
    checkerTitle.textContent = 'Добавить чекер';
    checkerSub.textContent = 'Название и GUID для запуска на серверах.';
    checkerForm.reset();
    setCheckerNote('');
  }

  var GEAR_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>';

  function addCheckerInfoRow(label, value) {
    checkerInfoBody.append(el('dt', '', label));

    var cell = el('dd');

    if (value instanceof Node) {
      cell.append(value);
    } else if (label === 'GUID') {
      cell.append(el('code', '', value));
    } else {
      cell.textContent = value;
    }

    checkerInfoBody.append(cell);
  }

  function openCheckerInfo(checker) {
    checkerInfoBody.replaceChildren();
    editingChecker = checker;
    checkerEditName.value = checker.name || '';
    setCheckerEditNote('');
    setCheckerEditBusy(false);

    var status = el('span', 'badge ' + (checker.online ? 'ok' : 'warn'), checker.online ? 'Онлайн' : 'Офлайн');

    addCheckerInfoRow('ID', String(checker.id));
    addCheckerInfoRow('GUID', checker.guid || '—');
    addCheckerInfoRow('Статус', status);
    addCheckerInfoRow('Хост', checker.host || '—');
    addCheckerInfoRow('IP-адрес', checker.ip || '—');
    addCheckerInfoRow('Версия', checker.version || '—');
    addCheckerInfoRow('API-ключ', checker.api_key_name || '—');
    addCheckerInfoRow('Bash-команда', checker.bash_command || '—');
    addCheckerInfoRow('Последняя активность', formatDateTime(checker.last_seen_at));
    addCheckerInfoRow('Создан', formatDateTime(checker.created_at));

    checkerInfoInstallCode.textContent = checkerInstallScript(checker);
    checkerInfoInstallHint.textContent = INSTALL_HINT;
    checkerInfoInstallCopy.textContent = 'Скопировать';

    checkerInfoModal.hidden = false;
    document.body.classList.add('modal-open');
  }

  function closeCheckerInfo() {
    checkerInfoModal.hidden = true;
    document.body.classList.remove('modal-open');
    editingChecker = null;
    checkerEditForm.reset();
    setCheckerEditNote('');
    setCheckerEditBusy(false);
  }

  function setTab(name) {
    Array.prototype.forEach.call(document.querySelectorAll('.tab'), function (button) {
      button.classList.toggle('active', button.dataset.tab === name);
    });

    Array.prototype.forEach.call(document.querySelectorAll('.panel'), function (panel) {
      panel.classList.toggle('active', panel.id === 'panel-' + name);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('.tab'), function (button) {
    button.addEventListener('click', function () {
      setTab(button.dataset.tab);
    });
  });

  document.querySelector('[data-add-checker]').addEventListener('click', openCheckerModal);

  Array.prototype.forEach.call(checkerModal.querySelectorAll('[data-close-checker]'), function (node) {
    node.addEventListener('click', closeCheckerModal);
  });

  Array.prototype.forEach.call(checkerInfoModal.querySelectorAll('[data-close-checker-info]'), function (node) {
    node.addEventListener('click', closeCheckerInfo);
  });

  checkerInstallDone.addEventListener('click', closeCheckerModal);
  bindCopyButton(checkerInstallCopy, checkerInstallCode);
  bindCopyButton(checkerInfoInstallCopy, checkerInfoInstallCode);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }

    if (!checkerInfoModal.hidden) {
      closeCheckerInfo();
      return;
    }

    if (!checkerModal.hidden) {
      closeCheckerModal();
    }
  });

  adminForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setAdminNote('');

    var email = adminForm.querySelector('input[name="email"]').value.trim();
    var username = adminForm.querySelector('input[name="username"]').value.trim();
    var name = adminForm.querySelector('input[name="name"]').value.trim();
    var password = adminForm.querySelector('input[name="password"]').value;

    if (email === '' || password.length < 8) {
      setAdminNote('Укажите email и пароль не короче 8 символов.', 'error');
      return;
    }

    setAdminBusy(true);

    api('/api/v1/admin/administrators', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email, username: username, name: name, password: password })
    }).then(function () {
      adminForm.reset();
      setAdminNote('Администратор создан.', 'ok');
      return loadUsers();
    }).catch(function (error) {
      if (error.status === 401) {
        loginRedirect();
        return;
      }

      if (error.status === 409) {
        if (error.data && error.data.error === 'username_taken') {
          setAdminNote('Пользователь с таким логином уже существует.', 'error');
        } else {
          setAdminNote('Пользователь с таким email уже существует.', 'error');
        }
      } else if (error.status === 422) {
        setAdminNote('Проверьте email, логин и пароль (минимум 8 символов).', 'error');
      } else {
        setAdminNote('Не удалось создать администратора.', 'error');
      }
    }).finally(function () {
      setAdminBusy(false);
    });
  });

  checkerEditForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setCheckerEditNote('');

    if (editingChecker === null) {
      return;
    }

    var name = checkerEditName.value.trim();

    if (name === '') {
      setCheckerEditNote('Укажите название чекера.', 'error');
      return;
    }

    setCheckerEditBusy(true);

    api('/api/v1/admin/checkers/' + encodeURIComponent(editingChecker.id), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name })
    }).then(function (data) {
      var checker = data && data.checker ? data.checker : null;

      if (checker) {
        editingChecker = checker;
        checkerEditName.value = checker.name || name;
      }

      setCheckerEditNote('Название сохранено.', 'ok');
      loadCheckers().catch(function () {});
    }).catch(function (error) {
      if (error.status === 401) {
        loginRedirect();
        return;
      }

      if (error.status === 409) {
        setCheckerEditNote('Чекер с таким названием уже существует.', 'error');
      } else if (error.status === 404) {
        setCheckerEditNote('Чекер не найден.', 'error');
      } else if (error.status === 422) {
        setCheckerEditNote('Проверьте название (до 128 символов).', 'error');
      } else {
        setCheckerEditNote('Не удалось сохранить название.', 'error');
      }
    }).finally(function () {
      setCheckerEditBusy(false);
    });
  });

  checkerForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setCheckerNote('');

    var name = checkerNameInput.value.trim();
    var guid = checkerGuidInput.value.trim();

    if (name === '') {
      setCheckerNote('Укажите название чекера.', 'error');
      return;
    }

    setCheckerBusy(true);

    api('/api/v1/admin/checkers', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name, guid: guid })
    }).then(function (data) {
      var checker = data && data.checker ? data.checker : null;

      if (checker) {
        showCheckerInstall(checker);
      } else {
        closeCheckerModal();
      }

      return loadCheckers();
    }).catch(function (error) {
      if (error.status === 401) {
        loginRedirect();
        return;
      }

      if (error.status === 409) {
        if (error.data && error.data.error === 'checker_guid_exists') {
          checkerGuidInput.value = newGuid();
          setCheckerNote('Этот GUID уже занят, сгенерирован новый. Повторите добавление.', 'error');
        } else {
          setCheckerNote('Чекер с таким названием уже существует.', 'error');
        }
      } else if (error.status === 422) {
        setCheckerNote('Проверьте название и GUID.', 'error');
      } else {
        setCheckerNote('Не удалось добавить чекер.', 'error');
      }
    }).finally(function () {
      setCheckerBusy(false);
    });
  });

  document.getElementById('logoutBtn').addEventListener('click', function () {
    api('/api/v1/auth/logout', { method: 'POST' }).then(leave, leave);
  });

  if (readToken() === null) {
    loginRedirect();
    return;
  }

  api('/api/v1/auth/me').then(function (data) {
    var user = data.user;

    if (!user || user.group !== 'administrators') {
      showDenied();
      return;
    }

    document.getElementById('userName').textContent = user.name || user.email;
    document.getElementById('userEmail').textContent = user.email;
    loadAll();
  }).catch(function (error) {
    if (error.status === 401) {
      loginRedirect();
      return;
    }

    gate.textContent = 'Не удалось проверить доступ. Обновите страницу.';
  });
})();
</script>
</body>
</html>
HTML);

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
