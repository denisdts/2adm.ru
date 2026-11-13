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
