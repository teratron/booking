<?php

declare(strict_types=1);

return [

    'brand' => 'Администрирование портала',
    'cabinet_brand' => 'Кабинет владельца',

    'bulk' => [
        'confirmation' => 'Действие затронет выбранных записей: :count. Изменение применится к каждой из них.',
        'confirm_label' => 'Применить к записям: :count',
    ],

    'impersonation' => [
        'banner_text' => 'Режим поддержки — вы просматриваете кабинет от имени :owner.',
        'return_to_admin' => 'Вернуться в админ-панель',
    ],

    'navigation' => [
        'catalog' => 'Каталог',
        'commerce' => 'Коммерция',
        'advertising' => 'Реклама',
        'analytics' => 'Аналитика',
        'communication' => 'Коммуникации',
        'content' => 'Контент',
        'geography' => 'География',
        'governance' => 'Управление',
        'access' => 'Доступ',
        'system' => 'Система',
    ],

    'dashboard' => [
        'objects_total' => 'Объекты',
        'objects_breakdown' => 'опубликовано: :published · скрыто: :hidden · в архиве: :archived',
        'pending_moderation' => 'Ожидают проверки',
        'reporting_vacancies' => 'Сообщают о наличии мест',
        'owners' => 'Владельцы',
        'geography_breakdown' => 'стран: :countries · территорий: :territories',
        'recorded_amount' => 'Учтённые платежи',
        'recorded_amount_window' => 'За последние 30 дней',
        'active_placements' => 'Активные размещения',
        'expiring_placements' => 'Истекают в течение 14 дней',
    ],

    'objects' => [
        'title' => 'Объекты',
        'model_label' => 'объект',

        'columns' => [
            'name' => 'Название',
            'type' => 'Тип',
            'country' => 'Страна',
            'territory' => 'Территория',
            'owner' => 'Владелец',
            'status' => 'Публикация',
            'moderation_status' => 'Модерация',
            'availability' => 'Наличие мест',
            'availability_confirmed' => 'Наличие подтверждено',
            'identifier' => 'Идентификатор',
        ],

        'status' => [
            'draft' => 'Черновик',
            'published' => 'Опубликован',
            'hidden' => 'Скрыт',
            'archived' => 'В архиве',
        ],

        'moderation' => [
            'pending' => 'Ожидает проверки',
            'approved' => 'Одобрено',
            'rejected' => 'Отклонено',
            'revision_requested' => 'Возвращено на доработку',
        ],

        'availability' => [
            'available' => 'Есть свободные места',
            'unavailable' => 'Мест нет',
            'unspecified' => 'Не указано',
            'current_status' => 'Текущий статус',
            'changed_at' => 'Изменено',
            'changed_by' => 'Кем изменено',
            'last_confirmed_at' => 'Последнее подтверждение',
            'override' => 'Изменить наличие мест',
            'new_status' => 'Новый статус',
            'comment' => 'Комментарий',
            'revert' => 'Вернуть к предыдущему',
            'revert_refused' => 'Нет предыдущего значения для отката.',
            'history_title' => 'История изменения наличия мест',
            'from_status' => 'Было',
            'to_status' => 'Стало',
            'source' => 'Источник',
            'sources' => [
                'owner' => 'Владелец',
                'administrator' => 'Администратор',
                'automatic' => 'Автоматически',
            ],
        ],

        'filters' => [
            'contact' => 'Телефон, email или мессенджер',
            'quick_available' => 'Есть свободные места',
            'quick_unspecified' => 'Статус не указан',
            'quick_stale' => 'Статус давно не обновлялся',
            'quick_active' => 'Активные объекты',
            'quick_expired_package' => 'Истёкший пакет',
        ],

        'bulk' => [
            'publish' => 'Опубликовать',
            'hide' => 'Скрыть',
            'draft' => 'Перевести в черновик',
            'archive' => 'В архив',
            'assign_promotion_label' => 'Назначить промо-метку',
            'move_territory' => 'Перенести на другую территорию',
            'assign_manager' => 'Назначить менеджера',
            'notify_owners' => 'Уведомить владельцев',
            'export' => 'Экспортировать выбранное',
            'reset_stale_availability' => 'Сбросить устаревшее наличие мест',
            'promotion_label' => 'Промо-метка',
            'starts_at' => 'Начало действия',
            'ends_at' => 'Окончание действия',
            'territory' => 'Целевая территория',
            'manager' => 'Менеджер',
            'notification_title' => 'Заголовок сообщения',
            'notification_body' => 'Текст сообщения',
            'queued' => 'Поставлено в очередь на обработку. Вы получите уведомление по завершении.',
            'completed' => 'Готово.',
            'scope_denied' => 'В действии отказано',
        ],

        'form' => [
            'tabs' => [
                'core' => 'Основная информация',
                'geography' => 'География',
                'type_attributes' => 'Поля по типу',
                'seo' => 'SEO',
                'contacts' => 'Контакты',
                'services' => 'Услуги',
                'owner_staff' => 'Владелец и сотрудники',
                'availability' => 'Наличие мест',
            ],
            'name' => 'Название',
            'short_description' => 'Краткое описание',
            'full_description' => 'Полное описание',
            'address' => 'Адрес',
            'seo_slug' => 'URL-слаг',
            'seo_title' => 'SEO-заголовок',
            'seo_description' => 'SEO-описание',
            'contact_value' => 'Значение',
            'contact_label' => 'Подпись',
            'out_of_scope' => 'Эта страна или категория вне вашей зоны ответственности.',
        ],

        'lifecycle' => [
            'save_as_draft' => 'Сохранить как черновик',
            'publish' => 'Опубликовать',
            'hide' => 'Скрыть',
            'return_for_revision' => 'Вернуть на доработку',
            'archive' => 'В архив',
            'restore' => 'Восстановить',
            'permanently_delete' => 'Удалить навсегда',
            'permanently_delete_refused' => 'Не удалось удалить объект навсегда',
            'reauthenticate_password' => 'Подтвердите пароль для продолжения',
            'duplicate' => 'Дублировать',
            'transfer_ownership' => 'Передать владельцу',
            'applied' => 'Готово.',
            'section' => 'Раздел, требующий доработки',
            'reason' => 'Причина',
            'new_owner' => 'Новый владелец',
            'bump' => 'Поднять',
            'bump_comment' => 'Комментарий',
            'bump_refused' => 'Не удалось поднять объект',
        ],
    ],

    'owners' => [
        'title' => 'Владельцы',
        'model_label' => 'владелец',

        'columns' => [
            'name' => 'Имя',
            'company' => 'Компания',
            'phone' => 'Телефон',
            'email' => 'Email',
            'country' => 'Страна',
            'objects_count' => 'Объекты',
            'overdue_placements' => 'Просроченные размещения',
            'registered_at' => 'Регистрация',
            'last_sign_in_at' => 'Последний вход',
            'status' => 'Статус',
        ],

        'status' => [
            'active' => 'Активен',
            'blocked' => 'Заблокирован',
        ],

        'filters' => [
            'status_all' => 'Все',
        ],

        'form' => [
            'name' => 'Имя',
            'email' => 'Email',
            'company' => 'Компания',
            'phone' => 'Телефон',
            'country' => 'Страна',
            'out_of_scope' => 'Эта страна вне вашей зоны ответственности.',
        ],

        'actions' => [
            'impersonate' => 'Войти в режим поддержки',
            'impersonate_confirm' => 'Вы войдёте в кабинет от имени этого владельца. Все ваши действия там будут журналироваться от вашего имени, а не от имени владельца.',
            'impersonation_refused' => 'В режиме поддержки отказано.',
            'block' => 'Заблокировать',
            'block_confirm' => 'Аккаунту будет отказано в доступе к любой панели до восстановления.',
            'restore' => 'Восстановить',
            'restore_confirm' => 'Блокировка снимается, доступ к панели восстанавливается.',
            'send_password_reset_link' => 'Отправить ссылку для сброса пароля',
            'password_reset_link_sent' => 'Ссылка для сброса пароля отправлена.',
            'applied' => 'Готово.',
        ],

        'objects' => [
            'title' => 'Прикреплённые объекты',
            'attach' => 'Прикрепить объект',
            'object' => 'Объект',
            'detach' => 'Открепить',
            'detach_confirm' => 'У объекта будет очищен владелец. Он останется без владельца до переназначения.',
            'detachment_refused' => 'В открепении отказано',
            'attachment_refused' => 'В прикреплении отказано — объект вне вашей зоны ответственности.',
            'applied' => 'Готово.',
        ],
    ],

    'territories' => [
        'title' => 'Территории',
        'model_label' => 'территория',

        'columns' => [
            'name' => 'Название',
            'level' => 'Уровень',
            'country' => 'Страна',
            'parent' => 'Родитель',
            'active' => 'Активна',
        ],

        'status' => [
            'active' => 'Активна',
            'inactive' => 'Неактивна',
        ],

        'form' => [
            'display_order' => 'Порядок отображения',
            'name' => 'Название',
            'slug' => 'URL-слаг',
            'short_description' => 'Краткое описание',
            'seo_title' => 'SEO-заголовок',
        ],

        'actions' => [
            'reparent' => 'Перенести к другому родителю',
            'new_parent' => 'Новая родительская территория',
            'no_parent' => 'Нет — сделать корнем страны',
            'reparent_confirm' => 'Перенос затронет дочерних территорий: :descendants, объектов: :objects.',
            'cycle_refused' => 'Перенос отклонён',
        ],
    ],

    'object_types' => [
        'title' => 'Типы объектов',
        'model_label' => 'тип объекта',

        'columns' => [
            'active' => 'Активен',
        ],

        'form' => [
            'key' => 'Ключ',
            'name' => 'Название',
            'parent' => 'Родительский тип',
            'no_parent' => 'Нет — тип верхнего уровня',
            'icon' => 'Путь к иконке',
            'has_rooms' => 'Имеет номера',
            'has_availability_status' => 'Имеет статус наличия мест',
            'display_order' => 'Порядок отображения',
            'amenity_groups' => 'Применимые группы удобств',
            'attribute_schema' => 'Дополнительные поля',
            'attribute_schema_hint' => 'Дополнительные поля, которые объект этого типа показывает в своей форме и на публичной странице — например, кухня и часы работы для типа «кафе».',
            'attribute_key' => 'Ключ поля',
            'attribute_type' => 'Тип поля',
            'attribute_types' => [
                'text' => 'Текст',
                'number' => 'Число',
                'boolean' => 'Да / Нет',
            ],
            'attribute_label' => 'Подпись (:language)',
            'seo_title' => 'SEO-заголовок',
            'seo_description' => 'SEO-описание',
        ],
    ],

    'placement_tiers' => [
        'title' => 'Уровни размещения',
        'model_label' => 'уровень размещения',

        'columns' => [
            'rank' => 'Ранг',
            'active' => 'Активен',
        ],

        'form' => [
            'rank' => 'Ранг',
            'label' => 'Название',
            'badge_text' => 'Текст значка',
            'border_colour' => 'Цвет рамки',
            'badge_colour' => 'Цвет значка',
            'badge_icon' => 'Иконка значка',
        ],
    ],

    'placement_packages' => [
        'title' => 'Пакеты размещения',
        'model_label' => 'пакет размещения',

        'columns' => [
            'active' => 'Активен',
        ],

        'form' => [
            'name' => 'Название',
            'tier' => 'Уровень размещения',
            'object_type' => 'Категория объектов',
            'any_object_type' => 'Любая категория',
            'price' => 'Цена',
            'currency' => 'Валюта',
            'validity_days' => 'Срок действия (дней)',
            'bump_allowed' => 'Поднятие разрешено',
            'bump_interval_hours' => 'Минимальный интервал между бесплатными поднятиями (часов)',
            'free_bumps_per_period' => 'Бесплатных поднятий за период',
            'paid_bump_price' => 'Цена платного поднятия',
            'display_order' => 'Порядок отображения',
        ],
    ],

    'banner_slots' => [
        'title' => 'Рекламные слоты',
        'model_label' => 'рекламный слот',

        'columns' => [
            'active' => 'Активен',
        ],

        'form' => [
            'key' => 'Ключ',
            'name' => 'Название',
            'surfaces' => 'Поверхности показа',
            'surface_options' => [
                'home' => 'Главная',
                'country' => 'Страна',
                'region' => 'Регион',
                'city' => 'Город',
                'resort' => 'Курорт',
                'category' => 'Категория',
                'object' => 'Объект',
                'news' => 'Новости',
                'article' => 'Статья',
            ],
        ],
    ],

    'banners' => [
        'title' => 'Баннеры',
        'model_label' => 'баннер',

        'columns' => [
            'active' => 'Активен',
            'impressions' => 'Показы',
            'clicks' => 'Клики',
            'click_through_rate' => 'CTR',
        ],

        'form' => [
            'slot' => 'Слот',
            'name' => 'Название',
            'advertiser' => 'Рекламодатель',
            'destination_link' => 'Ссылка перехода',
            'starts_at' => 'Начало показа',
            'ends_at' => 'Окончание показа',
            'display_order' => 'Порядок отображения',
            'desktop_creative' => 'Креатив (десктоп)',
            'mobile_creative' => 'Креатив (моб.)',
            'territories' => 'Территории',
            'categories' => 'Категории',
            'target_languages' => 'Языки',
            'link_text' => 'Текст ссылки',
        ],
    ],

    'promotion_labels' => [
        'title' => 'Промо-метки',
        'model_label' => 'промо-метка',

        'columns' => [
            'active' => 'Активна',
        ],

        'form' => [
            'text' => 'Текст',
            'border_colour' => 'Цвет рамки',
            'text_colour' => 'Цвет текста',
            'background_colour' => 'Цвет фона',
            'icon' => 'Иконка',
            'position_on_card' => 'Позиция на карточке',
            'preview' => 'Предпросмотр карточки',
            'preview_placeholder' => 'Выберите позицию, чтобы увидеть предпросмотр.',
            'preview_sample_text' => 'Метка',
        ],

        'positions' => [
            'top-left' => 'Верхний левый угол',
            'top-right' => 'Верхний правый угол',
            'bottom-left' => 'Нижний левый угол',
            'bottom-right' => 'Нижний правый угол',
        ],
    ],

    'financial_records' => [
        'title' => 'Финансовый журнал',
        'model_label' => 'запись журнала',

        'form' => [
            'subject_kind' => 'Относится к',
            'subject_object' => 'Объект',
            'subject_banner' => 'Баннер',
            'object' => 'Объект',
            'banner' => 'Баннер',
            'service' => 'Услуга',
            'package' => 'Пакет',
            'amount' => 'Сумма',
            'currency' => 'Валюта',
            'status' => 'Статус',
            'paid_at' => 'Дата оплаты',
            'valid_from' => 'Действует с',
            'valid_until' => 'Действует по',
            'payment_method' => 'Способ оплаты',
            'document_number' => 'Номер документа',
            'responsible_staff' => 'Ответственный сотрудник',
            'comment' => 'Комментарий',
        ],

        'status' => [
            'awaiting_payment' => 'Ожидает оплаты',
            'paid' => 'Оплачено',
            'partially_paid' => 'Частично оплачено',
            'overdue' => 'Просрочено',
            'cancelled' => 'Отменено',
            'granted_free' => 'Предоставлено бесплатно',
        ],

        'filters' => [
            'from' => 'С',
            'until' => 'По',
        ],

        'actions' => [
            'export' => 'Экспорт',
        ],

        'notifications' => [
            'export_completed' => 'Экспорт журнала завершён, экспортировано записей: :count.',
        ],
    ],

    'broadcast' => [
        'title' => 'Рассылка владельцам',

        'fields' => [
            'target_type' => 'Цель по',
            'target' => 'Цель',
            'title' => 'Заголовок сообщения',
            'body' => 'Текст сообщения',
        ],

        'target_types' => [
            'country' => 'Страна',
            'resort' => 'Курорт',
            'package' => 'Пакет размещения',
        ],

        'actions' => [
            'send' => 'Отправить рассылку',
        ],

        'confirmation' => 'Будет уведомлено владельцев: :count. Каждый из них получит это сообщение.',
        'confirm_label' => 'Уведомить владельцев: :count',
        'sent' => 'Рассылка отправлена владельцам: :count.',
        'rate_limited' => 'Достигнут дневной лимит рассылок',
        'quota_remaining' => 'Осталось рассылок сегодня: :count.',
    ],

    'commerce_reports' => [
        'title' => 'Коммерческие отчёты',
        'active_placements' => 'Активные размещения',
        'ending_30' => 'Истекают в течение 30 дней',
        'ending_14' => 'Истекают в течение 14 дней',
        'ending_7' => 'Истекают в течение 7 дней',
        'ending_3' => 'Истекают в течение 3 дней',
        'expired_placements' => 'Истёкшие размещения',
        'no_term_set' => 'Объекты без установленного срока',
        'free_placements' => 'Бесплатные размещения',
        'paid_bump_count' => 'Платные поднятия',
        'active_campaigns' => 'Активные рекламные кампании',
    ],

    'analytics_report' => [
        'title' => 'Отчёт по аналитике',

        'filters' => [
            'territory' => 'Город',
            'category' => 'Категория',
            'language' => 'Язык',
            'banner' => 'Баннер',
            'object' => 'ID объекта',
        ],

        'columns' => [
            'date' => 'Дата',
            'kind' => 'Тип',
            'subject_type' => 'Субъект',
            'subject_id' => 'ID субъекта',
            'territory' => 'Город',
            'language' => 'Язык',
            'count' => 'Количество',
        ],

        'kinds' => [
            'object_card_view' => 'Просмотры карточки',
            'object_page_view' => 'Просмотры страницы',
            'photo_view' => 'Просмотры фото',
            'contact_click' => 'Клики по контактам',
            'banner_impression' => 'Показы баннеров',
            'banner_click' => 'Клики по баннерам',
        ],

        'actions' => [
            'export' => 'Экспорт',
        ],

        'notifications' => [
            'export_completed' => '{1} экспортирована :count строка.|[2,4] экспортировано :count строки.|[5,*] экспортировано :count строк.',
        ],
    ],

    'modules' => [
        'title' => 'Модули',
        'model_label' => 'модуль',
        'applied' => 'Состояние модуля обновлено.',
        'refused' => 'Изменение отклонено',
        'confirm' => 'Изменение модуля «:module» на уровне «:scope» затронет объектов: :count.',

        'scope' => [
            'portal' => 'портал',
            'country' => 'страна',
        ],

        'state' => [
            'enabled' => 'Включён',
            'disabled' => 'Выключен',
        ],

        'columns' => [
            'key' => 'Ключ',
            'name' => 'Модуль',
            'default_state' => 'По умолчанию',
            'effective' => 'Действует (портал)',
            'dependencies' => 'Требует',
            'registered' => 'Зарегистрирован',
        ],

        'actions' => [
            'enable_portal' => 'Включить для всего портала',
            'disable_portal' => 'Выключить для всего портала',
            'set_for_country' => 'Задать для страны',
        ],

        'fields' => [
            'country' => 'Страна',
            'state' => 'Состояние',
        ],
    ],

    'settings' => [
        'title' => 'Настройки портала',
        'save' => 'Сохранить настройки',
        'saved' => 'Настройки сохранены.',
        'critical_refused' => 'Настройка с ограниченным доступом',

        'groups' => [
            'portal' => 'Портал',
            'presentation' => 'Представление',
            'media' => 'Медиа',
            'moderation' => 'Модерация',
            'availability' => 'Наличие мест',
            'placement' => 'Размещение',
            'notifications' => 'Уведомления',
            'integrations' => 'Интеграции',
            'security' => 'Безопасность',
            'journal' => 'Журнал действий',
            'analytics' => 'Аналитика',
        ],

        'fields' => [
            'portal.name' => 'Название портала',
            'portal.logo_path' => 'Путь к логотипу',
            'portal.contact_email' => 'Контактный email',
            'portal.contact_phone' => 'Контактный телефон',

            'presentation.date_format' => 'Формат даты',
            'presentation.time_format' => 'Формат времени',
            'presentation.timezone' => 'Часовой пояс',
            'presentation.default_currency' => 'Валюта по умолчанию',
            'presentation.within_tier_order' => 'Порядок внутри уровня размещения',

            'media.image_max_width' => 'Максимальная ширина изображения (px)',
            'media.image_max_height' => 'Максимальная высота изображения (px)',
            'media.upload_max_kilobytes' => 'Максимальный размер загрузки (КБ)',
            'media.allowed_mime_types' => 'Допустимые типы изображений',

            'moderation.default_mode' => 'Режим модерации по умолчанию',
            'moderation.moderated_change_types' => 'Типы изменений, требующие проверки',
            'moderation.partial_acceptance_enabled' => 'Разрешить частичное принятие изменений',
            'moderation.stale_object_days' => 'Дней до признания объекта устаревшим',

            'availability.confirmation_period_days' => 'Дней до повторного подтверждения наличия мест',
            'availability.auto_reset_enabled' => 'Автоматически сбрасывать устаревший статус',

            'placement.expiry_grace_days' => 'Льготный период после окончания размещения (дней)',
            'placement.expired_behaviour' => 'Поведение при окончании размещения',

            'notifications.digest_hour' => 'Час отправки ежедневной сводки',
            'notifications.expiry_reminder_lead_days' => 'За сколько дней предупреждать об окончании',
            'notifications.dispatch_max_retries' => 'Максимум повторных попыток для неудачной отправки',
            'notifications.broadcast_rate_limit' => 'Максимум рассылок администратора в день',

            'integrations.map_tile_provider' => 'Поставщик картографических тайлов',
            'integrations.map_tile_key' => 'API-ключ картографических тайлов',
            'integrations.captcha_provider' => 'Поставщик CAPTCHA',
            'integrations.captcha_site_key' => 'Публичный ключ CAPTCHA',
            'integrations.captcha_secret' => 'Секретный ключ CAPTCHA',
            'integrations.analytics_measurement_id' => 'Идентификатор аналитики',

            'security.session_lifetime_minutes' => 'Время жизни сессии (минут)',
            'security.sign_in_max_attempts' => 'Попыток входа до блокировки',

            'journal.retention_days' => 'Хранение журнала (дней)',

            'analytics.raw_retention_days' => 'Хранение сырых событий (дней)',
        ],
    ],

    'languages' => [
        'title' => 'Языки',
        'model_label' => 'язык',

        'columns' => [
            'code' => 'Код',
            'short_label' => 'Метка',
            'text_direction' => 'Направление',
            'active' => 'Активен',
            'primary' => 'Основной',
            'display_order' => 'Порядок',
        ],

        'text_direction' => [
            'ltr' => 'Слева направо',
            'rtl' => 'Справа налево',
        ],

        'actions' => [
            'activate' => 'Активировать',
            'deactivate' => 'Деактивировать',
            'make_primary' => 'Сделать основным',
        ],

        'notifications' => [
            'activated' => 'Язык активирован.',
            'deactivated' => 'Язык деактивирован.',
            'deactivate_refused' => 'Не удалось деактивировать этот язык',
            'primary_changed' => 'Основной язык обновлён.',
            'make_primary_refused' => 'Не удалось изменить основной язык',
        ],
    ],

    'interface_catalog' => [
        'title' => 'Каталог интерфейса',
        'save' => 'Сохранить каталог',
        'saved' => 'Каталог интерфейса сохранён.',
    ],

    'translation_report' => [
        'title' => 'Отчёт о переводах',

        'columns' => [
            'entity' => 'Сущность',
            'state' => 'Состояние',
            'needs_review_count' => 'на проверке: :count',
        ],

        'state' => [
            'missing' => 'Отсутствует',
            'needs_review' => 'Требует проверки',
        ],

        'actions' => [
            'copy_from_primary' => 'Скопировать из основного языка',
            'publish' => 'Опубликовать',
        ],

        'notifications' => [
            'copied' => 'Скопировано из основного языка — отмечено как требующее проверки.',
            'published' => 'Перевод опубликован.',
            'publish_refused' => 'Для этого языка ещё нет перевода.',
        ],
    ],

    'moderation_queue' => [
        'title' => 'Очередь модерации',
        'model_label' => 'заявка на модерацию',

        'columns' => [
            'submitted_at' => 'Подано',
            'owner' => 'Владелец',
            'object' => 'Объект',
            'section' => 'Раздел',
            'change_summary' => 'Изменённые поля',
            'status' => 'Статус',
            'assigned_to' => 'Назначено',
            'country' => 'Страна',
        ],

        'filters' => [
            'submitted_between' => 'Подано в период',
            'from' => 'С',
            'until' => 'По',
        ],

        'actions' => [
            'review' => 'Рассмотреть',
            'reassign' => 'Переназначить',
            'reassign_to' => 'Переназначить на',
        ],

        'notifications' => [
            'reassigned' => 'Заявка переназначена.',
        ],
    ],

    'moderation_review' => [
        'title' => 'Рассмотрение заявки на изменение',

        'columns' => [
            'field' => 'Поле',
            'published' => 'Опубликовано',
            'proposed' => 'Предложено',
        ],

        'fields' => [
            'reason' => 'Причина',
            'comment' => 'Комментарий',
            'accepted_fields' => 'Поля для принятия',
        ],

        'actions' => [
            'approve' => 'Одобрить',
            'reject' => 'Отклонить',
            'request_revision' => 'Запросить доработку',
            'partially_accept' => 'Принять частично',
        ],

        'notifications' => [
            'approved' => 'Изменение одобрено и опубликовано.',
            'rejected' => 'Изменение отклонено.',
            'revision_requested' => 'У владельца запрошена доработка.',
            'partially_accepted' => 'Принятые поля применены; остальные возвращены на доработку.',
            'partial_refused' => 'Не удалось применить частичное принятие.',
        ],
    ],

    'action_journal' => [
        'title' => 'Журнал действий',
        'model_label' => 'запись журнала',

        'columns' => [
            'occurred_at' => 'Произошло',
            'actor' => 'Исполнитель',
            'action' => 'Действие',
            'target' => 'Объект',
            'target_id' => 'ID объекта',
            'previous_value' => 'Предыдущее значение',
            'new_value' => 'Новое значение',
            'ip_address' => 'IP-адрес',
            'device' => 'Устройство',
            'outcome' => 'Результат',
        ],

        'outcome' => [
            'success' => 'Успех',
            'failure' => 'Неудача',
        ],

        'filters' => [
            'occurred_between' => 'Произошло в период',
        ],

        'actions' => [
            'export' => 'Экспорт',
        ],

        'notifications' => [
            'export_completed' => '{0}Экспорт завершён, строк нет.|{1}Экспорт завершён, :count строка.|[2,4]Экспорт завершён, :count строки.|[5,*]Экспорт завершён, :count строк.',
        ],
    ],

    'article_categories' => [
        'title' => 'Категории статей',
        'model_label' => 'категория статьи',

        'columns' => [
            'active' => 'Активна',
        ],

        'form' => [
            'slug' => 'URL-слаг',
            'name' => 'Название',
            'display_order' => 'Порядок отображения',
        ],
    ],

    'article_tags' => [
        'title' => 'Теги статей',
        'model_label' => 'тег статьи',

        'columns' => [
            'active' => 'Активен',
        ],

        'form' => [
            'slug' => 'URL-слаг',
            'name' => 'Название',
            'display_order' => 'Порядок отображения',
        ],
    ],

    'articles' => [
        'title' => 'Статьи',
        'model_label' => 'статья',

        'form' => [
            'author' => 'Автор',
            'category' => 'Категория',
            'status' => 'Статус',
            'publish_at' => 'Дата публикации',
            'cover_image' => 'Обложка',
            'related_objects' => 'Связанные объекты',
            'related_territories' => 'Связанные территории',
            'tags' => 'Теги',
            'title' => 'Заголовок',
            'summary' => 'Краткое описание',
            'body' => 'Текст',
            'slug' => 'URL-слаг',
            'seo_title' => 'SEO-заголовок',
            'seo_description' => 'SEO-описание',
        ],

        'status' => [
            'draft' => 'Черновик',
            'scheduled' => 'Запланировано',
            'published' => 'Опубликовано',
        ],

        'lifecycle' => [
            'publish' => 'Опубликовать',
            'schedule' => 'Запланировать',
            'archive' => 'В архив',
            'restore' => 'Восстановить',
            'applied' => 'Изменение применено.',
            'schedule_refused' => 'Эту статью нельзя запланировать',
        ],
    ],

    'news_items' => [
        'title' => 'Новости',
        'model_label' => 'новость',

        'columns' => [
            'view_count' => 'Просмотры',
        ],

        'form' => [
            'author' => 'Автор',
            'object' => 'Объект',
            'any_object' => 'По всему порталу (без объекта)',
            'territory' => 'Территория',
            'category' => 'Категория',
            'status' => 'Статус',
            'publish_at' => 'Дата публикации',
            'end_at' => 'Дата окончания',
            'pinned' => 'Закреплено',
            'cover_image' => 'Обложка',
            'gallery' => 'Галерея',
            'title' => 'Заголовок',
            'summary' => 'Краткое описание',
            'body' => 'Текст',
            'slug' => 'URL-слаг',
            'seo_title' => 'SEO-заголовок',
            'seo_description' => 'SEO-описание',
        ],

        'status' => [
            'draft' => 'Черновик',
            'scheduled' => 'Запланировано',
            'published' => 'Опубликовано',
            'withdrawn' => 'Снято',
        ],

        'moderation_status' => [
            'pending' => 'На проверке',
            'approved' => 'Одобрено',
            'rejected' => 'Отклонено',
            'revision_requested' => 'Требует доработки',
        ],

        'lifecycle' => [
            'publish' => 'Опубликовать',
            'schedule' => 'Запланировать',
            'pin' => 'Закрепить',
            'unpin' => 'Открепить',
            'withdraw' => 'Снять',
            'archive' => 'В архив',
            'restore' => 'Восстановить',
            'applied' => 'Изменение применено.',
            'schedule_refused' => 'Эту новость нельзя запланировать',
        ],
    ],

    'promotions' => [
        'title' => 'Акции',
        'model_label' => 'акция',

        'form' => [
            'object' => 'Объект',
            'territory' => 'Территория',
            'status' => 'Статус',
            'starts_at' => 'Дата начала',
            'ends_at' => 'Дата окончания',
            'image' => 'Изображение',
            'title' => 'Заголовок',
            'summary' => 'Описание',
            'slug' => 'URL-слаг',
            'seo_title' => 'SEO-заголовок',
            'seo_description' => 'SEO-описание',
        ],

        'status' => [
            'draft' => 'Черновик',
            'scheduled' => 'Запланировано',
            'published' => 'Опубликовано',
            'archived' => 'В архиве',
        ],

        'moderation_status' => [
            'pending' => 'На проверке',
            'approved' => 'Одобрено',
            'rejected' => 'Отклонено',
            'revision_requested' => 'Требует доработки',
        ],

        'lifecycle' => [
            'publish' => 'Опубликовать',
            'schedule' => 'Запланировать',
            'archive' => 'В архив',
            'delete' => 'Удалить',
            'restore' => 'Восстановить',
            'applied' => 'Изменение применено.',
            'schedule_refused' => 'Эту акцию нельзя запланировать',
        ],
    ],

    'cabinet' => [
        'dashboard' => [
            'title' => 'Панель управления',
            'object_name' => 'Объект',
            'active_package' => 'Активный пакет',
            'no_active_package' => 'Нет активного пакета',
            'current_tier' => 'Текущий уровень',
            'expires_at' => 'Срок размещения истекает',
            'catalog_position' => 'Позиция в каталоге',
            'catalog_position_unavailable' => 'Пока недоступно',
            'views_today' => 'Просмотры сегодня',
            'views_this_week' => 'Просмотры за неделю',
            'views_this_month' => 'Просмотры за месяц',
            'views_all_time' => 'Просмотры за всё время',
            'messenger_clicks' => 'Переходы в мессенджер',
            'website_clicks' => 'Переходы на сайт',
            'expiry_warning_today' => 'Срок действия пакета размещения истекает сегодня.',
            'expiry_warning_days' => 'Срок действия пакета размещения истекает через :days дн.',
            'quick_actions_section' => 'Быстрые действия',
            'quick_actions' => [
                'edit_object' => 'Редактировать объект',
                'bump_object' => 'Поднять объект',
                'add_photos' => 'Добавить фото',
                'add_news' => 'Добавить новость',
                'add_promotion' => 'Добавить акцию',
                'not_yet_available' => 'Этот раздел пока недоступен.',
            ],
        ],

        'availability' => [
            'mark_available' => 'Отметить как доступен',
            'mark_unavailable' => 'Отметить как недоступен',
            'toggled' => 'Статус доступности обновлён.',
        ],

        'objects' => [
            'title' => 'Редактирование объекта',
            'model_label' => 'объект',

            'sections' => [
                'core' => 'Основное',
                'geography' => 'География',
                'contacts' => 'Контакты',
                'translations' => 'Переводы',
                'seo' => 'SEO',
            ],

            'form' => [
                'latitude' => 'Широта',
                'longitude' => 'Долгота',
            ],

            'lifecycle' => [
                'saved' => 'Сохранено.',
                'submitted_for_review' => 'Отправлено на проверку. Изменения появятся после подтверждения модератором.',
            ],

            'moderation_feedback' => [
                'title' => 'Отзыв модерации',
                'pending' => 'Ваше последнее изменение ожидает проверки.',
                'rejected_with_reason' => 'Ваше последнее изменение отклонено. Причина: :reason',
                'revision_requested_with_reason' => 'Модератор запросил доработку последней заявки. Причина: :reason',
            ],

            'eligibility' => [
                'title' => 'Перед публикацией объекта необходимо',
                'coordinates' => 'Указать координаты (широту и долготу).',
                'name' => 'Указать название на основном языке.',
            ],
        ],

        'photos' => [
            'title' => 'Фотографии',
            'model_label' => 'фотография',
            'empty' => 'Фотографии ещё не загружены.',

            'columns' => [
                'photo' => 'Фото',
                'caption' => 'Подпись',
                'primary' => 'Главное',
            ],

            'actions' => [
                'upload' => 'Загрузить',
                'set_primary' => 'Сделать главным',
                'edit_caption' => 'Изменить подпись',
                'delete' => 'Удалить',
            ],

            'upload_form' => [
                'files' => 'Фотографии',
            ],

            'caption_form' => [
                'caption' => 'Подпись',
            ],

            'notifications' => [
                'uploaded' => 'Фотографии загружены.',
                'limit_reached' => 'Достигнут лимит фотографий',
                'limit_reached_body' => 'У этого объекта уже максимум :max фото. Удалите одно, прежде чем загружать новые.',
                'primary_set' => 'Главное фото обновлено.',
                'caption_saved' => 'Подпись сохранена.',
                'deleted' => 'Фото удалено.',
            ],
        ],

        'rooms' => [
            'title' => 'Номера',
            'model_label' => 'номер',

            'sections' => [
                'core' => 'Параметры номера',
                'translations' => 'Переводы',
                'amenities' => 'Удобства',
                'prices' => 'Цены',
            ],

            'form' => [
                'capacity' => 'Вместимость',
                'room_count' => 'Количество номеров',
                'area_sqm' => 'Площадь (м²)',
                'bed_configuration' => 'Конфигурация кроватей',
                'max_guests' => 'Максимум гостей',
                'display_order' => 'Порядок отображения',
                'has_extra_bed' => 'Есть дополнительная кровать',
                'is_active' => 'Активен',
                'name' => 'Название',
                'description' => 'Описание',
            ],

            'columns' => [
                'name' => 'Название',
                'capacity' => 'Вместимость',
                'max_guests' => 'Макс. гостей',
                'area_sqm' => 'Площадь (м²)',
                'is_active' => 'Активен',
                'display_order' => 'Порядок',
            ],

            'price_form' => [
                'type' => 'Название тарифа',
                'calculation_unit' => 'Единица расчёта',
                'amount' => 'Сумма',
                'currency' => 'Валюта',
                'valid_from' => 'Действует с',
                'valid_until' => 'Действует по',
                'comment' => 'Комментарий',
                'add' => 'Добавить цену',
            ],

            'calculation_unit' => [
                'per_room' => 'За номер',
                'per_person' => 'За человека',
                'per_night' => 'За ночь',
                'per_service' => 'За услугу',
                'from' => 'От',
            ],
        ],

        'services' => [
            'title' => 'Услуги',
            'model_label' => 'услуга',
            'empty' => 'Для этого типа объекта пока нет доступных групп услуг.',

            'columns' => [
                'name' => 'Название',
                'selected_count' => 'Выбрано услуг',
            ],
        ],

        'news_items' => [
            'title' => 'Новости',
            'model_label' => 'новость',

            'form' => [
                'title' => 'Заголовок',
                'summary' => 'Краткое описание',
                'body' => 'Текст',
                'image' => 'Изображение',
                'publish_at' => 'Дата публикации',
            ],

            'columns' => [
                'title' => 'Заголовок',
                'status' => 'Статус',
                'publish_at' => 'Дата публикации',
            ],

            'status' => [
                'draft' => 'Черновик',
                'scheduled' => 'Запланировано',
                'published' => 'Опубликовано',
                'withdrawn' => 'Снято с публикации',
            ],

            'lifecycle' => [
                'published' => 'Опубликовано.',
                'submitted_for_review' => 'Отправлено на проверку. Новость появится после подтверждения модератором.',
            ],
        ],

        'promotions' => [
            'title' => 'Акции',
            'model_label' => 'акция',

            'form' => [
                'title' => 'Заголовок',
                'description' => 'Описание',
                'image' => 'Изображение',
                'starts_at' => 'Дата начала',
                'ends_at' => 'Дата окончания',
            ],

            'columns' => [
                'title' => 'Заголовок',
                'status' => 'Статус',
                'starts_at' => 'Дата начала',
                'ends_at' => 'Дата окончания',
            ],

            'status' => [
                'draft' => 'Черновик',
                'scheduled' => 'Запланировано',
                'published' => 'Опубликовано',
                'archived' => 'В архиве',
            ],

            'lifecycle' => [
                'published' => 'Опубликовано.',
                'submitted_for_review' => 'Отправлено на проверку. Акция появится после подтверждения модератором.',
            ],
        ],

        'reviews' => [
            'title' => 'Отзывы',
            'model_label' => 'отзыв',
            'empty' => 'Отзывов пока нет.',
            'author_fallback' => 'Гость',

            'columns' => [
                'rating' => 'Оценка',
                'author' => 'Автор',
                'body' => 'Отзыв',
                'status' => 'Статус',
                'owner_reply' => 'Ваш ответ',
                'reported' => 'Жалоба',
                'created_at' => 'Дата отправки',
            ],

            'status' => [
                'pending' => 'На проверке',
                'published' => 'Опубликован',
                'rejected' => 'Отклонён',
            ],

            'actions' => [
                'reply' => 'Ответить',
                'report' => 'Пожаловаться',
            ],

            'reply_form' => [
                'owner_reply' => 'Ваш ответ',
            ],

            'report_form' => [
                'report_reason' => 'Причина',
            ],

            'notifications' => [
                'replied' => 'Ответ опубликован.',
                'reported' => 'Жалоба на отзыв отправлена.',
            ],
        ],
    ],

];
