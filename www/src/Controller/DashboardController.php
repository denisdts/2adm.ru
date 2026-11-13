<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Response;

/**
 * Small self-contained dashboard; data is fetched from the public API.
 */
final class DashboardController
{
    public function index(): Response
    {
        return Response::html(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monitoring</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 32px; background: #0f1115; color: #e6e8ee;
    font: 15px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }
  h1 { font-size: 20px; margin: 0 0 4px; }
  header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 28px; }
  .status { font-size: 12px; padding: 3px 10px; border-radius: 999px; background: #1c212b; color: #9aa4b2; }
  .status.ok { background: #11331f; color: #65d78a; }
  .status.bad { background: #3a1a1a; color: #ff8a8a; }
  .muted { color: #7f8a99; }
  .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
  .card { background: #161a21; border: 1px solid #232a35; border-radius: 10px; padding: 16px 18px; }
  .card h2 { font-size: 15px; margin: 0 0 12px; display: flex; justify-content: space-between; gap: 8px; }
  .card h2 span { color: #6f7a88; font-weight: 400; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 6px 0; border-top: 1px solid #20262f; vertical-align: top; }
  tr:first-child td { border-top: 0; }
  td.value { text-align: right; font-variant-numeric: tabular-nums; color: #8fd3ff; }
  td.time { text-align: right; color: #6f7a88; font-size: 12px; padding-left: 12px; }
  .labels { display: block; color: #7f8a99; font-size: 11px; }
  .empty { color: #6f7a88; }
</style>
</head>
<body>
<header>
  <h1>Monitoring</h1>
  <span id="status" class="status">checking…</span>
  <span class="muted" id="updated"></span>
</header>
<div id="grid" class="grid"></div>
<script>
const grid = document.getElementById('grid');
const statusEl = document.getElementById('status');
const updatedEl = document.getElementById('updated');

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function formatValue(value) {
  if (!Number.isFinite(value)) return String(value);
  return Number.isInteger(value) ? String(value) : value.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
}

function renderService(service, metrics) {
  const card = el('div', 'card');
  const head = el('h2');
  head.append(el('span', '', service.name || service.slug), el('span', '', service.slug));
  card.append(head);

  if (!metrics.length) {
    card.append(el('div', 'empty', 'no metrics yet'));
    return card;
  }

  const table = el('table');
  for (const metric of metrics) {
    const row = el('tr');
    const name = el('td');
    name.append(document.createTextNode(metric.name));
    const labels = Object.entries(metric.labels || {});
    if (labels.length) {
      name.append(el('span', 'labels', labels.map(([k, v]) => `${k}=${v}`).join(' ')));
    }
    row.append(name);
    row.append(el('td', 'value', formatValue(metric.value)));
    row.append(el('td', 'time', (metric.ts || '').slice(11, 19)));
    table.append(row);
  }
  card.append(table);
  return card;
}

async function load() {
  try {
    const [health, servicesResponse] = await Promise.all([
      fetch('/api/v1/health').then((r) => r.json()).catch(() => null),
      fetch('/api/v1/services').then((r) => r.json()),
    ]);

    if (health) {
      const ok = health.status === 'ok';
      statusEl.textContent = ok ? 'all systems ok' : 'degraded';
      statusEl.className = 'status ' + (ok ? 'ok' : 'bad');
    }

    const services = servicesResponse.services || [];
    const cards = await Promise.all(services.map(async (service) => {
      const latest = await fetch('/api/v1/metrics/' + encodeURIComponent(service.slug) + '/latest')
        .then((r) => r.json())
        .catch(() => ({ metrics: [] }));
      return renderService(service, latest.metrics || []);
    }));

    grid.replaceChildren(...cards);
    if (!cards.length) {
      grid.append(el('div', 'card empty', 'Create a service and an API key to start ingesting metrics.'));
    }

    updatedEl.textContent = 'updated ' + new Date().toLocaleTimeString();
  } catch (error) {
    statusEl.textContent = 'unavailable';
    statusEl.className = 'status bad';
  }
}

load();
setInterval(load, 15000);
</script>
</body>
</html>
HTML);
    }
}
