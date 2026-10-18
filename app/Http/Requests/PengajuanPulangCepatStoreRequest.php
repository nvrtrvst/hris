<?php

namespace App\Http\Requests;

use App\Helpers\PayrollLockHelper;
use App\Models\Pegawai;
use App\Models\PengajuanPulangCepat;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class PengajuanPulangCepatStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'after_or_equal:today'],
            'jam_diminta' => ['required', 'date_format:H:i'],
            'alasan' => ['required', 'in:sakit,urusan_keluarga,keperluan_pribadi,dinas_luar,lainnya'],
            'alasan_detail' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $pegawai = Pegawai::where('user_id', Auth::id())->first();
            if (! $pegawai) {
                abort(403, 'Akses ditolak.');
            }

            $pending = PengajuanPulangCepat::where('pegawai_id', $pegawai->id)
                ->where('tanggal', $this->input('tanggal'))
                ->where('status', 'pending')
                ->exists();
            if ($pending) {
                $validator->errors()->add('tanggal', 'Sudah ada pengajuan pulang cepat yang menunggu persetujuan untuk tanggal ini.');
            }

            if (PayrollLockHelper::isPeriodLocked($pegawai->id, Carbon::parse($this->input('tanggal')))) {
                $validator->errors()->add('tanggal', 'Periode payroll sudah terkunci. Hubungi HR.');
            }
        });
    }
}
