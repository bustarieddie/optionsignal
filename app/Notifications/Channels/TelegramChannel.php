<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Telegram notification channel — no external package required.
 * Active only when services.telegram.bot_token is set. A notification opts in
 * by implementing toTelegram($notifiable): string.
 *
 * The recipient chat id is resolved from (in order):
 *   1. $notifiable->routeNotificationFor('telegram')
 *   2. config('services.telegram.chat_id')
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $token = config('services.telegram.bot_token');
        $chatId = $notifiable->routeNotificationFor('telegram')
            ?? config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return; // not configured — silently skip
        }

        $text = $notification->toTelegram($notifiable);

        $response = Http::asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        if ($response->failed()) {
            Log::warning('Telegram notification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
