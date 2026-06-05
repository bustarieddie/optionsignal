<?php

namespace App\Jobs;

use App\Models\Backtest;
use App\Services\Backtest\CsvParser;
use App\Services\Backtest\MetricsCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessBacktestImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $backtestId)
    {
    }

    public function handle(CsvParser $parser, MetricsCalculator $calculator): void
    {
        $backtest = Backtest::find($this->backtestId);

        if (! $backtest) {
            return;
        }

        try {
            $backtest->update(['status' => 'processing', 'error' => null]);

            $absolutePath = Storage::disk('local')->path($backtest->file_path);

            $rows = $parser->parse($absolutePath);

            $now = now();
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'backtest_id' => $backtest->id,
                    'ticker' => $row['ticker'] ?? null,
                    'timeframe' => $row['timeframe'] ?? null,
                    'direction' => $row['direction'] ?? null,
                    'entry' => $row['entry'] ?? null,
                    'exit' => $row['exit'] ?? null,
                    'pnl' => $row['pnl'] ?? 0,
                    'grade' => $row['grade'] ?? null,
                    'setup' => $row['setup'] ?? null,
                    'result' => $row['result'] ?? 'be',
                    'occurred_at' => $row['occurred_at'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Replace any prior rows then bulk insert (chunked to be safe).
            $backtest->trades()->delete();
            foreach (array_chunk($records, 500) as $chunk) {
                \App\Models\BacktestTrade::insert($chunk);
            }

            $metrics = $calculator->compute($rows);

            $backtest->update([
                'metrics' => $metrics,
                'rows_count' => count($rows),
                'status' => 'done',
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $backtest->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
