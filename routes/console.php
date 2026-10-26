<?php

use Illuminate\Support\Facades\Schedule;

// crontab produksi menjalankan `schedule:run` tiap menit (lihat deploy/deploy.sh).
Schedule::command('approval:remind')->hourly();
Schedule::command('approval:escalate')->everyTenMinutes();
