<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PegawaiSelfUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik' => 'required|string|size:16',
            'nuptk' => 'nullable|string|max:50',
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'jumlah_tanggungan' => 'required|integer|min:0',
            'alamat' => 'required|string|max:500',
            'no_hp' => 'required|string|max:20',
            'no_hp_darurat' => 'nullable|string|max:20',
            'status_kepegawaian' => 'required|in:tetap,kontrak,honorer,gtt',
            'tmt_mengajar' => 'required|date',
            'tanggal_akhir_kontrak' => 'nullable|date|after_or_equal:tmt_mengajar',
            'pendidikan_terakhir' => 'required|string|max:255',
            'pendidikan_jurusan' => 'nullable|string|max:255',
            'pendidikan_tahun_lulus' => 'nullable|integer|min:1950|max:2100',
            'pendidikan_asal_sekolah' => 'nullable|string|max:255',
            'sk_nomor' => 'nullable|string|max:100',
            'sk_tanggal' => 'nullable|date',
            'nama_bank' => 'required|string|max:255',
            'no_rekening' => 'required|string|max:50',
            'npwp' => 'nullable|string|max:50',
            'no_bpjs_kesehatan' => 'nullable|string|max:50',
            'no_bpjs_ketenagakerjaan' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
