<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Pdf\PdfRenderer;
use Dedoc\Scramble\Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateApiDocsPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $exportId,
        public readonly string $locale,
    ) {}

    public function uniqueId(): string
    {
        return $this->exportId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(PdfRenderer $renderer, Generator $scrambleGenerator): void
    {
        $previous = App::getLocale();

        try {
            App::setLocale($this->locale);

            Cache::put("pdf_export:{$this->exportId}", [
                'status' => 'running',
                'locale' => $this->locale,
            ], now()->addHours(24));

            /** @var array<string, mixed> $spec */
            $spec = $scrambleGenerator();

            $html = view('pdf.api-docs', ['spec' => $spec])->render();
            $headerHtml = view('pdf.partials.header')->render();
            $footerHtml = view('pdf.partials.footer')->render();

            $dir = storage_path('app/exports');

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $outputPath = "{$dir}/{$this->exportId}.pdf";

            $renderer->render($html, $outputPath, $headerHtml, $footerHtml);

            Cache::put("pdf_export:{$this->exportId}", [
                'status' => 'done',
                'locale' => $this->locale,
                'path' => $outputPath,
            ], now()->addHours(24));
        } finally {
            App::setLocale($previous);
        }
    }

    public function failed(Throwable $exception): void
    {
        Cache::put("pdf_export:{$this->exportId}", [
            'status' => 'failed',
            'locale' => $this->locale,
            'error' => $exception->getMessage(),
        ], now()->addHours(24));

        Log::error('api-docs pdf job failed', [
            'export_id' => $this->exportId,
            'locale' => $this->locale,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
