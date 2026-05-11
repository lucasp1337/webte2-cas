<?php

declare(strict_types=1);

use App\Jobs\RefreshGeolocationDatabaseJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

describe('RefreshGeolocationDatabaseJob', function (): void {
    it('implements ShouldQueue', function (): void {
        expect(RefreshGeolocationDatabaseJob::class)->toImplement(ShouldQueue::class);
    });

    it('implements ShouldBeUnique to prevent concurrent refreshes', function (): void {
        expect(RefreshGeolocationDatabaseJob::class)->toImplement(ShouldBeUnique::class);
    });

    it('returns a stable unique ID', function (): void {
        $job = new RefreshGeolocationDatabaseJob;
        expect($job->uniqueId())->toBe('refresh-geolite-db');
    });

    it('is a no-op and makes no HTTP call when license key is empty', function (): void {
        config(['cas.geolite_license_key' => null]);

        Http::fake(function (): never {
            throw new RuntimeException('HTTP call should not be made when license key is absent');
        });

        Log::shouldReceive('info')
            ->once()
            ->with('geolite: GEOLITE_LICENSE_KEY not set; skipping refresh');

        $job = new RefreshGeolocationDatabaseJob;
        $job->handle();

        // Assert that Http::fake received no calls (we'd have gotten an exception otherwise).
        Http::assertNothingSent();
    });

    it('is a no-op when license key is an empty string', function (): void {
        config(['cas.geolite_license_key' => '']);

        Http::fake(function (): never {
            throw new RuntimeException('HTTP call should not be made when license key is empty string');
        });

        Log::shouldReceive('info')
            ->once()
            ->with('geolite: GEOLITE_LICENSE_KEY not set; skipping refresh');

        $job = new RefreshGeolocationDatabaseJob;
        $job->handle();

        Http::assertNothingSent();
    });

    it('downloads and extracts the mmdb when license key is set', function (): void {
        // Build a minimal tar.gz in memory that contains a fake .mmdb file.
        $tmpDir = sys_get_temp_dir().'/geolite-test-'.uniqid();
        mkdir($tmpDir, 0755, true);

        $sourceDir = $tmpDir.'/GeoLite2-City_20260101';
        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceDir.'/GeoLite2-City.mmdb', 'STUB');

        $tarball = $tmpDir.'/test.tar.gz';
        $phar = new PharData($tmpDir.'/test.tar');
        $phar->buildFromDirectory($tmpDir);
        $phar->compress(Phar::GZ, '.tar.gz');
        // PharData names the compressed file test.tar.gz already.

        $tarballContents = (string) file_get_contents($tarball);

        Http::fake([
            '*' => Http::response($tarballContents, 200),
        ]);

        Log::shouldReceive('info')->withAnyArgs();

        $destPath = $tmpDir.'/result/GeoLite2-City.mmdb';
        config([
            'cas.geolite_license_key' => 'test-key-abc',
            'cas.geolite_db_path' => $destPath,
        ]);

        $job = new RefreshGeolocationDatabaseJob;
        $job->handle();

        expect(file_exists($destPath))->toBeTrue()
            ->and(file_get_contents($destPath))->toBe('STUB');

        // Cleanup.
        File::deleteDirectory($tmpDir);
    });

    it('logs an error and invokes failed() on download failure', function (): void {
        config(['cas.geolite_license_key' => 'test-key-abc']);

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('geolite: RefreshGeolocationDatabaseJob failed', Mockery::type('array'));

        $job = new RefreshGeolocationDatabaseJob;

        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);
        }
    });

    it('logs an error when the tarball contains no mmdb file', function (): void {
        // Build a tar.gz with no .mmdb file inside.
        $tmpDir = sys_get_temp_dir().'/geolite-empty-'.uniqid();
        mkdir($tmpDir, 0755, true);

        $innerDir = $tmpDir.'/GeoLite2-City_20260101';
        mkdir($innerDir, 0755, true);
        file_put_contents($innerDir.'/README.txt', 'no mmdb here');

        $phar = new PharData($tmpDir.'/empty.tar');
        $phar->buildFromDirectory($tmpDir);
        $phar->compress(Phar::GZ, '.tar.gz');

        $tarballContents = (string) file_get_contents($tmpDir.'/empty.tar.gz');

        Http::fake([
            '*' => Http::response($tarballContents, 200),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('geolite: RefreshGeolocationDatabaseJob failed', Mockery::type('array'));

        $destPath = $tmpDir.'/result/GeoLite2-City.mmdb';
        config([
            'cas.geolite_license_key' => 'test-key-abc',
            'cas.geolite_db_path' => $destPath,
        ]);

        $job = new RefreshGeolocationDatabaseJob;

        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);
        }

        File::deleteDirectory($tmpDir);
    });
});
