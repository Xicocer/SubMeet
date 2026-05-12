# Observability

В проекте используется легкий observability-стек:

- `Loki` для хранения логов
- `Promtail` для чтения лог-файлов сервисов
- `Grafana` для просмотра и запросов к логам

Сами сервисы уже пишут структурированные JSON-логи, поэтому этот контур работает и локально, и в Docker.

## Запуск через Docker

Из корня проекта:

```powershell
npm run observability:up
```

Остановка:

```powershell
npm run observability:down
```

## Локальный запуск без Docker

Из корня проекта:

```powershell
npm run observability:local:up
```

Проверить, что именно будет использовано, без реального запуска:

```powershell
npm run observability:local:check
```

Остановка локального стека:

```powershell
npm run observability:local:down
```

Что делает локальный launcher:

- автоматически скачивает Windows-версии `Grafana`, `Loki` и `Promtail`
- складывает их во временную папку `.run/observability-local`
- генерирует локальные конфиги
- поднимает процессы в фоне

Если не хочешь автоскачивание, можно использовать сам PowerShell-скрипт с `-NoDownload`, но тогда бинарники уже должны лежать в `.run/observability-local/tools`.

## Адреса и доступ

Grafana:

- `http://127.0.0.1:3000`
- логин: `admin`
- пароль: `admin`

Loki:

- `http://127.0.0.1:3100`

Promtail:

- `http://127.0.0.1:9080`

Datasource `Loki` провижинится автоматически и в Docker, и в локальном режиме.

## Откуда берутся логи

Laravel-сервисы пишут JSON-логи в:

- `services/<service>/storage/logs/structured*.log`

`recommendation-service` пишет JSON-логи в:

- `services/recommendation-service/logs/structured*.log`

## Полезные LogQL-запросы

Все HTTP-запросы по проекту:

```logql
{stack="submeet"} | json | message="http_request"
```

Только `booking-service`:

```logql
{service="booking-service"} | json
```

Медленные запросы дольше 500ms:

```logql
{stack="submeet"} | json | duration_ms > 500
```

Проблемные платежные кейсы в логах booking/admin:

```logql
{service=~"booking-service|admin-service"} | json | status_code >= 400
```

Очередь модерации событий:

```logql
{service="event-service"} | json | path="/api/admin/events"
```

## Практический смысл для диплома

`Loki + Grafana` используется как основной рабочий стек просмотра логов.
При этом сами логи уже структурированы в JSON, поэтому при желании их потом можно
отправить и в более тяжелый `ELK`-контур без переделки самих сервисов.

admin@submeet.local / Password123!
anna@submeet.local / Password123!
nikita@submeet.local / Password123!
dkh@submeet.local / Password123!
milo@submeet.local / Password123!
citylight@submeet.local / Password123!
oldarena@submeet.local / Password123!
