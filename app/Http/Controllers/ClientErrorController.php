<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Terima laporan error client-side dari ErrorBoundary React.
 *
 * Error React murni client-side — tidak ada exception Laravel, jadi admin
 * tidak akan pernah melihat stack trace-nya kecuali user copy-paste manual.
 * Endpoint ini jadi sumber diagnosa utama: log ke laravel.log dengan channel
 * terpisah supaya gampang di-grep.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
            'stack' => 'nullable|string|max:10000',
            'component_stack' => 'nullable|string|max:10000',
            'url' => 'nullable|string|max:2000',
            'user_agent' => 'nullable|string|max:1000',
            'timestamp' => 'nullable|string|max:50',
        ]);

        // Masuk channel default (stack 'single' = storage/logs/laravel.log).
        // Prefix 'react_errorboundary' agar gampang di-grep.
        Log::error('react_errorboundary', $data);

        return response()->noContent();
    }
}
