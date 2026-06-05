<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWatchlistRequest;
use App\Http\Requests\UpdateWatchlistRequest;
use App\Models\Watchlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request): View
    {
        $watchlists = $request->user()->watchlists()->orderBy('ticker')->get();

        return view('content.osp.watchlist.index', compact('watchlists'));
    }

    public function create(): View
    {
        return view('content.osp.watchlist.create');
    }

    public function store(StoreWatchlistRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['optionable'] = $request->boolean('optionable');
        $data['active'] = $request->boolean('active');

        $request->user()->watchlists()->create($data);

        return redirect()->route('watchlist.index')->with('status', 'Ticker added to watchlist.');
    }

    public function edit(Request $request, Watchlist $watchlist): View
    {
        abort_unless($watchlist->user_id === $request->user()->id, 403);

        return view('content.osp.watchlist.edit', compact('watchlist'));
    }

    public function update(UpdateWatchlistRequest $request, Watchlist $watchlist): RedirectResponse
    {
        abort_unless($watchlist->user_id === $request->user()->id, 403);

        $data = $request->validated();
        $data['optionable'] = $request->boolean('optionable');
        $data['active'] = $request->boolean('active');

        $watchlist->update($data);

        return redirect()->route('watchlist.index')->with('status', 'Ticker updated.');
    }

    public function destroy(Request $request, Watchlist $watchlist): RedirectResponse
    {
        abort_unless($watchlist->user_id === $request->user()->id, 403);

        $watchlist->delete();

        return redirect()->route('watchlist.index')->with('status', 'Ticker removed from watchlist.');
    }
}
