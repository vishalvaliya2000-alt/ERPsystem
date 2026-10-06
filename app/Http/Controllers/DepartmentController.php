<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();
        $departments = Department::with(['manager', 'users'])->get();
        $allUsers = User::where('is_active', true)->orderBy('name')->get();

        return view('organization.departments', compact('tenant', 'departments', 'allUsers'));
    }

    public function assignManager(Request $request, Department $department): RedirectResponse
    {
        $actor = auth()->user();

        if (! $actor->isAdmin()) {
            abort(403, 'Only organization owners and administrators can assign department managers.');
        }

        $validated = $request->validate([
            'manager_user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['manager_user_id']);

        try {
            $department->assignManager($user);

            return back()->with('success', "✓ Assigned {$user->name} as Department Manager of {$department->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to assign department manager: '.$e->getMessage());
        }
    }
}
