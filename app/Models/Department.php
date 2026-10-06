<?php

namespace App\Models;

use App\Services\DepartmentPermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Department extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'manager_user_id',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'manager_user_id', 'description'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * Assign a single user as department manager, ensuring exactly one manager per department rule.
     * Transitions any previous manager to staff and prevents cross-department conflicts.
     */
    public function assignManager(User $user): void
    {
        if ($user->department_id && (int) $user->department_id !== (int) $this->id) {
            throw new InvalidArgumentException("User '{$user->name}' belongs to another department. A user must belong to this department to be appointed its manager.");
        }

        // If this user was previously manager of another department, clear that department's manager
        static::where('manager_user_id', $user->id)
            ->where('id', '!=', $this->id)
            ->update(['manager_user_id' => null]);

        // If this department previously had a different manager, demote old manager to staff
        if ($this->manager_user_id && (int) $this->manager_user_id !== (int) $user->id) {
            $oldManager = User::find($this->manager_user_id);
            if ($oldManager && $oldManager->role === 'department_manager') {
                $oldManager->update(['role' => 'staff']);
                DepartmentPermissionService::syncUserPermissions($oldManager);
            }
        }

        $this->update(['manager_user_id' => $user->id]);

        $user->update([
            'department_id' => $this->id,
            'role' => 'department_manager',
        ]);

        DepartmentPermissionService::syncUserPermissions($user);

        activity('departments')
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->log("Assigned {$user->name} as Department Manager of {$this->name}");
    }
}
