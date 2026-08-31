<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds one template row per (type, locale, channel) combination the
 * notification type's own `default_channels` names. Administrator-editable
 * seed content, not fixed strings — `NotificationDispatchService` renders
 * and stores this text at creation time, so an edit here only changes what
 * a *future* notification says.
 */
final class NotificationTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $typeIds = DB::table('notification_types')->pluck('id', 'key');
        $channelIds = DB::table('notification_channels')->pluck('id', 'key');
        $now = now();

        foreach ($this->templates() as $typeKey => $variants) {
            $typeId = $typeIds[$typeKey] ?? null;

            if ($typeId === null) {
                continue;
            }

            foreach ($variants as $channelKey => $locales) {
                $channelId = $channelIds[$channelKey] ?? null;

                if ($channelId === null) {
                    continue;
                }

                foreach ($locales as $locale => $content) {
                    DB::table('notification_templates')->insert([
                        'notification_type_id' => $typeId,
                        'locale' => $locale,
                        'notification_channel_id' => $channelId,
                        'subject' => $content['subject'] ?? null,
                        'body' => $content['body'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, array<string, array<string, array{subject?: string, body: string}>>>
     */
    private function templates(): array
    {
        return [
            'placement_expiring' => [
                'email' => [
                    'en' => ['subject' => 'Your placement is expiring soon', 'body' => 'Your current placement package is nearing its end date. Renew it from your cabinet to keep your position in the catalog.'],
                    'ru' => ['subject' => 'Срок вашего размещения скоро истекает', 'body' => 'Срок действия вашего пакета размещения подходит к концу. Продлите его в личном кабинете, чтобы сохранить позицию в каталоге.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'Your placement package is expiring soon. Renew it to keep your current position.'],
                    'ru' => ['body' => 'Срок действия вашего пакета размещения скоро истекает. Продлите его, чтобы сохранить текущую позицию.'],
                ],
            ],
            'package_expired' => [
                'email' => [
                    'en' => ['subject' => 'Your placement package has expired', 'body' => 'Your placement package has expired and the configured expiry action has been applied. Visit your cabinet to purchase a new package.'],
                    'ru' => ['subject' => 'Срок действия пакета размещения истёк', 'body' => 'Срок действия вашего пакета размещения истёк, применено настроенное действие по истечении срока. Оформите новый пакет в личном кабинете.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'Your placement package has expired.'],
                    'ru' => ['body' => 'Срок действия вашего пакета размещения истёк.'],
                ],
            ],
            'moderation_approved' => [
                'email' => [
                    'en' => ['subject' => 'Your changes were published', 'body' => 'Changes published. Your submitted changes have been reviewed and are now live on your object page.'],
                    'ru' => ['subject' => 'Ваши изменения опубликованы', 'body' => 'Изменения опубликованы. Отправленные вами изменения прошли проверку и теперь отображаются на странице объекта.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'Changes published.'],
                    'ru' => ['body' => 'Изменения опубликованы.'],
                ],
            ],
            'moderation_rejected' => [
                'email' => [
                    'en' => ['subject' => 'Your changes require revision', 'body' => 'Changes require revision. A moderator has reviewed your submission and requires changes before it can be published — see the reason in your cabinet.'],
                    'ru' => ['subject' => 'Ваши изменения требуют доработки', 'body' => 'Изменения требуют доработки. Модератор рассмотрел вашу заявку и требует внести изменения перед публикацией — причина указана в личном кабинете.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'Changes require revision.'],
                    'ru' => ['body' => 'Изменения требуют доработки.'],
                ],
            ],
            'revision_requested' => [
                'email' => [
                    'en' => ['subject' => 'A section of your object needs revision', 'body' => 'A moderator has requested a revision to one section of your object. Review the reason in your cabinet and resubmit.'],
                    'ru' => ['subject' => 'Раздел вашего объекта требует доработки', 'body' => 'Модератор запросил доработку одного из разделов вашего объекта. Ознакомьтесь с причиной в личном кабинете и отправьте изменения повторно.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'A section of your object needs revision.'],
                    'ru' => ['body' => 'Раздел вашего объекта требует доработки.'],
                ],
            ],
            'information_out_of_date' => [
                'inbox' => [
                    'en' => ['body' => 'Some information on your object page may be out of date. Please review and update it.'],
                    'ru' => ['body' => 'Некоторая информация на странице вашего объекта может быть устаревшей. Пожалуйста, проверьте и обновите её.'],
                ],
            ],
            'confirm_availability_status' => [
                'inbox' => [
                    'en' => ['body' => 'Please confirm whether your object currently has vacancies available.'],
                    'ru' => ['body' => 'Пожалуйста, подтвердите, есть ли сейчас свободные места в вашем объекте.'],
                ],
            ],
            'administration_message' => [
                'inbox' => [
                    'en' => ['body' => 'You have a message from the portal administration.'],
                    'ru' => ['body' => 'У вас есть сообщение от администрации портала.'],
                ],
            ],
            'object_status_changed' => [
                'email' => [
                    'en' => ['subject' => 'Your object\'s publication status has changed', 'body' => 'An administrator has changed the publication status of your object. Check your cabinet for details.'],
                    'ru' => ['subject' => 'Статус публикации вашего объекта изменён', 'body' => 'Администратор изменил статус публикации вашего объекта. Подробности — в личном кабинете.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'Your object\'s publication status has changed.'],
                    'ru' => ['body' => 'Статус публикации вашего объекта изменён.'],
                ],
            ],
            'system_message' => [
                'email' => [
                    'en' => ['subject' => 'System notification', 'body' => 'A system event affecting your account has occurred. Check your cabinet for details.'],
                    'ru' => ['subject' => 'Системное уведомление', 'body' => 'Произошло системное событие, затрагивающее ваш аккаунт. Подробности — в личном кабинете.'],
                ],
                'inbox' => [
                    'en' => ['body' => 'A system event affecting your account has occurred.'],
                    'ru' => ['body' => 'Произошло системное событие, затрагивающее ваш аккаунт.'],
                ],
            ],
        ];
    }
}
