<?php

namespace App\Notifications;

use App\Models\Signal;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignalNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $riskReasons
     */
    public function __construct(
        public Signal $signal,
        public array $riskReasons = [],
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email_verified_at) {
            $channels[] = 'mail';
        }

        // Telegram is opt-in: active only when a bot token is configured.
        if (config('services.telegram.bot_token')) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toTelegram(object $notifiable): string
    {
        $lines = ['<b>' . e($this->message()) . '</b>'];

        if ($this->riskReasons) {
            $lines[] = '⚠️ ' . e(implode(' ', $this->riskReasons));
        }

        $lines[] = 'Decision support only — verify the option chain manually.';

        return implode("\n", $lines);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $side = strtoupper(str_replace('buy_', '', $this->signal->signal_type));

        $mail = (new MailMessage)
            ->subject("{$this->signal->ticker} {$side} signal — Grade {$this->signal->grade}")
            ->greeting("New {$side} signal on {$this->signal->ticker}")
            ->line($this->message())
            ->line('Check the option chain manually before any trade. Decision support, not financial advice.');

        if ($this->riskReasons) {
            $mail->line('Risk notes: ' . implode(' ', $this->riskReasons));
        }

        return $mail->action('View signal', route('signals.show', $this->signal));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'signal_id' => $this->signal->id,
            'ticker' => $this->signal->ticker,
            'signal_type' => $this->signal->signal_type,
            'timeframe' => $this->signal->timeframe,
            'grade' => $this->signal->grade,
            'total_score' => $this->signal->total_score,
            'message' => $this->message(),
            'risk_reasons' => $this->riskReasons,
        ];
    }

    private function message(): string
    {
        $side = strtoupper(str_replace('buy_', '', $this->signal->signal_type));

        return sprintf(
            '%s %s signal detected on %s. Grade %s (%d pts).',
            $this->signal->ticker,
            $side,
            $this->signal->timeframe,
            $this->signal->grade,
            $this->signal->total_score,
        );
    }
}
