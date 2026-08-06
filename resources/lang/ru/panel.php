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
