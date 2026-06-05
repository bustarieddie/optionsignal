<?php

namespace Tests\Feature;

use App\Models\Signal;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\SignalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_channel_inactive_without_token(): void
    {
        config(['services.telegram.bot_token' => null]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $signal = $this->signalFor($user);

        $this->assertNotContains(TelegramChannel::class, (new SignalNotification($signal))->via($user));
    }

    public function test_telegram_message_is_sent_when_configured(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '999',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $signal = $this->signalFor($user);

        $notification = new SignalNotification($signal, ['Daily trade limit reached.']);
        $this->assertContains(TelegramChannel::class, $notification->via($user));

        $user->notify($notification);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bottest-token/sendMessage')
                && $request['chat_id'] === '999'
                && str_contains($request['text'], 'NVDA');
        });
    }

    private function signalFor(User $user): Signal
    {
        return Signal::create([
            'user_id' => $user->id,
            'ticker' => 'NVDA',
            'timeframe' => '5m',
            'signal_type' => 'buy_call',
            'price' => 120.50,
            'grade' => 'A+',
            'total_score' => 100,
            'status' => 'active',
            'occurred_at' => now(),
        ]);
    }
}
