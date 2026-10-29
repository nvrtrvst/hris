<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AbsenSiswaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_absen_siswa');
    }

    public function rules(): array
    {
        return [
            'mode' => 'nullable|in:daily,period',
            'tanggal' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'unit_id' => 'nullable|integer|exists:unit_sekolah,id',
            'class_label' => 'nullable|string|max:100',
            'status' => 'nullable|in:semua,hadir,telat,izin,sakit,alpa',
            'search' => 'nullable|string|max:100',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            if ($start->diffInDays($end) > 366) {
                $validator->errors()->add('end_date', 'Rentang laporan maksimal 1 tahun.');
            }
        });
    }
}
