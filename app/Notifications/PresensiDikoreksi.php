<?php

namespace App\Notifications;

use App\Models\Presensi;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PresensiDikoreksi extends Notification
{
    public function __construct(
        public Presensi $presensi,
        public string $statusLama,
        public string $alasan,
        public ?User $admin = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $presensi = $this->presensi;
        $jadwal = $presensi->jadwal;
        $mapel = $jadwal?->pegawaiMapel?->mataPelajaran?->nama ?? $jadwal?->kelas_label ?? 'JP';
        $tanggal = $presensi->tanggal?->format('d M Y') ?? '-';
        $jam = substr((string) $jadwal?->jam_mulai, 0, 5).'-'.substr((string) $jadwal?->jam_selesai, 0, 5);
        $statusLama = $this->statusLama === 'belum_presensi' ? 'Belum presensi' : ucfirst($this->statusLama);

        return (new MailMessage)
            ->subject('Presensi Mengajar Dikoreksi')
            ->greeting('Yth. '.($notifiable->name ?? $presensi->pegawai?->nama_lengkap ?? 'Pegawai').',')
            ->line('Presensi mengajar Anda telah dikoreksi oleh admin.')
            ->line("Tanggal: {$tanggal}")
            ->line("JP: {$mapel} ({$jam})")
            ->line('Status: '.$statusLama.' -> '.ucfirst((string) $presensi->status))
            ->line('Jam masuk: '.substr((string) $presensi->jam_masuk, 0, 5))
            ->line('Jam keluar: '.substr((string) $presensi->jam_keluar, 0, 5))
            ->line("Alasan: {$this->alasan}")
            ->line('Admin: '.($this->admin?->name ?? '-'))
            ->action('Lihat Riwayat Presensi', route('presensi.riwayat'))
            ->salutation('Hormat kami, Tim HR Yayasan');
    }
}
