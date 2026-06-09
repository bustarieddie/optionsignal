<?php

namespace App\Console\Commands;

use App\Events\SignalProcessed;
use App\Models\Signal;
use App\Models\User;
use App\Notifications\SignalNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end smoke test for the signal feed + ping pipeline. Creates a
 * synthetic high-grade signal for a user and pushes it through the real
 * SignalProcessed broadcast and SignalNotification (database / mail / Telegram),
 * so you can confirm the in-app feed updates and the Telegram ping arrives
 * without waiting for a live TradingView entry.
 */
class TestSignalNotification extends Command
{
    protected $signature = 'signal:test
        {email? : Recipient user email (defaults to the first user)}
        {--ticker=NVDA : Ticker for the synthetic signal}
        {--type=buy_call : buy_call or buy_put}
        {--cleanup : Delete the synthetic signal after sending (notifications still go out)}';

    protected $description = 'Send a synthetic high-grade signal to verify the in-app feed and Telegram ping end-to-end.';

    public function handle(): int
    {
        $user = ($email = $this->argument('email'))
            ? User::where('email', $email)->first()
            : User::query()->oldest('id')->first();

        if (! $user) {
            $this->error($email ? "No user with email {$email}." : 'No users found.');

            return self::FAILURE;
        }

        $ticker = strtoupper((string) $this->option('ticker'));
        $type = (string) $this->option('type');
        if (! in_array($type, ['buy_call', 'buy_put'], true)) {
            $this->error('--type must be buy_call or buy_put.');

            return self::FAILURE;
        }

        $this->info("Recipient: {$user->name} <{$user->email}> (id {$user->id})");

        // --- Telegram configuration check --------------------------------
        $this->newLine();
        $this->line('Telegram:');
        $token = config('services.telegram.bot_token');
        $chatId = $user->routeNotificationFor('telegram') ?? config('services.telegram.chat_id');
        $telegramActive = false;

        if (! $token) {
            $this->warn('  TELEGRAM_BOT_TOKEN not set — ping will be SKIPPED.');
        } elseif (! $chatId) {
            $this->warn('  TELEGRAM_CHAT_ID not set (and no per-user route) — ping will be SKIPPED.');
        } else {
            try {
                $resp = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
                if ($resp->ok() && $resp->json('ok') === true) {
                    $this->info("  token valid (bot @{$resp->json('result.username')}), chat_id {$chatId} — ping ACTIVE.");
                    $telegramActive = true;
                } else {
                    $this->error("  token check failed (HTTP {$resp->status()}): {$resp->body()}");
                }
            } catch (\Throwable $e) {
                $this->error('  could not reach Telegram API: ' . $e->getMessage());
            }
        }

        // --- Build the synthetic signal ----------------------------------
        $watchlist = $user->watchlists()->where('ticker', $ticker)->where('active', true)->first();
        if (! $watchlist) {
            $this->newLine();
            $this->warn("{$ticker} is not on this user's ACTIVE watchlist — a real webhook would skip them. This test notifies directly anyway.");
        }

        $price = 100.0;
        $up = $type === 'buy_call';
        $signal = Signal::create([
            'user_id' => $user->id,
            'watchlist_id' => $watchlist?->id,
            'ticker' => $ticker,
            'timeframe' => '5m',
            'signal_type' => $type,
            'price' => $price,
            'ema9' => $up ? $price + 0.5 : $price - 0.5,
            'ema21' => $up ? $price - 0.5 : $price + 0.5,
            'rsi' => $up ? 61 : 39,
            'rsi_ma' => $up ? 55 : 45,
            'vwap' => $up ? $price - 0.2 : $price + 0.2,
            'volume_status' => 'above_average',
            'rs_status' => 'leading_both',
            'atr' => 1.0,
            'stop_loss' => $up ? $price - 1 : $price + 1,
            'tp1' => $up ? $price + 1 : $price - 1,
            'tp2' => $up ? $price + 1.5 : $price - 1.5,
            'tp3' => $up ? $price + 2 : $price - 2,
            'grade' => 'A',
            'total_score' => 92,
            'status' => 'active',
            'occurred_at' => now(),
        ]);

        $this->newLine();
        $this->info("Created test signal #{$signal->id} ({$ticker} {$type}, grade A, 92 pts).");

        // Realtime in-app push (Reverb). Don't let a broadcast hiccup abort the test.
        try {
            event(new SignalProcessed($signal));
            $this->line('  broadcast:  SignalProcessed dispatched to App.Models.User.' . $user->id);
        } catch (\Throwable $e) {
            $this->error('  broadcast:  failed — ' . $e->getMessage());
        }

        $user->notify(new SignalNotification($signal, ['This is a synthetic TEST signal — not a real trade idea.']));

        $this->newLine();
        $this->line('Channels dispatched via SignalNotification:');
        $this->line("  in-app feed:  /signals/{$signal->id}");
        $this->line('  database:     sent (bell/notifications)');
        $this->line('  mail:         ' . ($user->email_verified_at ? 'sent (email verified)' : 'skipped (email not verified)'));
        $this->line('  telegram:     ' . ($telegramActive ? 'sent — check your Telegram chat' : 'skipped (not configured/invalid)'));

        if ($this->option('cleanup')) {
            $signal->scores()->delete();
            $signal->optionSuggestion()->delete();
            $signal->delete();
            $this->newLine();
            $this->comment('Synthetic signal deleted (--cleanup). Notifications already went out.');
        } else {
            $this->newLine();
            $this->comment("Signal kept so you can see it in the feed. Re-run with --cleanup, or delete signal #{$signal->id} from the UI.");
        }

        return self::SUCCESS;
    }
}
