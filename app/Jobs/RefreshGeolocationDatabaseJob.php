<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PharData;
use Throwable;

final class RefreshGeolocationDatabaseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return 'refresh-geolite-db';
    }

    public function handle(): void
    {
        /** @var string|null $key */
        $key = config('cas.geolite_license_key');

        if ($key === null || $key === '') {
            Log::info('geolite: GEOLITE_LICENSE_KEY not set; skipping refresh');

            return;
        }

        $tmpDir = storage_path('app/geoip/tmp');
        File::ensureDirectoryExists($tmpDir);

        $tarballPath = $tmpDir.'/GeoLite2-City.tar.gz';

        try {
            $url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key={$key}&suffix=tar.gz";

            Http::sink($tarballPath)->get($url)->throw();

            $phar = new PharData($tarballPath);
            $extractDir = $tmpDir.'/extracted';
            File::ensureDirectoryExists($extractDir);
            $phar->extractTo($extractDir, overwrite: true);

            $mmdbPath = $this->findMmdb($extractDir);

            /** @var string $destPath */
            $destPath = config('cas.geolite_db_path');
            File::ensureDirectoryExists(dirname($destPath));

            rename($mmdbPath, $destPath);

            Log::info('geolite: database refreshed successfully', ['path' => $destPath]);
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('geolite: RefreshGeolocationDatabaseJob failed', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function findMmdb(string $directory): string
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'mmdb') {
                return $file->getRealPath();
            }
        }

        throw new \RuntimeException("No .mmdb file found in extracted tarball under {$directory}");
    }
}
