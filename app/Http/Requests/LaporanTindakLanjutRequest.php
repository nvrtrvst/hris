<?php

namespace App\Http\Requests;

use App\Models\LaporanTindakLanjut;
use App\Services\LaporanTindakLanjutAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaporanTindakLanjutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(LaporanTindakLanjutAccess::class)->canManage($this->user());
    }

    public function rules(): array
    {
        return [
            'finding_key' => ['required', 'string', 'size:64'],
            'unit_sekolah_id' => ['required', 'integer', 'exists:unit_sekolah,id'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'version' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(LaporanTindakLanjut::STATUSES)],
            'penanggung_jawab_id' => ['nullable', 'required_if:status,diproses,selesai', 'integer', 'exists:users,id'],
            'catatan' => ['nullable', 'required_if:status,selesai', 'string', 'max:2000'],
            'hasil' => ['nullable', 'required_if:status,selesai', Rule::in(LaporanTindakLanjut::RESULTS)],
        ];
    }
}
