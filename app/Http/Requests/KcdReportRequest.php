<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class KcdReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && $user->can('view_laporan_kcd');
    }

    public function rules(): array
    {
        return [
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
            'periode' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'minggu' => 'nullable|integer|min:1|max:6',
        ];
    }

    protected function passedValidation(): void
    {
        $user = Auth::user();

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            $this->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
        }
    }
}
