<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('content.osp.account.profile', [
            'user' => $user,
            'twoFactorEnabled' => ! is_null($user->two_factor_secret),
        ]);
    }
}
