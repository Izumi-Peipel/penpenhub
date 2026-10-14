<?php

namespace App\Models;

use App\Support\CurrentContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class Membership extends Model
{
    protected $fillable = ['user_id', 'company_id', 'role', 'scope_type', 'scope_id'];

    protected $casts = ['user_id' => 'integer', 'company_id' => 'integer', 'scope_id' => 'integer'];

    /** @var array<string, string[]> */
    private static array $rolePermissions = [];

    protected static function booted(): void
    {
        $flush = fn () => app(CurrentContext::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, self::permissionsForRole($this->role), true);
    }

    /** @return string[] */
    public static function permissionsForRole(string $role): array
    {
        return self::$rolePermissions[$role] ??= Role::query()
            ->where('name', $role)
            ->where('guard_name', 'web')
            ->first()
            ?->permissions
            ->pluck('name')
            ->all() ?? [];
    }

    public static function flushPermissionCache(): void
    {
        self::$rolePermissions = [];
    }
}
