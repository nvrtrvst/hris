<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\DbDumper;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        // Otorisasi via route middleware 'can:manage_master_data' (superadmin).
        return inertia('Backup/Index');
    }

    public function download(Request $request)
    {
        // Otorisasi via route middleware 'can:manage_master_data' (superadmin).
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        $fileName = 'backup_'.now()->format('Y_m_d_H_i_s').'.sql';
        $directory = storage_path('app/backups');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.'/'.$fileName;

        try {
            $dumper = $this->makeDumper($connection, $config);
            $dumper->dumpToFile($path);

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return back()->withErrors(['backup' => 'Gagal melakukan backup: '.$e->getMessage()]);
        }
    }

    private function makeDumper(string $connection, array $config): DbDumper
    {
        if ($connection === 'sqlite') {
            return Sqlite::create()
                ->setDbName($config['database']);
        }

        $dumper = MySql::create()
            ->setDbName($config['database'])
            ->setUserName($config['username'])
            ->setHost($config['host'] ?? '127.0.0.1')
            ->setPort($config['port'] ?? '3306');

        if (! empty($config['password'])) {
            $dumper->setPassword($config['password']);
        }

        return $dumper;
    }
}
