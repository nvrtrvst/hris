<?php

namespace App\Events;

use App\Models\PengajuanIzin;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IzinBaruEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $data;

    public function __construct(PengajuanIzin $pengajuan)
    {
        $this->data = [
            'id' => $pengajuan->id,
            'pegawai_nama' => $pengajuan->pegawai?->nama_lengkap ?? 'Pegawai',
            'jenis_izin' => $pengajuan->jenis_izin,
            'message' => 'Pengajuan izin baru: '.$pengajuan->jenis_izin,
        ];
    }

    /**
     * Broadcast ke channel approver L1 (user id).
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->data['id'] && $pengajuan = PengajuanIzin::find($this->data['id'])) {
            if ($pengajuan->approver_l1_id) {
                $channels[] = new Channel('App.Models.User.'.$pengajuan->approver_l1_id);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'izin-baru';
    }
}
