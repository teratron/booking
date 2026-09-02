# Первый запуск — проверка, вход, повседневные задачи

Сделайте это сразу после любой из четырёх инструкций по установке. Здесь
подтверждается, что сайт действительно работает, выполняется первый вход, а
затем перечислены несколько команд, которыми вы будете пользоваться изо дня
в день.

Везде **`BASE`** означает адрес, на котором отвечает ваш сайт:

- Локально с Docker: `http://localhost:8300`
- Локально без Docker: то, что вы задали в `APP_URL` (часто
  `http://127.0.0.1:8000`)
- Продакшен: `https://ваш-домен`

## Часть 1 — Подтвердите, что всё заработало

Проверяйте по порядку. Каждый пройденный пункт исключает целый класс
проблем.

### 1. Приложение живо

Откройте **`BASE/up`** в браузере или выполните `curl -i BASE/up`.

**Пройдено:** простая страница со словом `OK` или статус HTTP `200`.
**Не пройдено:** ошибка `500`, таймаут или «connection refused» → приложение
не запущено или не может достучаться до своей базы/Redis. См.
[`troubleshooting.md`](troubleshooting.md).

### 2. Публичный сайт отрисовывается

Откройте **`BASE`**. Он должен перенаправить на **`BASE/en`**.

**Пройдено:** главная страница туристического портала, **со стилями** (цвета,
вёрстка, шрифты), с названиями территорий и карточками объектов.
**Не пройдено — только текст без стилей:** сборка фронтенда не произошла →
выполните `pnpm build` (и `filament:assets`).
**Не пройдено — перенаправляет туда, что не открывается:** неверный
`APP_URL` → см. [`troubleshooting.md`](troubleshooting.md) → «Неверный адрес
/ битые ссылки».

### 3. Обе панели открываются

- **`BASE/portal-admin`** (или ваш кастомный путь админки) → страница входа.
- **`BASE/cabinet`** → страница входа.

**Пройдено:** обе показывают нормальную, **оформленную** форму входа.
**Не пройдено — форма без стилей:** выполните `php artisan filament:assets`.

### 4. (Только локально) вспомогательные инструменты

- Ловушка почты: `http://localhost:8325`
- Консоль файлового хранилища: `http://localhost:9101` (вход по
  `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` из `.env`)
- Дашборд очереди: `BASE/horizon` (после входа администратором)

## Часть 2 — Первый вход

### На локальной установке

Стартовый администратор был создан командой `migrate --seed`:

- **E-mail:** `test@example.com`
- **Пароль:** `password`

Зайдите на `BASE/portal-admin`, войдите и следуйте подсказке по настройке
второго фактора входа (приложение-аутентификатор: Google Authenticator,
1Password, Aegis и т. п.) — верхняя роль администратора его требует.
Сохраните коды восстановления, которые вам покажут.

Эта учётная запись **только для локального использования**. Её никогда не
должно быть на настоящем сервере.

### На настоящем сервере

Своего администратора вы создали во время инструкции по установке продакшена
(фрагмент `tinker`) и удалили `test@example.com`. Войдите на
`BASE/<ваш путь админки>` этой учётной записью и настройте её второй фактор.

Если позже нужно выдать права администратора существующему пользователю:

```
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'person@example.com')->firstOrFail();
app(App\Services\Authorization\RoleGrantService::class)
    ->grantRole($user, 'chief_administrator', $user);
```

(На Docker-хосте продакшена добавьте перед этим
`docker compose -p booking-production -f docker-compose.production.yml exec app`.)

## Часть 3 — Повседневные команды

Две колонки: с Docker выполняйте показанную команду, **добавляя перед ней**
`docker compose exec app` (локально) или
`docker compose -p booking-production -f docker-compose.production.yml exec app`
(продакшен). Без Docker выполняйте её прямо в папке проекта.

| Задача | Команда |
| --- | --- |
| Запустить сайт (Docker, уже настроен) | `docker compose up -d` |
| Остановить сайт (Docker) | `docker compose stop` |
| Смотреть живой лог приложения | `docker compose logs -f app` — или без Docker `php artisan pail`, либо читать `storage/logs/laravel.log` |
| Очистить все кеши (после странного поведения) | `php artisan optimize:clear` (за один раз чистит config, routes, views, events и кеш приложения) |
| Применить новые изменения базы после подтягивания кода | `php artisan migrate` |
| Пересобрать фронтенд после подтягивания кода | `pnpm install && pnpm build` |
| Переопубликовать стили панелей | `php artisan filament:assets` |
| Смотреть фоновые задачи и сбои | открыть `BASE/horizon` |
| Повторить все упавшие задачи | `php artisan queue:retry all` |
| Смотреть производительность сайта | открыть `BASE/pulse` |
| Запустить задачу по расписанию прямо сейчас | см. [`../../operations/ru/run-scheduled-job.md`](../../operations/ru/run-scheduled-job.md) |
| Проверить качество кода перед коммитом | `composer quality` (PHP) и `pnpm run quality` (стили/скрипты) |

### Обновление локальной копии до последнего кода

```
git pull
composer install
php artisan migrate
php artisan filament:assets
pnpm install && pnpm build
php artisan optimize:clear
```

С Docker добавляйте перед каждой командой `docker compose exec app`, а если
менялся сам Docker-рецепт — сначала выполните `docker compose up -d --build`.

### Обновление настоящего сервера

Никогда вручную на Docker-настройке продакшена — используйте
[`../../operations/ru/deploy.md`](../../operations/ru/deploy.md). На не-Docker-
сервере следуйте разделу «Обновление до новой версии позже» из
[`production-without-docker.md`](production-without-docker.md).

## Часть 4 — Где лежат более глубокие руководства

| Мне нужно… | Руководство |
| --- | --- |
| Выпустить новую версию в продакшен | [`../../operations/ru/deploy.md`](../../operations/ru/deploy.md) |
| Отменить неудачный релиз | [`../../operations/ru/rollback.md`](../../operations/ru/rollback.md) |
| Восстановить базу из бэкапа | [`../../operations/ru/restore.md`](../../operations/ru/restore.md) |
| Сменить пароль, ключ или токен | [`../../operations/ru/rotate-credentials.md`](../../operations/ru/rotate-credentials.md) |
| Разобраться в упавшем автоматическом конвейере | [`../../operations/ru/read-a-failed-pipeline.md`](../../operations/ru/read-a-failed-pipeline.md) |
| Узнать, как работают бэкапы | [`../../backups.md`](../../backups.md) |
| Изучить структуру базы данных | [`../../database-schema.md`](../../database-schema.md) |
| Настроить хранилище, CDN, почту, отслеживание ошибок | [`../../production-provisioning.md`](../../production-provisioning.md), [`../../mail-and-error-tracking.md`](../../mail-and-error-tracking.md) |
| Наблюдать за очередями и воркерами | [`../../queues-and-observability.md`](../../queues-and-observability.md) |
