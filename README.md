# Monitoring service

Сервис мониторинга: приём метрик и API для работы с временными рядами.

- **PHP 8.5-FPM + nginx** в одном контейнере (supervisor).
- Вход извне — через прокси на порт **7060**.
- Код и сайт с API — в `www/`.
- **MySQL** — локально в контейнере, в продакшене — внешний сервер.
- **ClickHouse** и **Redis** — пока внутри проекта (`docker-compose.embedded.yml`), позже переезжают на отдельные серверы.

## Структура

```
docker/
  php/Dockerfile            # PHP 8.5-FPM + nginx + supervisor + composer
  php/entrypoint.sh         # рендер конфигов, ожидание БД, миграции, запуск
  php/conf.d/zz-fpm.conf    # пул php-fpm
  php/templates/            # шаблон php.ini
  nginx/                    # nginx.conf и шаблон сайта
  supervisor/               # запуск php-fpm + nginx
www/
  public/index.php          # front controller
  bin/console.php           # миграции и управление ключами
  src/                      # приложение
docker-compose.yml          # базовый сервис app
docker-compose.local.yml    # локальный MySQL + live-код
docker-compose.embedded.yml # ClickHouse + Redis внутри проекта
docker-compose.prod.yml     # продакшен: внешний MySQL
.env.example                # шаблон окружения
```

## Локальный запуск

```sh
cp .env.example .env

docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.local.yml \
  up -d --build
```

Создать сервис и API-ключ:

```sh
docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.local.yml \
  exec app php bin/console.php service:create api "API"

docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.local.yml \
  exec app php bin/console.php api-key:create "Agent" --service=api
```

Ключ выводится один раз — сохраните его. Дашборд и health:

- `http://localhost:7060/` — дашборд;
- `http://localhost:7060/healthz` — liveness (используется Docker);
- `http://localhost:7060/api/v1/health` — состояние MySQL / ClickHouse / Redis.

## Продакшен

MySQL всегда внешний. ClickHouse и Redis пока поднимаются контейнерами из `docker-compose.embedded.yml`.

```sh
export APP_KEY="$(openssl rand -hex 32)"
export DB_HOST=mysql.example.com
export DB_PASSWORD='...'

docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  up -d --build
```

Когда ClickHouse и Redis переедут на отдельные серверы, запускайте **без** embedded-файла и задайте их адреса:

```sh
export CLICKHOUSE_HOST=clickhouse.example.com
export REDIS_HOST=redis.example.com

docker compose \
  -f docker-compose.yml \
  -f docker-compose.prod.yml \
  up -d --build
```

`APP_KEY` в продакшене обязателен: контейнер не стартует с пустым или дефолтным значением. Смена `APP_KEY` инвалидирует все выпущенные API-ключи.

## API

Все запросы — JSON.

| Метод | Путь | Назначение |
| --- | --- | --- |
| `POST` | `/api/v1/metrics` | приём метрик, заголовок `X-Api-Key` |
| `GET` | `/api/v1/metrics/{service}` | временной ряд (`name`, `from`, `to`, `step`, `limit`) |
| `GET` | `/api/v1/metrics/{service}/latest` | последние значения из Redis |
| `GET` | `/api/v1/services` | список сервисов |
| `GET` | `/api/v1/health` | состояние хранилищ |

Пример отправки:

```sh
curl -X POST http://localhost:7060/api/v1/metrics \
  -H 'X-Api-Key: mk_xxxxxxxx_yyyyyyyy' \
  -H 'Content-Type: application/json' \
  -d '{
    "service": "api",
    "metrics": [
      {"name": "cpu.usage", "value": 12.5, "type": "gauge", "labels": {"host": "web-1"}},
      {"name": "requests.total", "value": 1530, "type": "counter"}
    ]
  }'
```

Ответ: `202 {"accepted": 2}`.

## Полезные команды

```sh
# миграции вручную
docker compose ... exec app php bin/console.php migrate

# список ключей
docker compose ... exec app php bin/console.php api-key:list

# проверка бэкендов
docker compose ... exec app php bin/console.php health

# логи приложения
docker compose ... logs -f app
```

## Деплой в продакшен (GitHub Actions)

Схема: push в ветку `production` → GitHub Actions → SSH на сервер → обновление кода до `origin/production` → сборка образа → `docker compose up -d` → ожидание healthcheck.

Workflow: `.github/workflows/deploy-production.yml`.

Деплой рассчитан на уже настроенную инфраструктуру: на сервере есть пользователь `app`, Docker и другие проекты, которые деплоятся из Git. Создавать нового пользователя и заново устанавливать Docker не нужно.

### 1. Что проверить/сделать на сервере

> Ветку `production` создайте заранее (см. п. 2.3) — она понадобится для клонирования и деплоя.

**1.1. Проверить Docker и Compose у пользователя `app`:**

```sh
sudo -u app docker compose version
sudo -u app docker ps
```

Если используется старый `docker-compose` v1, доустановите плагин `docker-compose-plugin`. Для сборки PHP-расширений желательно **не менее 2 ГБ RAM** (или swap).

**1.2. Проверить SSH-доступ для GitHub Actions.** Если другие проекты уже деплоятся под `app` с тем же ключом, ничего добавлять не нужно — workflow использует тот же секрет `RUNNER_KEY`. Если для этого проекта нужен отдельный ключ:

```sh
# на рабочей машине
ssh-keygen -t ed25519 -f ~/.ssh/2adm_deploy -C "github-actions-2adm" -N ""
ssh app@SERVER_IP "install -d -m 700 /home/app/.ssh && cat >> /home/app/.ssh/authorized_keys && chmod 600 /home/app/.ssh/authorized_keys" < ~/.ssh/2adm_deploy.pub
```

Приватный ключ целиком добавляется в GitHub Secret `RUNNER_KEY` (п. 2.1).

**1.3. Склонировать репозиторий в существующий каталог проектов:**

```sh
sudo -u app git clone https://github.com/denisdts/2adm.ru.git /data/prod/2adm.ru
sudo -u app git -C /data/prod/2adm.ru checkout production
```

`/data/prod` уже используется другими проектами и должен быть доступен на запись пользователю `app`. Если каталог другой — задайте его в `PRODUCTION_PATH` (п. 2.1).

Если репозиторий станет приватным — добавьте на сервере deploy key для `app` и внесите его в GitHub: **Settings → Deploy keys → Add deploy key** (read-only).

**1.4. Создать `.env`** (файл не в Git и сохраняется между деплоями):

```sh
cd /data/prod/2adm.ru
sudo -u app cp .env.example .env
sudo -u app nano .env
```

Минимум для продакшена:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<openssl rand -hex 32>
APP_PORT=7060
# 127.0.0.1 — если прокси стоит на этом же сервере,
# 0.0.0.0 — если прокси внешний (ограничьте порт firewall'ом)
APP_BIND=127.0.0.1

DB_HOST=mysql.example.com
DB_PORT=3306
DB_DATABASE=monitoring
DB_USERNAME=monitoring
DB_PASSWORD=<пароль>

CLICKHOUSE_HOST=clickhouse
CLICKHOUSE_PASSWORD=<пароль>
REDIS_HOST=redis
REDIS_PASSWORD=<пароль или пусто>

MIGRATE_ON_START=true
AUTO_COMPOSER_INSTALL=false
```

`APP_KEY` обязателен: с пустым или дефолтным значением контейнер в продакшене не стартует. Смена `APP_KEY` инвалидирует все API-ключи.

**1.5. Подготовить внешний MySQL:**

- создать отдельную базу `monitoring` и пользователя (не переиспользуйте базу другого проекта);
- разрешить подключение с IP этого сервера;
- таблицы создаст приложение при старте (`MIGRATE_ON_START=true`). Если схемой управляете отдельно — поставьте `false`.

**1.6. Настроить сеть и прокси:**

- наружу публикуется только `APP_PORT` (7060); проверьте, что порт не занят другим проектом: `ss -ltnp | grep 7060`;
- ClickHouse и Redis портов не публикуют и доступны только внутри Docker-сети;
- если на сервере уже есть общий nginx/прокси, добавьте upstream на `127.0.0.1:7060`;
- firewall: SSH + 7060 только с адреса прокси. GitHub-hosted runner подключается по SSH с динамических IP — либо разрешите SSH для GitHub Actions IP ranges, либо используйте self-hosted runner (как в других проектах).

**1.7. Первый запуск вручную** (проверить, что всё поднимается):

```sh
cd /data/prod/2adm.ru
sudo -u app docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  up -d --build

sudo -u app docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  ps

curl -fsS http://127.0.0.1:7060/healthz
```

**1.8. Создать сервис и API-ключ:**

```sh
sudo -u app docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  exec app php bin/console.php service:create api "API"

sudo -u app docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  exec app php bin/console.php api-key:create "Agent" --service=api
```

### 2. Что добавить в GitHub

**2.1. Variables и Secrets** — репозиторий → **Settings → Secrets and variables → Actions**.

Используются те же имена, что и в остальных деплой-workflow проекта, поэтому можно переиспользовать существующие значения:

| Тип | Имя | Значение |
| --- | --- | --- |
| Variable | `PRODUCTION_SERVER` | IP или домен сервера |
| Variable | `PRODUCTION_USER` | `app` |
| Variable | `PRODUCTION_PORT` | SSH-порт (обычно `22`) |
| Variable | `PRODUCTION_PATH` | `/data/prod/2adm.ru` (необязательно, это дефолт) |
| Secret | `RUNNER_KEY` | приватный SSH-ключ, который уже используется для деплоя под `app` |

Если эти переменные/секрет уже заданы на уровне организации или environment — в репозитории их дублировать не нужно, workflow их унаследует. Иначе добавьте на уровне репозитория или environment `production`.

**2.2. Environment `production`** (опционально, но рекомендуется) — **Settings → Environments → New environment → production**. Там можно включить required reviewers, запретить деплой без подтверждения и хранить переменные/секреты на уровне окружения. Workflow уже использует `environment: production`.

**2.3. Ветка `production`.** Workflow срабатывает на push в неё. Создайте ветку после того, как добавлены секреты:

```sh
git checkout main
git pull
git checkout -b production
git push -u origin production
```

Дальнейшие деплои — обычный push:

```sh
git checkout production
git merge main
git push origin production
```

**2.4. Защита ветки** (опционально) — **Settings → Branches → Add branch protection rule** для `production`: запретить force-push, требовать PR/review.

Ручной запуск: **Actions → Deploy production → Run workflow**.

### 3. Что делает workflow

1. Подключается по SSH под пользователем `app` с ключом из `RUNNER_KEY`.
2. Переходит в `PRODUCTION_PATH` (по умолчанию `/data/prod/2adm.ru`), делает `git fetch` и переключается на `origin/production` (`.env` не затрагивается — он не в Git).
3. Собирает образ `app` (`docker compose build --pull app`). Если сборка упала — работающие контейнеры не перезапускаются.
4. Поднимает стек (`up -d --remove-orphans`) и ждёт статус `healthy` у контейнера `monitoring-app` (до 150 секунд).
5. При неуспехе печатает последние логи и завершает job с ошибкой.

### 4. Если деплой не прошёл

- `Permission denied (publickey)` — `RUNNER_KEY` не соответствует ключу в `/home/app/.ssh/authorized_keys` или неверные `PRODUCTION_USER` / `PRODUCTION_PORT`.
- `permission denied while trying to connect to the Docker daemon` — пользователь `app` не в группе `docker`.
- `cd: no such file or directory` — неверный `PRODUCTION_PATH` или репозиторий не склонирован под `app`.
- `app did not become healthy` — смотрите логи в job: внешний MySQL недоступен, неверный `DB_*`, не задан `APP_KEY`, занят порт 7060.
- `git fetch` не работает — у `app` нет доступа к репозиторию (deploy key или приватность).

## Переменные окружения

Основные (`см. .env.example`):

- `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_PORT` (7060);
- `DB_*` — MySQL;
- `CLICKHOUSE_*` — ClickHouse;
- `REDIS_*` — Redis;
- `API_RATE_LIMIT_PER_MINUTE` — лимит запросов на ключ;
- `METRICS_RETENTION_DAYS` — TTL сырых метрик в ClickHouse;
- `MIGRATE_ON_START` — применять схему при старте;
- `AUTO_COMPOSER_INSTALL` — ставить зависимости при старте (только локально).
