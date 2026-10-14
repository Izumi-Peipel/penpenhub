<?php

namespace App\Http\Controllers;

use App\Support\ContextSwitcher;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function update(Request $request, ContextSwitcher $switcher)
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
        ]);

        $switcher->select(
            $request->user(),
            isset($data['company_id']) ? (int) $data['company_id'] : null,
            isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            isset($data['outlet_id']) ? (int) $data['outlet_id'] : null,
        );

        return back();
    }
}
