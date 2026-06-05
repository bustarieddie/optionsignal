<?php

namespace App\Services\Backtest;

use League\Csv\Reader;
use Throwable;

class CsvParser
{
    /**
     * Parse a CSV file into normalized backtest trade rows.
     *
     * Supports two shapes, auto-detected by header:
     *  - TradingView "List of Trades": Entry & Exit are SEPARATE rows paired
     *    by a "Trade #" column (Type = "Entry Long"/"Exit Long" etc.).
     *  - Generic: one row per trade with ticker,direction,entry,exit,pnl headers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $absolutePath): array
    {
        $csv = Reader::createFromPath($absolutePath, 'r');
        $csv->setHeaderOffset(0);

        $header = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $csv->getHeader()
        );

        if ($this->looksLikeTradingView($header)) {
            return $this->parseTradingView($csv);
        }

        return $this->parseGeneric($csv);
    }

    protected function looksLikeTradingView(array $header): bool
    {
        $hasTradeNo = false;
        $hasType = false;

        foreach ($header as $h) {
            if (str_contains($h, 'trade #')) {
                $hasTradeNo = true;
            }
            if ($h === 'type') {
                $hasType = true;
            }
        }

        return $hasTradeNo && $hasType;
    }

    /**
     * Fold each Entry+Exit pair (keyed by Trade #) into a single normalized row.
     */
    protected function parseTradingView(Reader $csv): array
    {
        $groups = [];

        foreach ($csv->getRecords() as $record) {
            $row = $this->normalizeKeys($record);

            $tradeNo = $this->pick($row, ['trade #', 'trade#', 'trade number']);
            $type = strtolower((string) $this->pick($row, ['type']));

            if ($tradeNo === null || $type === '') {
                continue;
            }

            if (! isset($groups[$tradeNo])) {
                $groups[$tradeNo] = ['entry' => null, 'exit' => null, 'profit' => null, 'direction' => null];
            }

            $price = $this->toFloat($this->pick($row, ['price', 'price usd', 'price usdt']));
            $dateTime = $this->pick($row, ['date/time', 'date / time', 'datetime', 'date']);
            $profit = $this->toFloat($this->pick($row, ['profit', 'profit usd', 'net profit', 'p&l', 'pnl']));

            $direction = str_contains($type, 'short') ? 'put' : 'call';

            if (str_contains($type, 'entry')) {
                $groups[$tradeNo]['entry'] = $price;
                $groups[$tradeNo]['direction'] = $direction;
                $groups[$tradeNo]['entry_time'] = $dateTime;
            } elseif (str_contains($type, 'exit')) {
                $groups[$tradeNo]['exit'] = $price;
                $groups[$tradeNo]['profit'] = $profit;
                $groups[$tradeNo]['exit_time'] = $dateTime;
            }

            // Carry ticker/timeframe/setup/grade if present anywhere.
            $groups[$tradeNo]['ticker'] ??= $this->pick($row, ['ticker', 'symbol']);
            $groups[$tradeNo]['timeframe'] ??= $this->pick($row, ['timeframe', 'tf', 'interval']);
            $groups[$tradeNo]['setup'] ??= $this->pick($row, ['setup', 'signal', 'strategy']);
            $groups[$tradeNo]['grade'] ??= $this->pick($row, ['grade']);
        }

        $rows = [];

        foreach ($groups as $g) {
            if ($g['entry'] === null) {
                continue; // malformed pair
            }

            $pnl = $g['profit'];
            if ($pnl === null && $g['exit'] !== null) {
                $diff = $g['exit'] - $g['entry'];
                $pnl = ($g['direction'] ?? 'call') === 'put' ? -$diff : $diff;
            }
            $pnl = $pnl === null ? 0.0 : round((float) $pnl, 2);

            $rows[] = [
                'ticker' => $g['ticker'] ?: null,
                'timeframe' => $g['timeframe'] ?: null,
                'direction' => $g['direction'] ?? 'call',
                'entry' => $g['entry'],
                'exit' => $g['exit'],
                'pnl' => $pnl,
                'grade' => $g['grade'] ?: null,
                'setup' => $g['setup'] ?: null,
                'result' => $this->resultFromPnl($pnl),
                'occurred_at' => $this->toDate($g['exit_time'] ?? ($g['entry_time'] ?? null)),
            ];
        }

        return $rows;
    }

    /**
     * One normalized row per CSV line. Defensive: skip rows lacking key data.
     */
    protected function parseGeneric(Reader $csv): array
    {
        $rows = [];

        foreach ($csv->getRecords() as $record) {
            try {
                $row = $this->normalizeKeys($record);

                $entry = $this->toFloat($this->pick($row, ['entry', 'entry price', 'entry_price']));
                $exit = $this->toFloat($this->pick($row, ['exit', 'exit price', 'exit_price']));
                $pnl = $this->toFloat($this->pick($row, ['pnl', 'profit', 'p&l', 'net profit']));

                if ($entry === null && $exit === null && $pnl === null) {
                    continue;
                }

                $direction = strtolower((string) $this->pick($row, ['direction', 'side', 'type']));
                $direction = str_contains($direction, 'put') || str_contains($direction, 'short') ? 'put' : 'call';

                if ($pnl === null && $entry !== null && $exit !== null) {
                    $diff = $exit - $entry;
                    $pnl = $direction === 'put' ? -$diff : $diff;
                }
                $pnl = $pnl === null ? 0.0 : round((float) $pnl, 2);

                $rows[] = [
                    'ticker' => $this->pick($row, ['ticker', 'symbol']) ?: null,
                    'timeframe' => $this->pick($row, ['timeframe', 'tf', 'interval']) ?: null,
                    'direction' => $direction,
                    'entry' => $entry,
                    'exit' => $exit,
                    'pnl' => $pnl,
                    'grade' => $this->pick($row, ['grade']) ?: null,
                    'setup' => $this->pick($row, ['setup', 'signal', 'strategy']) ?: null,
                    'result' => $this->resultFromPnl($pnl),
                    'occurred_at' => $this->toDate($this->pick($row, ['occurred_at', 'date/time', 'datetime', 'date'])),
                ];
            } catch (Throwable $e) {
                continue; // skip malformed row
            }
        }

        return $rows;
    }

    protected function resultFromPnl(float $pnl): string
    {
        return $pnl > 0 ? 'win' : ($pnl < 0 ? 'loss' : 'be');
    }

    /**
     * Lowercase + trim every key so lookups are header-case-insensitive.
     */
    protected function normalizeKeys(array $record): array
    {
        $out = [];
        foreach ($record as $k => $v) {
            $out[strtolower(trim((string) $k))] = $v;
        }

        return $out;
    }

    protected function pick(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) {
                return $row[$key];
            }
        }

        return null;
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Strip currency symbols, thousands separators, percent, whitespace.
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($clean === '' || $clean === '-' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    protected function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}
