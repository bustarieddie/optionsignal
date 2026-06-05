<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRiskSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiskSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        $risk = $user->riskSetting;

        if (! $risk) {
            $risk = $user->riskSetting()->create(config('risk.defaults'));
        }

        return view('content.osp.risk.edit', compact('risk'));
    }

    public function update(UpdateRiskSettingRequest $request): RedirectResponse
    {
        $user = $request->user();

        $risk = $user->riskSetting ?: $user->riskSetting()->make();
        $risk->fill($request->validated());
        $risk->user_id = $user->id;
        $risk->save();

        return redirect()->route('risk.edit')->with('status', 'Risk settings saved.');
    }
}
