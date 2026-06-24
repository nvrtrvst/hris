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

        return Inertia::render('Backup/Index', [
            'databaseName' => env('DB_DATABASE', 'hris_yayasan')
        ]);
    }

    public function download(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'superadmin') {
            abort(403, 'Akses Ditolak.');
        }

        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');

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
