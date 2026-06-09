<?php

namespace Tests\Feature;

use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TestSignalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_signal_and_notifies_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->artisan('signal:test', ['email' => $user->email, '--ticker' => 'NVDA'])
            ->assertSuccessful();

        $this->assertDatabaseHas('signals', [
            'user_id' => $user->id,
            'ticker' => 'NVDA',
            'signal_type' => 'buy_call',
            'grade' => 'A',
        ]);

        Notification::assertSentTo($user, \App\Notifications\SignalNotification::class);
    }

    public function test_cleanup_removes_the_synthetic_signal(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->artisan('signal:test', ['email' => $user->email, '--cleanup' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('signals', 0);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->artisan('signal:test', ['email' => $user->email, '--type' => 'sideways'])
            ->assertFailed();
    }

    public function test_pings_telegram_when_configured(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '999',
        ]);
        Http::fake([
            'api.telegram.org/bottest-token/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'osp_bot']], 200),
            'api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();

        $this->artisan('signal:test', ['email' => $user->email])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '999');
    }
}
