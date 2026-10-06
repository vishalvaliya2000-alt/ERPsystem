<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();
        $members = User::with('department')->orderBy('name')->get();
        $departments = Department::with('manager')->get();

        return view('organization.team', compact('tenant', 'members', 'departments'));
    }

    public function invite(Request $request): RedirectResponse
    {
        $actor = auth()->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'in:admin,department_manager,staff,viewer'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // Authorization checks
        if ($actor->isDepartmentManager()) {
            if ($validated['role'] === 'admin' || $validated['role'] === 'department_manager') {
                throw ValidationException::withMessages([
                    'role' => 'Department managers can only invite staff and viewers.',
                ]);
            }
            if ((int) ($validated['department_id'] ?? 0) !== (int) $actor->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'You can only add members to your own department.',
                ]);
            }
        } elseif (! $actor->isAdmin()) {
            abort(403, 'You do not have permission to invite team members.');
        }

        if ($validated['role'] === 'department_manager' && empty($validated['department_id'])) {
            throw ValidationException::withMessages([
                'department_id' => 'A department must be selected when appointing a department manager.',
            ]);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'department_id' => $validated['department_id'] ?? null,
                'is_active' => true,
            ]);

            if ($validated['role'] === 'department_manager' && ! empty($validated['department_id'])) {
                $department = Department::findOrFail($validated['department_id']);
                $department->assignManager($user);
            } else {
                DepartmentPermissionService::syncUserPermissions($user);
            }

            activity('team')
                ->performedOn($user)
                ->causedBy($actor)
                ->log("Invited team member {$user->name} as {$user->role}");

            DB::commit();

            return back()->with('success', "✓ Team member {$user->name} has been added successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to add team member: '.$e->getMessage());
        }
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $actor = auth()->user();
        $targetUser = User::findOrFail($id);

        if (! DepartmentPermissionService::canManage($actor, $targetUser) && ! ($actor->isOwner() && (int) $actor->id === (int) $targetUser->id)) {
            abort(403, 'You do not have permission to modify this team member.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['required', 'in:owner,admin,department_manager,staff,viewer'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if ($targetUser->isOwner() && $validated['role'] !== 'owner') {
            $otherOwners = User::where('role', 'owner')
                ->where('id', '!=', $targetUser->id)
                ->where('is_active', true)
                ->count();
            if ($otherOwners === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Cannot revoke owner role from the sole active owner.',
                ]);
            }
        }

        if ($actor->isDepartmentManager()) {
            if (in_array($validated['role'], ['admin', 'department_manager', 'owner'], true)) {
                throw ValidationException::withMessages([
                    'role' => 'Department managers cannot elevate staff to manager or admin roles.',
                ]);
            }
            if ((int) ($validated['department_id'] ?? 0) !== (int) $actor->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'Cannot transfer staff to a different department.',
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $targetUser->name = $validated['name'];
            $targetUser->phone = $validated['phone'] ?? null;

            if (! empty($validated['password'])) {
                $targetUser->password = Hash::make($validated['password']);
            }

            $oldRole = $targetUser->role;
            $newRole = $validated['role'];
            $newDeptId = $validated['department_id'] ? (int) $validated['department_id'] : null;

            if ($newRole === 'department_manager') {
                if (! $newDeptId) {
                    throw ValidationException::withMessages([
                        'department_id' => 'Department is required for a department manager.',
                    ]);
                }
                $targetUser->save();
                $department = Department::findOrFail($newDeptId);
                $department->assignManager($targetUser);
            } else {
                // If demoting from department manager, remove manager designation from department
                if ($oldRole === 'department_manager') {
                    Department::where('manager_user_id', $targetUser->id)
                        ->update(['manager_user_id' => null]);
                }

                $targetUser->role = $newRole;
                $targetUser->department_id = $newDeptId;
                $targetUser->save();

                DepartmentPermissionService::syncUserPermissions($targetUser);
            }

            activity('team')
                ->performedOn($targetUser)
                ->causedBy($actor)
                ->log("Updated team member {$targetUser->name} ({$targetUser->role})");

            DB::commit();

            return back()->with('success', "✓ Team member {$targetUser->name} updated successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Failed to update member: '.$e->getMessage());
        }
    }

    public function toggleStatus(int|string $id): RedirectResponse
    {
        $actor = auth()->user();
        $targetUser = User::findOrFail($id);

        if ((int) $actor->id === (int) $targetUser->id) {
            return back()->with('error', 'You cannot deactivate your own active account.');
        }

        if (! DepartmentPermissionService::canManage($actor, $targetUser)) {
            abort(403, 'You do not have permission to alter the status of this member.');
        }

        if ($targetUser->isOwner() && $targetUser->is_active) {
            $otherActiveOwners = User::where('role', 'owner')
                ->where('is_active', true)
                ->where('id', '!=', $targetUser->id)
                ->count();

            if ($otherActiveOwners === 0) {
                return back()->with('error', 'Cannot deactivate the sole active organization owner.');
            }
        }

        $targetUser->is_active = ! $targetUser->is_active;
        $targetUser->save();

        $action = $targetUser->is_active ? 'Activated' : 'Deactivated';

        activity('team')
            ->performedOn($targetUser)
            ->causedBy($actor)
            ->log("{$action} user account {$targetUser->name}");

        return back()->with('success', "✓ Successfully {$action} account for {$targetUser->name}.");
    }

    public function destroy(int|string $id): RedirectResponse
    {
        $actor = auth()->user();
        $targetUser = User::findOrFail($id);

        if ((int) $actor->id === (int) $targetUser->id) {
            return back()->with('error', 'You cannot remove your own active account.');
        }

        if (! DepartmentPermissionService::canManage($actor, $targetUser)) {
            abort(403, 'You do not have permission to remove this member.');
        }

        if ($targetUser->isOwner()) {
            $otherOwners = User::where('role', 'owner')
                ->where('id', '!=', $targetUser->id)
                ->count();

            if ($otherOwners === 0) {
                return back()->with('error', 'Cannot remove the sole organization owner.');
            }
        }

        DB::beginTransaction();
        try {
            Department::where('manager_user_id', $targetUser->id)
                ->update(['manager_user_id' => null]);

            activity('team')
                ->performedOn($targetUser)
                ->causedBy($actor)
                ->log("Removed user {$targetUser->name}");

            $targetUser->delete();

            DB::commit();

            return back()->with('success', "✓ Member {$targetUser->name} has been removed.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to remove member: '.$e->getMessage());
        }
    }
}
