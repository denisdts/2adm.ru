<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

/**
 * Site detail page. All data comes from the API, which reads MySQL/ClickHouse.
 */
final class SitePageController
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        $response = Response::html(<<<'HTML'
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f7f9fc">
<meta name="robots" content="noindex">
<title>Сайт — 2adm</title>
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

  main { padding: 32px 0 56px; }
  .back { display: inline-block; margin-bottom: 18px; font-size: 14px; }
  .page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 26px; }
  .page-head h1 { margin: 0 0 4px; font-size: 30px; letter-spacing: -.01em; word-break: break-word; }
  .site-slug { color: var(--muted); font-size: 14px; word-break: break-all; }
  .ssl-pill {
    flex: none; padding: 5px 12px; border-radius: 999px;
    background: #eef2f7; color: var(--muted); font-size: 13px; font-weight: 600;
  }
  .ssl-pill.ok { background: var(--ok-bg); color: var(--ok); }
  .ssl-pill.bad { background: var(--warn-bg); color: var(--warn); }

  .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 34px; }
  .info-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 18px 20px; box-shadow: var(--shadow);
  }
  .info-card span { display: block; font-size: 13px; color: var(--muted); }
  .info-card strong { display: block; margin-top: 4px; font-size: 20px; line-height: 1.3; word-break: break-word; }
  .info-card small { display: block; margin-top: 4px; font-size: 12px; color: var(--muted); }

  .section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .section-head h2 { margin: 0; font-size: 20px; }
  .chart-section { margin-bottom: 34px; }
  .chart-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); padding: 18px;
  }
  .chart-legend { display: flex; flex-wrap: wrap; gap: 10px 18px; margin-bottom: 14px; }
  .legend-item { display: inline-flex; align-items: center; gap: 7px; color: var(--muted); font-size: 13px; cursor: pointer; user-select: none; }
  .legend-item input { width: 15px; height: 15px; accent-color: var(--accent); }
  .legend-swatch { width: 10px; height: 10px; border-radius: 3px; flex: none; }
  .legend-scale { color: var(--warn); font-size: 12px; }
  .chart-canvas { display: block; width: 100%; height: 320px; }
  .table-wrap {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    box-shadow: var(--shadow); overflow: auto; max-height: 520px;
  }
  table { width: 100%; border-collapse: collapse; }
  th { position: sticky; top: 0; z-index: 1; padding: 12px 18px; background: #fbfdff; border-bottom: 1px solid var(--border); color: var(--muted); font-size: 12px; font-weight: 600; text-align: left; text-transform: uppercase; letter-spacing: .04em; }
  td { padding: 11px 18px; border-top: 1px solid #eef2f7; font-size: 14px; }
  tbody tr:first-child td { border-top: 0; }
  td.right, th.right { text-align: right; }
  td.value { color: var(--accent); font-variant-numeric: tabular-nums; white-space: nowrap; }
  td.time { color: var(--muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
  td.name { font-weight: 500; }
  .empty { padding: 28px; text-align: center; color: var(--muted); background: var(--surface); border: 1px dashed #cbd5e1; border-radius: var(--radius); }
  .notice {
    padding: 28px; text-align: center; background: var(--surface);
    border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow);
  }
  .notice h2 { margin: 0 0 8px; font-size: 20px; }
  .notice p { margin: 0 0 16px; color: var(--muted); }
  .gate {
    position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center;
    background: var(--bg); color: var(--muted); font-size: 15px; text-align: center; padding: 24px;
  }

  @media (max-width: 860px) {
    .info-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 720px) {
    .who { display: none; }
  }
  @media (max-width: 520px) {
    .wrap { width: calc(100% - 28px); }
    .status-pill { display: none; }
    .info-grid { grid-template-columns: 1fr; }
    .chart-canvas { height: 260px; }
    th, td { padding-left: 14px; padding-right: 14px; }
  }
</style>
</head>
<body>
<div class="gate" id="gate">Проверяем сессию…</div>

<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="/dash"><span class="brand-mark" aria-hidden="true"></span>2adm</a>
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
  <a class="back" href="/dash">← Все сайты</a>

  <div class="page-head">
    <div>
      <h1 id="siteName">Загрузка…</h1>
      <div class="site-slug" id="siteSlug"></div>
    </div>
    <span class="ssl-pill" id="sslPill">SSL</span>
  </div>

  <div class="notice" id="notFound" hidden>
    <h2>Сайт не найден</h2>
    <p>Он не существует или принадлежит другой компании.</p>
    <a class="btn btn-ghost" href="/dash">Вернуться в панель</a>
  </div>

  <div id="content" hidden>
    <section class="info-grid" aria-label="Данные сайта">
      <div class="info-card">
        <span>Домен зарегистрирован</span>
        <strong id="domainRegistered">—</strong>
        <small id="domainExpires"></small>
      </div>
      <div class="info-card">
        <span>SSL-сертификат</span>
        <strong id="sslStatus">—</strong>
        <small id="sslRange"></small>
      </div>
      <div class="info-card">
        <span>Сайт добавлен</span>
        <strong id="siteCreated">—</strong>
        <small>в панель мониторинга</small>
      </div>
      <div class="info-card">
        <span>Записей в истории</span>
        <strong id="historyTotal">—</strong>
        <small>доступно в ClickHouse</small>
      </div>
    </section>

    <section class="chart-section" id="chartSection" hidden>
      <div class="section-head">
        <h2>График параметров</h2>
        <span class="muted" id="chartHint">последние 200 записей</span>
      </div>
      <div class="chart-card">
        <div class="chart-legend" id="chartLegend"></div>
        <canvas class="chart-canvas" id="chart" role="img" aria-label="График параметров сайта"></canvas>
      </div>
    </section>

    <section>
      <div class="section-head">
        <h2>История запросов</h2>
        <span class="muted" id="historyHint">последние 200 записей</span>
      </div>
      <div class="table-wrap" id="historyWrap" hidden>
        <table>
          <thead>
            <tr>
              <th>Время</th>
              <th>Метрика</th>
              <th class="right">Значение</th>
            </tr>
          </thead>
          <tbody id="historyBody"></tbody>
        </table>
      </div>
      <div class="empty" id="historyEmpty" hidden>Пока нет данных по этому сайту.</div>
      <div class="empty" id="historyError" hidden>Не удалось загрузить историю.</div>
    </section>
  </div>
</main>

<script>
(function () {
  var TOKEN_KEY = 'adm_token';
  var gate = document.getElementById('gate');
  var content = document.getElementById('content');
  var notFound = document.getElementById('notFound');
  var statusEl = document.getElementById('status');
  var statusText = document.getElementById('statusText');
  var sslPill = document.getElementById('sslPill');
  var chartSection = document.getElementById('chartSection');
  var chartCanvas = document.getElementById('chart');
  var chartLegend = document.getElementById('chartLegend');
  var chartHint = document.getElementById('chartHint');
  var chartContext = chartCanvas.getContext('2d');
  var chartSeries = [];
  var chartVisible = {};
  var chartColors = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#4d7c0f', '#be185d', '#475569', '#b45309'];

  var path = window.location.pathname.replace(/\/+$/, '');
  var slug = decodeURIComponent(path.substring(path.lastIndexOf('/') + 1));

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

  function formatValue(value) {
    if (!Number.isFinite(value)) return String(value);
    return Number.isInteger(value) ? String(value) : value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
  }

  function formatDate(value) {
    if (!value) return 'Нет данных';

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
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
  }

  function parseTimestamp(value) {
    var text = String(value || '');

    if (text === '') {
      return null;
    }

    var normalized = text.indexOf('T') === -1 ? text.replace(' ', 'T') + 'Z' : text;
    var timestamp = Date.parse(normalized);

    return isNaN(timestamp) ? null : timestamp;
  }

  function formatAxis(value) {
    var absolute = Math.abs(value);

    if (absolute >= 1000000) return (value / 1000000).toFixed(1) + 'M';
    if (absolute >= 1000) return (value / 1000).toFixed(1) + 'k';
    if (absolute >= 10) return value.toFixed(0);
    if (absolute >= 1) return value.toFixed(1);

    return value.toFixed(2);
  }

  function formatClock(timestamp) {
    return new Date(timestamp).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
  }

  function formatScale(scale) {
    var text = scale >= 0.1 ? scale.toFixed(2) : scale.toPrecision(2);

    return text.replace(/0+$/, '').replace(/\.$/, '');
  }

  function buildSeries(points) {
    var groups = {};

    points.forEach(function (point) {
      var timestamp = parseTimestamp(point.ts);
      var value = Number(point.value);

      if (timestamp === null || !isFinite(value)) {
        return;
      }

      if (!groups[point.name]) {
        groups[point.name] = [];
      }

      groups[point.name].push({ t: timestamp, value: value });
    });

    var series = Object.keys(groups).sort().map(function (name) {
      var values = groups[name].sort(function (a, b) { return a.t - b.t; });
      var maxAbs = 0;

      values.forEach(function (point) {
        maxAbs = Math.max(maxAbs, Math.abs(point.value));
      });

      return { name: name, points: values, maxAbs: maxAbs, scale: 1 };
    });

    var maxima = series.map(function (item) { return item.maxAbs; }).sort(function (a, b) { return b - a; });
    var target = maxima.length > 1 ? maxima[1] : (maxima[0] || 0);

    series.forEach(function (item) {
      if (item.maxAbs > 0 && target > 0 && item.maxAbs > target * 2) {
        item.scale = target / item.maxAbs;
      }
    });

    return series;
  }

  function buildLegend(series) {
    chartLegend.replaceChildren();

    series.forEach(function (item, index) {
      item.color = chartColors[index % chartColors.length];

      var label = el('label', 'legend-item');
      var checkbox = el('input');
      checkbox.type = 'checkbox';
      checkbox.checked = chartVisible[item.name] !== false;
      checkbox.addEventListener('change', function () {
        chartVisible[item.name] = checkbox.checked;
        drawChart();
      });

      var swatch = el('span', 'legend-swatch');
      swatch.style.background = item.color;

      label.append(checkbox, swatch, el('span', '', item.name));

      if (item.scale !== 1) {
        label.append(el('span', 'legend-scale', '×' + formatScale(item.scale)));
      }

      chartLegend.append(label);
    });
  }

  function drawChart() {
    var width = Math.floor(chartCanvas.getBoundingClientRect().width);
    var height = Math.floor(chartCanvas.getBoundingClientRect().height) || 320;

    if (width < 80) {
      return;
    }

    var dpr = window.devicePixelRatio || 1;
    chartCanvas.width = Math.floor(width * dpr);
    chartCanvas.height = Math.floor(height * dpr);

    var ctx = chartContext;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);

    var visible = chartSeries.filter(function (item) { return chartVisible[item.name] !== false; });

    if (!visible.length) {
      ctx.fillStyle = '#5b6b82';
      ctx.font = '14px ui-sans-serif, system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText('Все параметры скрыты', width / 2, height / 2);
      return;
    }

    var left = 58;
    var right = 18;
    var top = 16;
    var bottom = 34;
    var plotWidth = width - left - right;
    var plotHeight = height - top - bottom;
    var minTime = Infinity;
    var maxTime = -Infinity;
    var minValue = 0;
    var maxValue = 0;

    visible.forEach(function (item) {
      item.points.forEach(function (point) {
        var value = point.value * item.scale;

        minTime = Math.min(minTime, point.t);
        maxTime = Math.max(maxTime, point.t);
        minValue = Math.min(minValue, value);
        maxValue = Math.max(maxValue, value);
      });
    });

    if (minTime === Infinity || maxTime === -Infinity) {
      return;
    }

    if (minTime === maxTime) {
      minTime -= 1000;
      maxTime += 1000;
    }

    if (minValue === maxValue) {
      maxValue = minValue + 1;
    }

    var range = maxValue - minValue;
    minValue -= range * 0.05;
    maxValue += range * 0.05;

    function xOf(timestamp) {
      return left + (timestamp - minTime) / (maxTime - minTime) * plotWidth;
    }

    function yOf(value) {
      return top + (maxValue - value) / (maxValue - minValue) * plotHeight;
    }

    ctx.font = '11px ui-sans-serif, system-ui, sans-serif';
    ctx.textBaseline = 'middle';
    var gridCount = 4;

    for (var i = 0; i <= gridCount; i++) {
      var gridValue = maxValue - (maxValue - minValue) * i / gridCount;
      var gridY = top + plotHeight * i / gridCount;

      ctx.strokeStyle = '#e2e8f0';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(left, gridY);
      ctx.lineTo(left + plotWidth, gridY);
      ctx.stroke();

      ctx.fillStyle = '#5b6b82';
      ctx.textAlign = 'right';
      ctx.fillText(formatAxis(gridValue), left - 8, gridY);
    }

    for (var j = 0; j <= 4; j++) {
      var gridX = left + plotWidth * j / 4;

      ctx.strokeStyle = '#f1f5f9';
      ctx.beginPath();
      ctx.moveTo(gridX, top);
      ctx.lineTo(gridX, top + plotHeight);
      ctx.stroke();

      ctx.fillStyle = '#5b6b82';
      ctx.textAlign = 'center';
      ctx.fillText(formatClock(minTime + (maxTime - minTime) * j / 4), gridX, height - 14);
    }

    ctx.strokeStyle = '#e2e8f0';
    ctx.strokeRect(left, top, plotWidth, plotHeight);

    visible.forEach(function (item) {
      ctx.strokeStyle = item.color;
      ctx.fillStyle = item.color;
      ctx.lineWidth = 2;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';

      if (item.points.length === 1) {
        var point = item.points[0];
        ctx.beginPath();
        ctx.arc(xOf(point.t), yOf(point.value * item.scale), 3, 0, Math.PI * 2);
        ctx.fill();
        return;
      }

      ctx.beginPath();

      item.points.forEach(function (point, index) {
        var px = xOf(point.t);
        var py = yOf(point.value * item.scale);

        if (index === 0) {
          ctx.moveTo(px, py);
        } else {
          ctx.lineTo(px, py);
        }
      });

      ctx.stroke();
    });
  }

  function renderUser(user) {
    document.getElementById('userName').textContent = user.name || user.email;
    document.getElementById('userEmail').textContent = user.company_name
      ? user.email + ' · ' + user.company_name
      : user.email;
  }

  function setHealth(ok) {
    statusEl.className = 'status-pill ' + (ok ? 'ok' : 'bad');
    statusText.textContent = ok ? 'Все системы работают' : 'Частичный сбой';
  }

  function renderSite(site) {
    document.title = (site.name || site.slug) + ' — 2adm';
    document.getElementById('siteName').textContent = site.name || site.slug;
    document.getElementById('siteSlug').textContent = site.slug;

    var domainRegistered = site.domain.registered_at;
    var domainExpires = site.domain.expires_at;

    document.getElementById('domainRegistered').textContent = formatDate(domainRegistered);
    document.getElementById('domainExpires').textContent = domainExpires
      ? 'истекает ' + formatDate(domainExpires)
      : (domainRegistered ? 'дата окончания неизвестна' : '');

    var ssl = site.ssl || {};
    var sslStatus = document.getElementById('sslStatus');
    var sslRange = document.getElementById('sslRange');

    if (ssl.has_certificate === null || ssl.has_certificate === undefined) {
      sslStatus.textContent = 'Нет данных';
      sslRange.textContent = 'ожидает проверки';
      sslPill.className = 'ssl-pill';
      sslPill.textContent = 'SSL';
    } else if (ssl.has_certificate) {
      sslStatus.textContent = 'Есть';
      sslRange.textContent = formatDate(ssl.valid_from) + ' — ' + formatDate(ssl.valid_to);
      sslPill.className = 'ssl-pill ok';
      sslPill.textContent = 'SSL активен';
    } else {
      sslStatus.textContent = 'Нет';
      sslRange.textContent = 'сертификат не найден';
      sslPill.className = 'ssl-pill bad';
      sslPill.textContent = 'Без SSL';
    }

    document.getElementById('siteCreated').textContent = formatDate(site.created_at);
    content.hidden = false;
  }

  function renderHistory(response) {
    if (response === null) {
      chartSection.hidden = true;
      document.getElementById('historyWrap').hidden = true;
      document.getElementById('historyError').hidden = false;
      return;
    }

    document.getElementById('historyTotal').textContent = String(response.total || 0);

    var points = response.points || [];
    var body = document.getElementById('historyBody');
    body.replaceChildren();

    if (!points.length) {
      chartSection.hidden = true;
      document.getElementById('historyWrap').hidden = true;
      document.getElementById('historyEmpty').hidden = false;
      return;
    }

    var series = buildSeries(points);

    series.forEach(function (item) {
      if (typeof chartVisible[item.name] === 'undefined') {
        chartVisible[item.name] = true;
      }
    });

    chartSeries = series;
    chartSection.hidden = series.length === 0;
    document.getElementById('historyEmpty').hidden = series.length !== 0;
    chartHint.textContent = 'последние ' + points.length + ' записей';

    if (series.length > 0) {
      buildLegend(series);
      window.setTimeout(drawChart, 0);
    }

    points.forEach(function (point) {
      var row = el('tr');
      row.append(el('td', 'time', formatDateTime(point.ts)));
      row.append(el('td', 'name', point.name));
      row.append(el('td', 'value right', formatValue(point.value)));
      body.append(row);
    });

    document.getElementById('historyWrap').hidden = false;
    document.getElementById('historyHint').textContent = 'последние ' + points.length + ' записей';
  }

  function loadSite() {
    Promise.all([
      api('/api/v1/sites/' + encodeURIComponent(slug)),
      api('/api/v1/sites/' + encodeURIComponent(slug) + '/history?limit=200').catch(function () { return null; })
    ]).then(function (results) {
      renderSite(results[0].site);
      renderHistory(results[1]);
      gate.hidden = true;
    }).catch(function (error) {
      if (error.status === 401) {
        leave();
        return;
      }

      gate.hidden = true;
      content.hidden = true;

      if (error.status === 404) {
        notFound.hidden = false;
        return;
      }

      notFound.querySelector('h2').textContent = 'Не удалось загрузить сайт';
      notFound.querySelector('p').textContent = 'Попробуйте обновить страницу.';
      notFound.hidden = false;
    });
  }

  window.addEventListener('resize', function () {
    if (!chartSection.hidden) {
      drawChart();
    }
  });

  fetch('/api/v1/health', { headers: { Accept: 'application/json' } })
    .then(function (response) { return response.json(); })
    .then(function (data) { setHealth(data && data.status === 'ok'); })
    .catch(function () { setHealth(false); });

  document.getElementById('logoutBtn').addEventListener('click', function () {
    api('/api/v1/auth/logout', { method: 'POST' }).then(leave, leave);
  });

  if (readToken() === null) {
    leave();
    return;
  }

  api('/api/v1/auth/me').then(function (data) {
    renderUser(data.user);
    loadSite();
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
