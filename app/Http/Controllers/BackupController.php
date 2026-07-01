<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\DbDumper\Databases\MySql;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'superadmin') {
            abort(403, 'Akses Ditolak. Hanya Super Admin yang dapat mengakses halaman ini.');
        }

        // [FIX] Tidak mengirim nama database ke browser (information disclosure)
        return Inertia::render('Backup/Index');
    }

    public function download(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'superadmin') {
            abort(403, 'Akses Ditolak.');
        }

        // [FIX] Gunakan config() bukan env() — env() return null setelah config:cache
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = config('database.connections.mysql.port', '3306');

        $fileName = 'backup_' . $dbName . '_' . now()->format('Y_m_d_H_i_s') . '.sql';
        $directory = storage_path('app/backups');
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . '/' . $fileName;

        try {
            $dumper = MySql::create()
                ->setDbName($dbName)
                ->setUserName($dbUser)
                ->setHost($dbHost)
                ->setPort($dbPort);

            if (!empty($dbPass)) {
                $dumper->setPassword($dbPass);
            }

            $dumper->dumpToFile($path);

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->withErrors(['backup' => 'Gagal melakukan backup: ' . $e->getMessage()]);
        }
    }
}
