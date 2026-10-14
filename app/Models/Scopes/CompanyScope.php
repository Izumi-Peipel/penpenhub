<?php

namespace App\Models\Scopes;

use App\Support\CurrentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CompanyScope implements Scope
{
    public function __construct(private readonly string $column = 'company_id')
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $ids = app(CurrentContext::class)->companyIds();

        if ($ids === null) {
            return;
        }

        // whereIn([]) menghasilkan 0 baris: gagal tertutup untuk pengguna tanpa membership.
        $builder->whereIn($model->qualifyColumn($this->column), $ids);
    }
}
