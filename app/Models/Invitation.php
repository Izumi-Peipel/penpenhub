<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'email', 'role', 'scope_type', 'scope_id',
        'token_hash', 'invited_by', 'expires_at', 'accepted_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScopes()->where('token_hash', self::hashToken($token))->first();
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
