<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PresensiPersonalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:hadir,telat,sakit,izin,cuti,alpa',
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
                $validator->errors()->add('end_date', 'Rentang laporan pribadi maksimal 1 tahun.');
            }
        });
    }
}
