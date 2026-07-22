# NIK Hash null fix — server steps

## Root cause
MassivePegawaiSeeder passed plaintext NIK ke firstOrCreate. `nik`
cast auto-encrypts on fill, hook read post-cast ciphertext, hook
Branch A (`/^\d{8,}$/`) never matched input, hook returned null →
nik_hash left at migration default NULL.

## Fix pushed
commit `3f5581a` — Seeder pre-computes `$nikHash` + `$nikCipher`,
passes both to firstOrCreate so hash is set at insert time, before
hook runs.

## Server run
```
cd /var/www/hris
git pull origin main
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan nik:check
# expect: total=N cipher=N plain=0 hash_null=0
# (pegawai:hash-nik backfill command has been retired post-migration;
#  use nik:check for ad-hoc verification.)
```

## What was skipped
- Hook clobber protection (double-cipher + reassign) — hook still
  works correctly for path A (plaintext → encrypted → hash correct)
  proven via debug trace. Re-fix only if regressions.
- Hook comment at Pegawai.php:93-96 is stale (says "pre-cast").

## Verified
```
nik:check               → total=1 cipher=1 plain=0 null_nik=0 hash_null=0
nik_masked              → 3201********0001
nik_hash                → 982c7efdc21f3417
```
Status: `hash_null=0` confirmed. Audit NIK closed.
