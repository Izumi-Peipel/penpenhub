<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\CurrentContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Pertahanan berlapis: menulis ke perusahaan lain ditolak walau Policy terlewat.
        static::creating(function (Model $model) {
            if ($model->company_id === null && method_exists($model, 'resolveCompanyId')) {
                $model->company_id = $model->resolveCompanyId();
            }

            $ids = app(CurrentContext::class)->companyIds();

            if ($ids !== null && ! in_array((int) $model->company_id, $ids, true)) {
                throw new AuthorizationException('Akses lintas perusahaan ditolak.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }
}
