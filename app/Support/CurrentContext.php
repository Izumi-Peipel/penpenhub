<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Konteks tenant aktif untuk request berjalan. Dipakai CompanyScope.
 *
 * Catatan keamanan: tanpa pengguna terautentikasi (console, queue, seeder) scope tidak
 * memfilter. Semua route aplikasi wajib berada di balik middleware 'auth'.
 */
final class CurrentContext
{
    public const SESSION_KEY = 'active_company_id';

    /** @var array<int, int[]> */
    private array $allowed = [];

    /**
     * ID perusahaan yang boleh dilihat pengguna saat ini.
     * null = tidak dibatasi (tanpa pengguna, atau Super Admin). Array kosong = tidak melihat apa pun.
     *
     * @return int[]|null
     */
    public function companyIds(): ?array
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return null;
        }

        $allowed = $this->allowedFor($user);
        $active = session(self::SESSION_KEY);

        if ($active !== null && in_array((int) $active, $allowed, true)) {
            return [(int) $active];
        }

        return $allowed;
    }

    /** @return int[]|null null = semua (Super Admin) */
    public function allowedCompanyIds(User $user): ?array
    {
        return $user->isSuperAdmin() ? null : $this->allowedFor($user);
    }

    public function setActive(User $user, int $companyId): void
    {
        if (! $user->isSuperAdmin() && ! in_array($companyId, $this->allowedFor($user), true)) {
            throw new AuthorizationException('Perusahaan tidak termasuk dalam akses Anda.');
        }

        session([self::SESSION_KEY => $companyId]);
    }

    public function flush(): void
    {
        $this->allowed = [];
    }

    /** @return int[] */
    private function allowedFor(User $user): array
    {
        return $this->allowed[$user->getKey()] ??= Membership::query()
            ->where('user_id', $user->getKey())
            ->distinct()
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
