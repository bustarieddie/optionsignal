<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStrategyRequest;
use App\Http\Requests\UpdateStrategyRequest;
use App\Models\Strategy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategyController extends Controller
{
    public function index(Request $request): View
    {
        $strategies = Strategy::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhereNull('user_id');
        })->orderBy('user_id')->orderBy('name')->get();

        return view('content.osp.strategies.index', compact('strategies'));
    }

    public function create(): View
    {
        $strategy = new Strategy(['active' => true, 'timeframes' => []]);

        return view('content.osp.strategies.create', compact('strategy'));
    }

    public function store(StoreStrategyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $strategy = $request->user()->strategies()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'timeframes' => $data['timeframes'] ?? [],
            'active' => $request->boolean('active'),
        ]);

        $this->syncRules($strategy, $data['rules'] ?? []);

        return redirect()->route('strategies.show', $strategy)->with('status', 'Strategy created.');
    }

    public function show(Request $request, Strategy $strategy): View
    {
        abort_unless(
            $strategy->user_id === null || $strategy->user_id === $request->user()->id,
            403
        );

        $strategy->load('rules');
        $rulesByType = $strategy->rules->groupBy('rule_type');

        return view('content.osp.strategies.show', compact('strategy', 'rulesByType'));
    }

    public function edit(Request $request, Strategy $strategy): View
    {
        abort_unless($strategy->user_id === $request->user()->id, 403);

        $strategy->load('rules');

        return view('content.osp.strategies.edit', compact('strategy'));
    }

    public function update(UpdateStrategyRequest $request, Strategy $strategy): RedirectResponse
    {
        abort_unless($strategy->user_id === $request->user()->id, 403);

        $data = $request->validated();

        $strategy->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'timeframes' => $data['timeframes'] ?? [],
            'active' => $request->boolean('active'),
        ]);

        $strategy->rules()->delete();
        $this->syncRules($strategy, $data['rules'] ?? []);

        return redirect()->route('strategies.show', $strategy)->with('status', 'Strategy updated.');
    }

    public function destroy(Request $request, Strategy $strategy): RedirectResponse
    {
        abort_unless($strategy->user_id === $request->user()->id, 403);

        $strategy->delete();

        return redirect()->route('strategies.index')->with('status', 'Strategy deleted.');
    }

    /**
     * Recreate the strategy's rules from submitted rows.
     */
    protected function syncRules(Strategy $strategy, array $rules): void
    {
        $sort = 0;
        foreach ($rules as $row) {
            if (empty($row['condition_key']) || empty($row['rule_type'])) {
                continue;
            }

            $strategy->rules()->create([
                'rule_type' => $row['rule_type'],
                'component' => $row['component'] ?? null,
                'condition_key' => $row['condition_key'],
                'operator' => $row['operator'] ?? null,
                'value' => $row['value'] ?? null,
                'points' => $row['points'] ?? null,
                'sort' => $sort++,
            ]);
        }
    }
}
