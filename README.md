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
checker/
  main.go                   # Go-сервис проверки сайтов
  .env.example              # настройки чекера
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

- `http://localhost:7060/` — сайт сервиса и вход в панель;
- `http://localhost:7060/dash/` — панель мониторинга (после входа);
- `http://localhost:7060/dash/site/{slug}` — страница сайта: данные из MySQL, история из ClickHouse, график последних 200 записей с переключателями параметров;
- `http://localhost:7060/admin` — админ-панель (только группа `administrators`);
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
| `GET` | `/api/v1/services` | список сервисов с доменными/SSL-полями; `?ssl=true&domain=true` включает сбор данных чекером; требует `X-Checker-Guid` зарегистрированного чекера |
| `GET` | `/api/v1/sites` | сайты компании пользователя, `Authorization: Bearer <token>` |
| `POST` | `/api/v1/sites` | добавить сайт компании, JSON `{url, name}`, Bearer-токен |
| `PUT` | `/api/v1/sites/{slug}/metadata` | доменные и SSL-данные сайта от чекера, `X-Api-Key` |
| `POST` | `/api/v1/checkers/heartbeat` | heartbeat чекера, `X-Api-Key` |
| `GET` | `/api/v1/admin/users` | пользователи, Bearer администратора |
| `GET` | `/api/v1/admin/companies` | компании, Bearer администратора |
| `GET` | `/api/v1/admin/sites` | сайты, Bearer администратора |
| `GET` | `/api/v1/admin/checkers` | чекеры, Bearer администратора |
| `POST` | `/api/v1/admin/checkers` | добавить чекер: `{name, bash_command, guid}`; `guid` генерируется на форме, если не передан |
| `GET` | `/api/v1/admin/notifications` | уведомления, Bearer администратора |
| `POST` | `/api/v1/admin/administrators` | создать администратора |
| `GET` | `/api/v1/sites/{slug}` | данные сайта из MySQL: домен, SSL, дата добавления |
| `GET` | `/api/v1/sites/{slug}/history` | история метрик сайта из ClickHouse (по умолчанию 200 записей, `limit` до 500) |
| `GET` | `/api/v1/health` | состояние хранилищ |
| `POST` | `/api/v1/auth/login` | вход в панель, JSON `{email, password}` → bearer-токен |
| `POST` | `/api/v1/auth/register` | регистрация, JSON `{name, company, email, password}` → bearer-токен и созданная компания |
| `GET` | `/api/v1/auth/me` | текущий пользователь, заголовок `Authorization: Bearer <token>` |
| `POST` | `/api/v1/auth/logout` | выход, отзывает токен |

Все запросы чекера (`/api/v1/services`, `/api/v1/metrics`, `/api/v1/sites/{slug}/metadata`, `/api/v1/checkers/heartbeat`) несут заголовок `X-Checker-Guid` с UUID инстанса. API хранит его в `checkers.guid`, обновляет `checkers.last_seen_at` и `checkers.ip` (IP источника запроса, с учётом `APP_TRUSTED_PROXIES`) на каждом запросе, записывает `checker_id` в строки метрик ClickHouse и `services.last_checker_id` / `last_checked_at` при обновлении метаданных. `GET /api/v1/services` отдаётся только при наличии заголовка с GUID зарегистрированного чекера (иначе `401`). Для heartbeat без заголовка API сгенерирует GUID и вернёт его в ответе.

Данные страницы сайта `/dash/site/{slug}`:

- `domain.registered_at`, `domain.expires_at` — колонки `services` в MySQL; заполняет Go-чекер;
- `ssl.has_certificate`, `ssl.valid_from`, `ssl.valid_to` — заполняет Go-чекер при `CHECK_SSL=true`;
- история запросов — таблица `metrics` в ClickHouse (`/api/v1/sites/{slug}/history`).

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

# пользователь для входа на сайт
docker compose ... exec app php bin/console.php user:create admin@example.com "Admin" --password='...'

# проверка бэкендов
docker compose ... exec app php bin/console.php health

# логи приложения
docker compose ... logs -f app
```

## Группы и администраторы

- Группы по умолчанию: `administrators` и `clients`; все новые пользователи попадают в `clients`.
- При миграции создаётся пользователь `admin` (`admin@2adm.ru`) с паролем по умолчанию `Ui2reer1asheeph2`. После первого входа пароль нужно сменить.
- Пароли в БД хранятся только в виде bcrypt-хешей (`password_hash`), plaintext не сохраняется.
- Раздел `/admin` и все `/api/v1/admin/*` доступны только группе `administrators`; создавать администраторов может только администратор.

## Checker (Go)

Локальный сервис проверки сайтов: раз в 60 секунд получает `/api/v1/services?ssl=true&domain=true`, проверяет каждый домен, отправляет метрики `http.status`, `response.time`, `check.up`, `http.ok` в `/api/v1/metrics`, данные домена и SSL — в `PUT /api/v1/sites/{slug}/metadata`.

```sh
# глобальный ключ для чекера
docker compose ... exec app php bin/console.php api-key:create "Site checker" --global

# настройки
cp checker/.env.example checker/.env
# указать MONITOR_API_KEY из вывода команды выше

# запуск
cd checker
go run .
```

Переменные: `MONITOR_BASE_URL`, `MONITOR_API_KEY`, `CHECKER_GUID`, `CHECK_INTERVAL` (по умолчанию `60s`), `CHECK_TIMEOUT`, `CHECK_CONCURRENCY`, `CHECK_SSL`, `CHECK_DOMAIN`, `CHECK_DOMAIN_INTERVAL` (по умолчанию `24h`), `CHECKER_NAME`, `CHECK_USER_AGENT`.

`CHECKER_GUID` — уникальный UUID чекера. `install.sh` генерирует его при установке и записывает в `checker.env`. Если переменная пуста, чекер ищет/создаёт файл `checker.guid` рядом с бинарником (можно переопределить через `CHECKER_GUID_FILE`). GUID отправляется в заголовке `X-Checker-Guid` и в теле heartbeat, по нему API отличает чекеры и привязывает к ним метрики и сайты. В админке при создании чекера GUID генерируется автоматически, показывается под названием только для чтения и сохраняется в `checkers.guid` — его нужно указать в `checker.env` на сервере.

### Сборка и установка без Go и Docker

Чекер — статический бинарник (`CGO_ENABLED=0`) без внешних зависимостей. На сервере нужны только сам бинарник и файл `checker.env` рядом с ним.

```sh
# собрать linux/amd64, linux/arm64, linux/armv7
cd checker
VERSION=1.0.0 ./build.sh
# артефакты: checker/dist/
```

Готовый архив linux/amd64 также доступен в корне сайта: `https://2adm.ru/2adm-checker_linux_amd64.tar.gz` (внутри бинарник, `checker.env.example` и `install.sh`).

На сервер копируются `2adm-checker_linux_<arch>`, `checker.env.example` и `install.sh`:

```sh
# на сервере, от root
sudo ./install.sh ./2adm-checker_linux_amd64 ./checker.env.example
# либо без указания путей: файлы ищутся рядом с install.sh
sudo ./install.sh

# обновление: повторный запуск заменит бинарник, checker.env сохранится
sudo ./install.sh ./2adm-checker_linux_amd64

# без systemd: только разложить файлы
sudo ./install.sh --no-systemd

# удалить
sudo ./install.sh --uninstall
```

По умолчанию файлы ставятся в `/opt/2adm-checker`, сервис — `2adm-checker.service`. Логи: `journalctl -u 2adm-checker -f`. Бинарник читает `checker.env` из своего каталога (можно переопределить через `CHECKER_ENV=/path/to/env`).

Релиз артефактов: тег `checker-v*` → workflow `.github/workflows/checker-release.yml` собирает бинарники и публикует в GitHub Release. Установка из релиза:

```sh
sudo ./install.sh --download https://github.com/<org>/<repo>/releases/latest/download
```

## Деплой в продакшен (GitHub Actions)

Схема как в `detot.ru`: push в ветку `production` → GitHub Actions → `rsync` файлов проекта на сервер (без `.git`, `.github`, `.env`) → `docker compose build && up -d` на сервере.

На сервере **не нужен git-клон** проекта: workflow сам загружает актуальные файлы через `rsync`. Серверный `.env` не перезаписывается.

Workflow: `.github/workflows/deploy-production.yml`.

### 1. Что сделать на сервере

**1.1. Проверить Docker, Compose v2 и rsync:**

```sh
sudo -u app docker compose version
sudo -u app docker ps
command -v rsync
```

Если `rsync` отсутствует:

```sh
sudo apt-get update && sudo apt-get install -y rsync
```

Пользователь `app` уже используется для других проектов и должен быть в группе `docker`. Для сборки PHP-расширений желательно **не менее 2 ГБ RAM** (или swap).

**1.2. Добавить SSH-ключ GitHub Actions в `authorized_keys` пользователя `app`.** Если для сервера уже используется рабочий ключ из других проектов, можно применить его. Если нужен отдельный:

```sh
# на рабочей машине
ssh-keygen -t ed25519 -f ~/.ssh/2adm_deploy -C "github-actions-2adm" -N ""
ssh app@SERVER_IP "install -d -m 700 /home/app/.ssh && cat >> /home/app/.ssh/authorized_keys && chmod 600 /home/app/.ssh/authorized_keys" < ~/.ssh/2adm_deploy.pub
```

Приватный ключ `~/.ssh/2adm_deploy` целиком добавляется в GitHub Secret `SSH_PRIVATE_KEY`. Формат — OpenSSH, без пароля.

**1.3. Создать каталог деплоя** (по аналогии с `/opt/detot.ru`):

```sh
sudo mkdir -p /opt/2adm.ru
sudo chown app:app /opt/2adm.ru
```

Каталог должен быть доступен на запись пользователю `app`. Путь можно выбрать любой существующий — он задаётся секретом `DEPLOY_PATH`.

> При деплое содержимое каталога синхронизируется с репозиторием (`rsync --delete`). Не храните там ручные файлы — они будут удалены. Исключения: `.env` и каталоги `www/vendor`, `www/var`.

**1.4. Создать `.env` в каталоге деплоя.** Его нужно подготовить до первого запуска:

```sh
sudo -u app nano /opt/2adm.ru/.env
```

Либо загрузить шаблон с рабочей машины и отредактировать:

```sh
scp .env.example app@SERVER_IP:/opt/2adm.ru/.env
ssh app@SERVER_IP 'nano /opt/2adm.ru/.env'
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
- таблицы создаст приложение при старте (`MIGRATE_ON_START=true`).

**1.6. Настроить сеть и прокси:**

- наружу публикуется только `APP_PORT` (7060); проверьте, что порт не занят: `ss -ltnp | grep 7060`;
- ClickHouse и Redis портов не публикуют;
- если на сервере уже есть общий nginx/прокси, добавьте upstream на `127.0.0.1:7060`;
- firewall: SSH (для GitHub Actions) и 7060 только с адреса прокси.

**1.7. Первый деплой** — push в `production` (или ручной запуск workflow). После успешного деплоя проверьте:

```sh
cd /opt/2adm.ru
sudo -u app docker compose \
  -f docker-compose.yml \
  -f docker-compose.embedded.yml \
  -f docker-compose.prod.yml \
  ps

curl -fsS http://127.0.0.1:7060/healthz
```

**1.8. Создать сервис и API-ключ:**

```sh
cd /opt/2adm.ru
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

Репозиторий → **Settings → Secrets and variables → Actions → Secrets**. Имена — как в `detot.ru`:

| Секрет | Назначение | Пример |
| --- | --- | --- |
| `SSH_HOST` | хост или IP сервера | `203.0.113.10` |
| `SSH_USER` | пользователь для деплоя | `app` |
| `SSH_PRIVATE_KEY` | приватный SSH-ключ целиком | содержимое `~/.ssh/2adm_deploy` |
| `SSH_PORT` | SSH-порт, необязательно | `22` |
| `DEPLOY_PATH` | каталог на сервере | `/opt/2adm.ru` |
| `SSH_KNOWN_HOSTS` | ключ хоста, необязательно | вывод `ssh-keyscan` |

`SSH_KNOWN_HOSTS` можно получить так:

```sh
ssh-keyscan -p 22 -H SERVER_IP
```

Если его не задать, workflow сам выполнит `ssh-keyscan`.

**Ветка `production`.** Workflow срабатывает на push в неё; push в `main` деплой не запускает:

```sh
git checkout main
git pull
git checkout -b production
git push -u origin production
```

Дальнейшие деплои:

```sh
git checkout production
git merge main
git push origin production
```

Опционально: **Settings → Branches** — защитить `production` от force-push и прямых пушей.

Ручной запуск: **Actions → Deploy production → Run workflow**.

### 3. Что делает workflow

1. Делает checkout репозитория на GitHub-hosted runner.
2. Настраивает SSH-ключ и `known_hosts`.
3. Проверяет, что приватный ключ читается.
4. Создаёт `DEPLOY_PATH` на сервере и синхронизирует туда проект через `rsync -az --delete`.
   Исключаются `.git`, `.github`, `.env`, `www/vendor`, `www/var`; серверный `.env` сохраняется.
5. По SSH выполняет в каталоге деплоя:
   `docker compose -f docker-compose.yml -f docker-compose.embedded.yml -f docker-compose.prod.yml build && up -d --remove-orphans`, затем `docker image prune -f`.

Когда ClickHouse и Redis переедут на отдельные серверы, уберите `-f docker-compose.embedded.yml` из шага «Build and restart» и задайте `CLICKHOUSE_HOST` / `REDIS_HOST` в серверном `.env`.

### 4. Если деплой не прошёл

- `rsync: command not found` — установите `rsync` на сервере.
- `Permission denied (publickey)` — `SSH_PRIVATE_KEY` не соответствует ключу в `/home/app/.ssh/authorized_keys`, либо неверные `SSH_USER` / `SSH_PORT`.
- `mkdir: cannot create directory` — у `app` нет прав на родительский каталог; выберите другой `DEPLOY_PATH`.
- `permission denied while trying to connect to the Docker daemon` — пользователь `app` не в группе `docker`.
- `DB_HOST must point to the external MySQL server` / `APP_KEY must be set` — нет серверного `.env` или не заполнены обязательные переменные.
- `app did not start` / контейнер падает — смотрите `docker compose ... logs app`; чаще всего внешний MySQL, порт 7060 или права на `www/var`.
- Сборка падает по памяти — добавьте swap или увеличьте RAM.

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
