<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LaporanGenerateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (! $user->can('view_dashboard')) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:presensi,penggajian,lemburan',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'unit_sekolah_id' => 'nullable|exists:unit_sekolah,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Tipe laporan harus salah satu dari: presensi, penggajian, lemburan',
            'start_date.required' => 'Tanggal awal harus diisi',
            'end_date.required' => 'Tanggal akhir harus diisi',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal',
            'unit_sekolah_id.exists' => 'Unit sekolah tidak ditemukan',
        ];
    }

    /**
     * Override unit_sekolah_id untuk admin unit.
     * Jika user adalah admin unit, otomatis set unit_sekolah_id ke unit user tersebut
     * kecuali jika sudah diset secara eksplisit.
     */
    protected function passedValidation(): void
    {
        $user = Auth::user();

        if ($user && $user->unit_sekolah_id && ! $user->can('view_all_units')) {
            // Hanya override jika user belum set unit_sekolah_id secara eksplisit
            if (! $this->has('unit_sekolah_id')) {
                $this->merge(['unit_sekolah_id' => $user->unit_sekolah_id]);
            }
        }
    }
}
