<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class LaporanPengawasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('view_presensi');
    }

    public function rules(): array
    {
        return [
            'unit_sekolah_id' => 'nullable|integer|exists:unit_sekolah,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'jenis_pegawai' => 'nullable|in:all,guru,non_guru',
            'metric' => 'nullable|in:alpa,hadir_tanpa_jp,jp_kurang,izin_mengajar,kelas_belum',
            'tahun_ajaran' => 'nullable|string|max:20',
            'semester' => 'nullable|integer|in:1,2',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to')));
                if ($days > 31) {
                    $validator->errors()->add('to', 'Rentang laporan maksimal 32 hari.');
                }
            },
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
