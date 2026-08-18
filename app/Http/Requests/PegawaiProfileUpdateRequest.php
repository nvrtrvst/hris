<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update data pribadi dari halaman PROFIL (diri sendiri) — setelah onboarding
 * LengkapiData selesai. Semua field `sometimes|nullable`: hanya field yang
 * dikirim yang diperbarui; yang kosong berarti "biarkan apa adanya".
 *
 * Berbeda dari PegawaiSelfUpdateRequest (onboarding) yang mewajibkan semuanya.
 * NIK tidak bisa diedit sendiri di sini (dikelola admin) — bila dikirim,
 * divalidasi format 16 digit.
 */
class PegawaiProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik' => 'sometimes|nullable|string|size:16',
            'nip' => 'sometimes|nullable|string|max:50',
            'nama_lengkap' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'tempat_lahir' => 'sometimes|nullable|string|max:255',
            'tanggal_lahir' => 'sometimes|nullable|date',
            'jenis_kelamin' => 'sometimes|nullable|in:L,P',
            'agama' => 'sometimes|nullable|string|max:255',
            'status_pernikahan' => 'sometimes|nullable|string|max:255',
            'jumlah_tanggungan' => 'sometimes|nullable|integer|min:0',
            'alamat_ktp' => 'sometimes|nullable|string|max:500',
            'alamat_domisili' => 'sometimes|nullable|string|max:500',
            'no_hp' => 'sometimes|nullable|string|max:20',
            'no_hp_darurat' => 'sometimes|nullable|string|max:20',
            'status_kepegawaian' => 'sometimes|nullable|in:tetap,kontrak,honorer,gtt',
            'tanggal_mulai_kerja' => 'sometimes|nullable|date',
            'tanggal_akhir_kontrak' => 'sometimes|nullable|date|after_or_equal:tanggal_mulai_kerja',
            'pendidikan_terakhir' => 'sometimes|nullable|string|max:255',
            'pendidikan_jurusan' => 'sometimes|nullable|string|max:255',
            'nama_bank' => 'sometimes|nullable|string|max:255',
            'no_rekening' => 'sometimes|nullable|string|max:50',
            'npwp' => 'sometimes|nullable|string|max:50',
            'no_bpjs_kesehatan' => 'sometimes|nullable|string|max:50',
            'no_bpjs_ketenagakerjaan' => 'sometimes|nullable|string|max:50',
        ];
    }
}
