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
            'nip' => 'nullable|string|max:50',
            'nama_lengkap' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'agama' => 'required|string|max:255',
            'status_pernikahan' => 'required|string|max:255',
            'jumlah_tanggungan' => 'required|integer|min:0',
            'alamat_ktp' => 'required|string|max:500',
            'alamat_domisili' => 'nullable|string|max:500',
            'no_hp' => 'required|string|max:20',
            'no_hp_darurat' => 'nullable|string|max:20',
            'status_kepegawaian' => 'required|in:tetap,kontrak,honorer,gtt',
            'tanggal_mulai_kerja' => 'required|date',
            'tanggal_akhir_kontrak' => 'nullable|date|after_or_equal:tanggal_mulai_kerja',
            'pendidikan_terakhir' => 'required|string|max:255',
            'pendidikan_jurusan' => 'nullable|string|max:255',
            'nama_bank' => 'required|string|max:255',
            'no_rekening' => 'required|string|max:50',
            'npwp' => 'nullable|string|max:50',
            'no_bpjs_kesehatan' => 'nullable|string|max:50',
            'no_bpjs_ketenagakerjaan' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
