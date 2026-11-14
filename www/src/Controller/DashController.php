<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Response;

/**
 * Monitoring panel. The page is rendered for everyone, but all data is loaded
 * with a bearer token and unauthenticated visitors are redirected to "/".
 */
final class DashController
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
<meta name="robots" content="noindex">
<title>Панель мониторинга — 2adm</title>
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
  body {
    margin: 0; min-height: 100vh; background: var(--bg); color: var(--text);
    font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  body.modal-open { overflow: hidden; }
  a { color: var(--accent); text-decoration: none; }
  a:hover { text-decoration: underline; }
  .wrap { width: min(1080px, 100% - 40px); margin: 0 auto; }
  .muted { color: var(--muted); }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 40px; padding: 0 16px; border: 1px solid transparent; border-radius: 10px;
    font: inherit; font-weight: 600; font-size: 15px; cursor: pointer; white-space: nowrap;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
  }
  .btn-ghost { background: var(--surface); border-color: var(--border); color: var(--text); }
  .btn-ghost:hover { border-color: #cbd5e1; background: #fbfdff; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-press); }
  .btn-block { width: 100%; }
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
  .topbar-actions { display: flex; align-items: center; gap: 14px; margin-left: auto; }
  .who { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
  .who strong { max-width: 180px; overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
  .who span { max-width: 180px; overflow: hidden; font-size: 12px; color: var(--muted); text-overflow: ellipsis; white-space: nowrap; }

  main { padding: 40px 0 56px; }
  .page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 26px; }
  .page-head h1 { margin: 0 0 4px; font-size: 28px; letter-spacing: -.01em; }
  .page-head p { margin: 0; }
  .head-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .updated { font-size: 13px; color: var(--muted); }

  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 34px; }
  .stat {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 18px 20px; box-shadow: var(--shadow);
  }
  .stat span { display: block; font-size: 13px; color: var(--muted); }
  .stat strong { display: block; margin-top: 4px; font-size: 26px; line-height: 1.2; }
  .stat strong.ok { color: var(--ok); }
  .stat strong.bad { color: var(--warn); }

  .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
  .section-head h2 { margin: 0; font-size: 20px; }
  .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
  .card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 20px; box-shadow: var(--shadow); cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .card:hover { border-color: #bfd3f5; box-shadow: 0 10px 26px rgba(37, 99, 235, .10); }
  .card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .site-name { display: block; color: var(--text); font-weight: 600; font-size: 15px; }
  .site-name:hover { color: var(--accent); text-decoration: none; }
  .site-slug { font-size: 12px; color: var(--muted); }
  .count { flex: none; padding: 3px 9px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-size: 12px; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 7px 0; border-top: 1px solid #eef2f7; vertical-align: top; font-size: 14px; }
  tr:first-child td { border-top: 0; }
  td.value { color: var(--accent); text-align: right; font-variant-numeric: tabular-nums; }
  td.time { padding-left: 12px; color: var(--muted); font-size: 12px; text-align: right; }
  .checker-group td {
    padding: 12px 0 4px; border-top: 0; color: var(--muted);
    font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
  }
  .checker-group:first-child td { padding-top: 0; }
  .labels { display: block; color: var(--muted); font-size: 11px; }
  .empty { color: var(--muted); font-size: 14px; }
  .empty-card { border: 1px dashed #cbd5e1; border-radius: var(--radius); padding: 28px; text-align: center; color: var(--muted); background: var(--surface); }
  .empty-card .btn { margin-top: 14px; }

  .modal[hidden] { display: none; }
  .modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .modal-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px); }
  .modal-dialog {
    position: relative; width: min(420px, 100%);
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, .22); padding: 28px;
  }
  .modal-dialog h2 { margin: 0 0 6px; font-size: 22px; }
  .modal-dialog .sub { margin: 0 0 20px; color: var(--muted); font-size: 14px; }
  .modal-close {
    position: absolute; top: 12px; right: 12px; width: 34px; height: 34px;
    border: 0; border-radius: 9px; background: transparent; color: var(--muted);
    font-size: 22px; line-height: 1; cursor: pointer;
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
  .form-note { min-height: 20px; margin: 14px 0 0; font-size: 13px; color: var(--muted); text-align: center; }
  .form-note.error { color: #b91c1c; }
  .toast {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 200;
    max-width: calc(100% - 32px); padding: 10px 16px; border-radius: 10px;
    background: #0f172a; color: #fff; font-size: 14px; box-shadow: 0 12px 30px rgba(15, 23, 42, .25);
  }

  .gate {
    position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center;
    background: var(--bg); color: var(--muted); font-size: 15px; text-align: center; padding: 24px;
  }

  @media (max-width: 720px) {
    .stats { grid-template-columns: 1fr; }
    .who { display: none; }
  }
  @media (max-width: 480px) {
    .wrap { width: calc(100% - 28px); }
    .status-pill { display: none; }
    .topbar-actions { gap: 8px; }
    .grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="gate" id="gate">Проверяем сессию…</div>

<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="/"><span class="brand-mark" aria-hidden="true"></span>2adm</a>
    <span class="status-pill" id="status"><span class="dot"></span><span id="statusText">Проверка…</span></span>
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
  <div class="page-head">
    <div>
      <h1>Панель мониторинга</h1>
      <p class="muted" id="greeting">Загрузка данных…</p>
    </div>
    <div class="head-actions">
      <span class="updated" id="updated"></span>
      <button class="btn btn-primary" type="button" data-add-site>Добавить сайт</button>
    </div>
  </div>

  <section class="stats" aria-label="Сводка">
    <div class="stat"><span>Сайтов</span><strong id="statSites">—</strong></div>
    <div class="stat"><span>Метрик в кэше</span><strong id="statMetrics">—</strong></div>
    <div class="stat"><span>Статус API</span><strong id="statApi">—</strong></div>
  </section>

  <section>
    <div class="section-head">
      <h2>Ваши сайты</h2>
      <span class="updated">последние значения</span>
    </div>
    <div class="grid" id="grid"></div>
  </section>
</main>

<div class="modal" id="siteModal" hidden>
  <div class="modal-backdrop" data-close-site></div>
  <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="siteTitle">
    <button class="modal-close" type="button" data-close-site aria-label="Закрыть">&times;</button>
    <h2 id="siteTitle">Добавить сайт</h2>
    <p class="sub">Укажите адрес — начнём собирать метрики для этого сайта.</p>
    <form id="siteForm" novalidate>
      <label class="field">
        <span>Адрес сайта</span>
        <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="https://example.com" required>
      </label>
      <label class="field">
        <span>Название</span>
        <input type="text" name="name" autocomplete="organization" placeholder="Необязательно">
      </label>
      <button class="btn btn-primary btn-block" type="submit" id="siteSubmit">Добавить</button>
      <p class="form-note" id="siteNote" role="status"></p>
    </form>
  </div>
</div>

<div class="toast" id="toast" role="status" hidden></div>

<script>
(function () {
  var TOKEN_KEY = 'adm_token';
  var gate = document.getElementById('gate');
  var grid = document.getElementById('grid');
  var statusEl = document.getElementById('status');
  var statusText = document.getElementById('statusText');
  var logoutBtn = document.getElementById('logoutBtn');
  var siteModal = document.getElementById('siteModal');
  var siteForm = document.getElementById('siteForm');
  var siteNote = document.getElementById('siteNote');
  var siteSubmit = document.getElementById('siteSubmit');
  var siteUrlInput = siteForm.querySelector('input[name="url"]');
  var toast = document.getElementById('toast');
  var toastTimer = null;
  var checkerNames = {};

  function checkerLabel(id) {
    if (!id) return 'Без чекера';
    return checkerNames[id] || ('Чекер #' + id);
  }

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

  function api(path, options) {
    options = options || {};

    var headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    var token = readToken();
    if (token) headers.Authorization = 'Bearer ' + token;
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

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function formatValue(value) {
    if (!Number.isFinite(value)) return String(value);
    return Number.isInteger(value) ? String(value) : value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
  }

  function setSiteNote(message, isError) {
    siteNote.textContent = message;
    siteNote.className = 'form-note' + (isError ? ' error' : '');
  }

  function setSiteBusy(busy) {
    siteSubmit.disabled = busy;
    siteSubmit.textContent = busy ? 'Добавляем…' : 'Добавить';
  }

  function showToast(message) {
    toast.textContent = message;
    toast.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, 3200);
  }

  function openSiteModal() {
    siteForm.reset();
    setSiteNote('');
    siteModal.hidden = false;
    document.body.classList.add('modal-open');
    window.setTimeout(function () { siteUrlInput.focus(); }, 0);
  }

  function closeSiteModal() {
    siteModal.hidden = true;
    document.body.classList.remove('modal-open');
    siteForm.reset();
    setSiteNote('');
  }

  function formatLabels(labels) {
    return Object.keys(labels || {}).map(function (key) {
      return key + '=' + labels[key];
    }).join(' ');
  }

  function renderUser(user) {
    var display = user.name || user.email;
    if (user.company_name) display += ' · ' + user.company_name;

    document.getElementById('userName').textContent = user.name || user.email;
    document.getElementById('userEmail').textContent = user.email;
    document.getElementById('greeting').textContent = 'Здравствуйте, ' + display + '. Здесь собраны ваши сайты и последние метрики.';
  }

  function setHealth(health) {
    var ok = !!(health && health.status === 'ok');
    statusEl.className = 'status-pill ' + (ok ? 'ok' : 'bad');
    statusText.textContent = ok ? 'Все системы работают' : 'Частичный сбой';

    var apiStat = document.getElementById('statApi');
    apiStat.textContent = ok ? 'OK' : 'Сбой';
    apiStat.className = ok ? 'ok' : 'bad';
  }

  function renderCard(item) {
    var card = el('article', 'card');
    var siteUrl = '/dash/site/' + encodeURIComponent(item.service.slug);

    card.addEventListener('click', function (event) {
      if (event.target.closest && event.target.closest('a')) {
        return;
      }

      window.location.href = siteUrl;
    });

    var head = el('div', 'card-head');
    var title = el('div');
    var name = el('a', 'site-name', item.service.name || item.service.slug);
    name.href = siteUrl;
    title.append(name);
    title.append(el('div', 'site-slug', item.service.slug));

    var metricNames = {};
    item.metrics.forEach(function (metric) { metricNames[metric.name] = true; });

    head.append(title, el('span', 'count', Object.keys(metricNames).length + ' метрик'));
    card.append(head);

    if (!item.metrics.length) {
      card.append(el('div', 'empty', 'Нет данных за последнее время.'));
      return card;
    }

    var metrics = item.metrics || [];
    var checkerIds = {};
    metrics.forEach(function (metric) { checkerIds[Number(metric.checker_id) || 0] = true; });
    var multiChecker = Object.keys(checkerIds).length > 1;

    var table = el('table');
    var groups = {};
    metrics.forEach(function (metric) {
      var key = String(Number(metric.checker_id) || 0);
      if (!groups[key]) groups[key] = [];
      groups[key].push(metric);
    });

    Object.keys(groups).sort(function (a, b) { return Number(a) - Number(b); }).forEach(function (key) {
      if (multiChecker) {
        var groupRow = el('tr', 'checker-group');
        var groupCell = el('td', '', checkerLabel(Number(key)));
        groupCell.colSpan = 3;
        groupRow.append(groupCell);
        table.append(groupRow);
      }

      groups[key].forEach(function (metric) {
        var row = el('tr');
        var metricName = el('td');
        metricName.append(document.createTextNode(metric.name));
        var labels = formatLabels(metric.labels);
        if (labels !== '') metricName.append(el('span', 'labels', labels));
        row.append(metricName);
        row.append(el('td', 'value', formatValue(metric.value)));
        row.append(el('td', 'time', (metric.ts || '').slice(11, 19)));
        table.append(row);
      });
    });
    card.append(table);
    return card;
  }

  function renderCards(items) {
    grid.replaceChildren();

    if (!items.length) {
      var empty = el('div', 'empty-card');
      empty.append(el('div', '', 'Сайты ещё не добавлены.'));
      var addButton = el('button', 'btn btn-primary', 'Добавить сайт');
      addButton.type = 'button';
      addButton.addEventListener('click', openSiteModal);
      empty.append(addButton);
      grid.append(empty);
      return;
    }

    items.forEach(function (item) { grid.append(renderCard(item)); });
  }

  function loadData() {
    Promise.all([
      fetch('/api/v1/health', { headers: { Accept: 'application/json' } })
        .then(function (response) { return response.json(); })
        .catch(function () { return null; }),
      api('/api/v1/sites').catch(function (error) {
        if (error.status === 401) leave();
        return { sites: [] };
      }),
      api('/api/v1/checkers').catch(function () { return { checkers: [] }; })
    ]).then(function (results) {
      setHealth(results[0]);

      checkerNames = {};
      (results[2].checkers || []).forEach(function (checker) {
        checkerNames[checker.id] = checker.name;
      });

      var sites = results[1].sites || [];
      document.getElementById('statSites').textContent = String(sites.length);

      return Promise.all(sites.map(function (site) {
        return api('/api/v1/metrics/' + encodeURIComponent(site.slug) + '/latest')
          .then(function (data) { return { service: site, metrics: data.metrics || [] }; })
          .catch(function () { return { service: site, metrics: [] }; });
      }));
    }).then(function (items) {
      var metricCount = items.reduce(function (total, item) {
        var names = {};
        item.metrics.forEach(function (metric) { names[metric.name] = true; });
        return total + Object.keys(names).length;
      }, 0);

      renderCards(items);
      document.getElementById('statMetrics').textContent = String(metricCount);
      document.getElementById('updated').textContent = 'обновлено ' + new Date().toLocaleTimeString();
    }).catch(function () {
      grid.replaceChildren(el('div', 'empty-card', 'Не удалось загрузить данные. Обновите страницу.'));
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-add-site]'), function (button) {
    button.addEventListener('click', openSiteModal);
  });

  Array.prototype.forEach.call(siteModal.querySelectorAll('[data-close-site]'), function (node) {
    node.addEventListener('click', closeSiteModal);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !siteModal.hidden) closeSiteModal();
  });

  siteForm.addEventListener('submit', function (event) {
    event.preventDefault();
    setSiteNote('');

    var url = siteUrlInput.value.trim();
    var name = siteForm.querySelector('input[name="name"]').value.trim();

    if (url === '') {
      setSiteNote('Укажите адрес сайта.', true);
      return;
    }

    setSiteBusy(true);

    api('/api/v1/sites', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: url, name: name })
    }).then(function () {
      closeSiteModal();
      showToast('Сайт добавлен.');
      loadData();
    }).catch(function (error) {
      if (error.status === 401) {
        leave();
        return;
      }

      if (error.status === 409) {
        setSiteNote(
          error.data && error.data.error === 'domain_exists'
            ? 'Этот домен уже добавлен на другом аккаунте.'
            : 'Этот сайт уже добавлен.',
          true
        );
      } else if (error.status === 422) {
        setSiteNote('Проверьте адрес сайта.', true);
      } else if (error.status === 429) {
        setSiteNote('Слишком много попыток. Попробуйте позже.', true);
      } else {
        setSiteNote('Не удалось добавить сайт. Попробуйте ещё раз.', true);
      }
    }).finally(function () {
      setSiteBusy(false);
    });
  });

  logoutBtn.addEventListener('click', function () {
    api('/api/v1/auth/logout', { method: 'POST' }).then(leave, leave);
  });

  if (readToken() === null) {
    leave();
    return;
  }

  api('/api/v1/auth/me').then(function (data) {
    renderUser(data.user);
    gate.hidden = true;
    loadData();
  }).catch(function (error) {
    if (error.status === 401) {
      leave();
      return;
    }

    gate.textContent = 'Не удалось проверить сессию. Обновите страницу.';
  });
})();
</script>
</body>
</html>
HTML);

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
