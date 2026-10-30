<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PresensiGuruReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('view_presensi');
    }

    public function rules(): array
    {
        return [
            'unit_sekolah_id' => 'required|integer|exists:unit_sekolah,id',
            'pegawai_id' => 'required|integer|exists:pegawai,id',
            'periode' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|integer|in:1,2',
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
