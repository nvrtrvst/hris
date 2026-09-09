<?php

use App\Services\MapelDedupService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Data fix: gabungkan mata_pelajaran duplikat (nama sama case-insensitive).
     * Destructive terhadap row dup — tidak bisa dibalik otomatis (relasi
     * sudah dipindah). Down() tidak mengembalikan apa pun.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return; // DB test selalu bersih — dedup diuji via service test.
        }

        $result = (new MapelDedupService)->dedup();
        if ($result['merged'] > 0) {
            echo "Mapel dedup: {$result['merged']} grup digabung, {$result['deleted']} duplikat dihapus.".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Data fix satu arah — tidak di-rollback.
    }
};
