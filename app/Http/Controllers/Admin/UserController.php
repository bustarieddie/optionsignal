<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * List users with their roles.
     */
    public function index(): View
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(20);

        $roles = ['Admin', 'Trader', 'Viewer'];

        return view('content.osp.admin.users', compact('users', 'roles'));
    }

    /**
     * Change a user's role (single-role model: Admin / Trader / Viewer).
     */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:Admin,Trader,Viewer'],
        ]);

        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.users')
            ->with('status', "Updated {$user->name}'s role to {$validated['role']}.");
    }
}
