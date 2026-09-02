# Развёртывание на настоящем сервере, без Docker

> **Прочитайте это сначала.** Это **не** тот способ, которым проект
> спроектирован работать в продакшене. Выбрав этот путь, вы по определению
> отказываетесь от:
>
> - автоматического конвейера релиза (собрать один раз, развернуть по точному
>   отпечатку);
> - автоматической проверки работоспособности после релиза;
> - отката к предыдущей версии одной командой;
> - гарантии, что развёрнуто ровно то, что было протестировано.
>
> Каждый из этих пунктов становится ручной процедурой, которую вы аккуратно
> выполняете сами. Выбирайте этот путь, только если Docker на вашем сервере
> действительно нельзя запустить. Эту настройку и каждое будущее обновление
> должен вести разработчик.

Сначала прочитайте [`overview.md`](overview.md). Эта инструкция предполагает
уверенную работу с Linux-сервером, nginx и системными службами.

## Что вы собираете

Классический серверный стек, собранный вручную: nginx обслуживает сайт,
PHP-FPM выполняет приложение, PostgreSQL хранит данные, Redis — для кеша и
очередей, а супервизор процессов поддерживает три фоновых процесса живыми.

## Перед началом

- **Linux-сервер** с доменом, направленным на него, и TLS-сертификатом
  (Let's Encrypt / certbot подойдёт).
- Возможность устанавливать системные пакеты и управлять службами (`root`
  или `sudo`).

Установите следующих версий:

| Программа | Версия | Примечания |
| --- | --- | --- |
| **PHP** | 8.5 | С `php-fpm` и расширениями `intl`, `pdo_pgsql`, `pgsql`, `redis`, `imagick`, `gd`, `zip`, `bcmath`, `exif`, `pcntl`, `opcache`. |
| **Composer** | 2.x | |
| **PostgreSQL** | 18 | С **PostGIS**, а также `pg_trgm` и `unaccent`. |
| **postgresql-client** | 18 | Для `pg_dump`, используется задачей бэкапа. Должен совпадать по мажорной версии с сервером. |
| **Redis** | 8 | |
| **Node.js** | 24 | Нужен на сервере, только если собираете ассеты там; можно собрать их в другом месте и загрузить результат. |
| **pnpm** | 11.x | |
| **nginx** | любая актуальная | Публичный веб-сервер. |
| **Supervisor** | любая актуальная | Поддерживает работу фоновых процессов. |

## Шаги

### 1. Создайте базу данных

```
sudo -u postgres psql
```

```
CREATE ROLE booking_app WITH LOGIN PASSWORD 'выберите-надёжный-пароль';
CREATE DATABASE booking OWNER booking_app;
\c booking
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

### 2. Разместите код на сервере

```
sudo git clone <адрес репозитория> /var/www/booking
cd /var/www/booking
sudo git checkout <тег версии для развёртывания, напр. v1.0.0>
```

Развёртывание **конкретного тега**, а не движущейся ветки — это то, что
позже позволит вернуться назад.

### 3. Установите части приложения

```
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
php artisan filament:assets
```

`--no-dev` оставляет инструменты тестирования и разработки вне
продакшен-установки. Если собираете ассеты на другой машине, загрузите
получившуюся папку `public/build/` вместо запуска `pnpm` здесь.

### 4. Создайте продакшен-файл настроек

```
cp .env.production.example .env
chmod 600 .env
```

Отредактируйте `.env` и заполните каждый пробел. Та же таблица, что в
[`production-with-docker.md`](production-with-docker.md) шаг 3, со следующими
отличиями для не-Docker-хоста:

| Настройка | Значение |
| --- | --- |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379` |
| `DB_USERNAME` / `DB_PASSWORD` | `booking_app` / пароль из шага 1 |
| `AWS_ENDPOINT` и т. д. | Ваш настоящий S3-совместимый bucket медиа |
| `APP_KEY` | Здесь `php artisan key:generate` записывает его прямо в `.env` |

### 5. Инициализируйте приложение

```
php artisan key:generate          # если ещё не задан
php artisan migrate --force
php artisan db:seed --force        # стартовые справочные данные + тестовый админ
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
```

Затем создайте настоящего администратора и удалите созданного при наполнении —
тот же фрагмент `tinker`, что в
[`production-with-docker.md`](production-with-docker.md) шаг 8, запускаемый как
`php artisan tinker` напрямую.

### 6. Задайте владельца файлов

Веб-сервер работает под `www-data`. Приложение пишет только в две папки:

```
sudo chown -R www-data:www-data /var/www/booking/storage /var/www/booking/bootstrap/cache
```

### 7. Настройте nginx

Создайте server-блок. Адаптируйте его из собственного эталона проекта
[`../../../docker/nginx/default.conf`](../../../docker/nginx/default.conf) —
сохраните эти части:

- `root`, указывающий на `/var/www/booking/public`;
- запасной путь `try_files ... /index.php?$query_string`;
- блок `location ~ \.php$`, проксирующий на ваш сокет PHP-FPM (например,
  `unix:/run/php/php8.5-fpm.sock` вместо `app:9000`);
- ограничение частоты на клиента для `^/[a-z]{2}/catalog` (самая тяжёлая
  страница) и его ответ `429`;
- `location ~ /\. { deny all; }` — никогда не отдавать dotfiles; `.env`
  находится прямо над корнем сайта.

Добавьте TLS-сертификат (certbot может отредактировать блок за вас) и
перенаправление с порта 80 на 443. Перезагрузите nginx.

### 8. Настройте фоновые процессы

Создайте конфиг Supervisor, например
`/etc/supervisor/conf.d/booking.conf`:

```
[program:booking-horizon]
command=php /var/www/booking/artisan horizon
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600

[program:booking-scheduler]
command=php /var/www/booking/artisan schedule:work
user=www-data
autostart=true
autorestart=true

[program:booking-pulse]
command=php /var/www/booking/artisan pulse:work
user=www-data
autostart=true
autorestart=true
```

Затем:

```
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status      # все три должны быть RUNNING
```

(Вместо `schedule:work` подойдёт и одна строка crontab:
`* * * * * cd /var/www/booking && php artisan schedule:run >> /dev/null 2>&1`.)

### 9. Подтвердите

- `curl -i https://ваш-домен/up` возвращает `200`.
- `https://ваш-домен/en` открывается по HTTPS, оформлен.
- `https://ваш-домен/<ваш путь админки>` пускает по входу.
- `supervisorctl status` показывает все три фоновые программы `RUNNING`.

## Обновление до новой версии позже (ручной релиз)

Конвейера здесь нет; релиз вы выполняете вручную. По порядку:

```
cd /var/www/booking
php artisan down --secret="какая-то-случайная-строка"   # режим обслуживания; секрет для предпросмотра
sudo git fetch --tags
sudo git checkout <новый тег версии>
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile && pnpm build            # или загрузите готовую public/build/
php artisan filament:assets
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo systemctl reload php8.5-fpm
sudo supervisorctl restart booking-horizon booking-scheduler booking-pulse
php artisan up                                          # выйти из режима обслуживания
```

Затем проверьте, что `https://ваш-домен/up` возвращает `200` и сайт
открывается.

**Чтобы откатиться:** повторите последовательность с *предыдущим* тегом. Если
неудачный релиз выполнил миграцию, удалившую или изменившую данные, отката
кода недостаточно — нужно восстановить базу из бэкапа. См.
[`../../operations/ru/restore.md`](../../operations/ru/restore.md) и
[`../../backups.md`](../../backups.md).

## Если что-то не работает

- **`/up` не 200** → проверьте `storage/logs/laravel.log`, затем собственный
  лог PHP-FPM. 500 с пустым логом приложения почти всегда — владелец папок
  (шаг 6).
- **Сайт перенаправляет на адрес, который не отвечает, или ассеты 404** →
  `APP_URL` в `.env` не совпадает точно с `https://ваш-домен`. Исправьте,
  затем `php artisan config:cache`.
- **Панели без стилей** → `php artisan filament:assets` не выполнялся или
  папка `public/build/` отсутствует.
- **Фоновые задачи никогда не выполняются** → `supervisorctl status`;
  проверьте, что программы `RUNNING`, и прочитайте их логи.
- **Что-то ещё** → [`troubleshooting.md`](troubleshooting.md), затем
  разработчику.
