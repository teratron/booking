<?php

declare(strict_types=1);

return [

    'brand' => 'Администрирование портала',
    'cabinet_brand' => 'Кабинет владельца',

    'bulk' => [
        'confirmation' => 'Действие затронет выбранных записей: :count. Изменение применится к каждой из них.',
        'confirm_label' => 'Применить к записям: :count',
    ],

    'navigation' => [
        'catalog' => 'Каталог',
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
        ],

        'filters' => [
            'contact' => 'Телефон, email или мессенджер',
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
            'duplicate' => 'Дублировать',
            'transfer_ownership' => 'Передать владельцу',
            'applied' => 'Готово.',
            'section' => 'Раздел, требующий доработки',
            'reason' => 'Причина',
            'new_owner' => 'Новый владелец',
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

            'integrations.map_tile_provider' => 'Поставщик картографических тайлов',
            'integrations.map_tile_key' => 'API-ключ картографических тайлов',
            'integrations.captcha_provider' => 'Поставщик CAPTCHA',
            'integrations.captcha_site_key' => 'Публичный ключ CAPTCHA',
            'integrations.captcha_secret' => 'Секретный ключ CAPTCHA',
            'integrations.analytics_measurement_id' => 'Идентификатор аналитики',

            'security.session_lifetime_minutes' => 'Время жизни сессии (минут)',
            'security.sign_in_max_attempts' => 'Попыток входа до блокировки',

            'journal.retention_days' => 'Хранение журнала (дней)',
        ],
    ],

];
