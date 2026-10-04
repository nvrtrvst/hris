<?php

namespace App\Http\Requests;

use App\Helpers\PayrollLockHelper;
use App\Models\Pegawai;
use App\Models\PengajuanKoreksi;
use App\Models\Presensi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class PengajuanKoreksiStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route mobile auth:web_mobile → Auth::id() user pegawai bersangkutan.
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'presensi_id' => ['required', 'integer', 'exists:presensi,id'],
            'nilai_baru' => ['required', 'date_format:H:i'],
            'alasan' => ['required', 'in:lupa_presensi,hp_rusak,dinas_luar,rapat,lainnya'],
            'alasan_detail' => ['nullable', 'string', 'max:500'],
            'penjelasan_khusus' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Cross-check server-side (tidak percaya FE): kepemilikan presensi,
     * urutan jam, payroll lock, duplikat pending, kuota bulanan.
     */
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

            $presensi = Presensi::find($this->input('presensi_id'));
            if (! $presensi) {
                return; // sudah ditangani rule exists
            }

            if ($presensi->pegawai_id !== $pegawai->id) {
                abort(403, 'Akses ditolak.');
            }

            if (! $presensi->jam_masuk) {
                $validator->errors()->add('presensi_id', 'Presensi belum memiliki jam masuk.');

                return;
            }

            if (strnatcmp(substr((string) $this->input('nilai_baru'), 0, 5), substr($presensi->jam_masuk, 0, 5)) <= 0) {
                $validator->errors()->add('nilai_baru', 'Jam keluar baru harus setelah jam masuk ('.$presensi->jam_masuk.').');

                return;
            }

            if (PayrollLockHelper::isPeriodLocked($presensi->pegawai_id, $presensi->tanggal)) {
                $validator->errors()->add('presensi_id', 'Periode payroll sudah terkunci. Hubungi HR.');

                return;
            }

            $pending = PengajuanKoreksi::where('presensi_id', $presensi->id)
                ->where('status', 'pending')
                ->exists();
            if ($pending) {
                $validator->errors()->add('presensi_id', 'Sudah ada pengajuan koreksi yang menunggu persetujuan untuk presensi ini.');

                return;
            }

            // Kuota 3 disetujui/bulan: lewat kuota tetap boleh, penjelasan wajib.
            if (PengajuanKoreksi::kuotaTerpakai($pegawai->id) >= PengajuanKoreksi::KUOTA_BULANAN
                && ! $this->filled('penjelasan_khusus')) {
                $validator->errors()->add('penjelasan_khusus', 'Melebihi kuota 3 koreksi/bulan — penjelasan khusus wajib diisi.');
            }
        });
    }
}
