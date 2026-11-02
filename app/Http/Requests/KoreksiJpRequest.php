<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KoreksiJpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('koreksi_presensi') ?? false;
    }

    public function rules(): array
    {
        return [
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_keluar' => ['required', 'date_format:H:i', 'after_or_equal:jam_masuk'],
            'status' => ['required', 'in:hadir,telat'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
