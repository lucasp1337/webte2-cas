<?php

declare(strict_types=1);

use App\Jobs\GenerateApiDocsPdfJob;
use App\Services\Pdf\Exceptions\PdfRenderingException;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\Testing\FakePdfRenderer;
use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    app()->bind(PdfRenderer::class, FakePdfRenderer::class);
});

it('transitions cache state from running to done and writes the pdf file', function (): void {
    $exportId = Str::ulid()->toBase32();

    $job = new GenerateApiDocsPdfJob($exportId, 'en');
    $job->handle(app(PdfRenderer::class), app(Generator::class));

    $expectedPath = storage_path("app/exports/{$exportId}.pdf");

    expect(file_exists($expectedPath))->toBeTrue();

    /** @var array{status: string, locale: string, path: string} $state */
    $state = Cache::get("pdf_export:{$exportId}");

    expect($state['status'])->toBe('done')
        ->and($state['locale'])->toBe('en')
        ->and($state['path'])->toBe($expectedPath);

    // Cleanup
    if (file_exists($expectedPath)) {
        unlink($expectedPath);
    }
});

it('restores the app locale after running', function (): void {
    app()->setLocale('en');

    $exportId = Str::ulid()->toBase32();

    $job = new GenerateApiDocsPdfJob($exportId, 'sk');
    $job->handle(app(PdfRenderer::class), app(Generator::class));

    expect(app()->getLocale())->toBe('en');

    $path = storage_path("app/exports/{$exportId}.pdf");

    if (file_exists($path)) {
        unlink($path);
    }
});

it('sets cache to running before rendering', function (): void {
    $exportId = Str::ulid()->toBase32();

    /** @var FakePdfRenderer $fake */
    $fake = app(PdfRenderer::class);

    // We need to check intermediate state — use a wrapper that captures it mid-render.
    $capturingRenderer = new class($fake, $exportId) implements PdfRenderer
    {
        private string $capturedStatus = '';

        public function __construct(
            private readonly FakePdfRenderer $inner,
            private readonly string $exportId,
        ) {}

        public function render(string $html, string $outputPath, string $headerHtml, string $footerHtml): void
        {
            /** @var array{status: string}|null $state */
            $state = Cache::get("pdf_export:{$this->exportId}");
            $this->capturedStatus = $state['status'] ?? '';
            $this->inner->render($html, $outputPath, $headerHtml, $footerHtml);
        }

        public function getCapturedStatus(): string
        {
            return $this->capturedStatus;
        }
    };

    app()->bind(PdfRenderer::class, static fn () => $capturingRenderer);

    $job = new GenerateApiDocsPdfJob($exportId, 'en');
    $job->handle(app(PdfRenderer::class), app(Generator::class));

    expect($capturingRenderer->getCapturedStatus())->toBe('running');

    $path = storage_path("app/exports/{$exportId}.pdf");

    if (file_exists($path)) {
        unlink($path);
    }
});

it('failed() handler marks cache state as failed with the error message', function (): void {
    $exportId = Str::ulid()->toBase32();

    Cache::put("pdf_export:{$exportId}", [
        'status' => 'running',
        'locale' => 'en',
    ], now()->addHour());

    $job = new GenerateApiDocsPdfJob($exportId, 'en');
    $job->failed(new RuntimeException('Chromium crashed.'));

    /** @var array{status: string, error: string} $state */
    $state = Cache::get("pdf_export:{$exportId}");

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('Chromium crashed.');
});

it('calls the renderer with the expected output path', function (): void {
    $exportId = Str::ulid()->toBase32();

    /** @var FakePdfRenderer $fake */
    $fake = app(PdfRenderer::class);

    $job = new GenerateApiDocsPdfJob($exportId, 'en');
    $job->handle($fake, app(Generator::class));

    expect($fake->calls)->toHaveCount(1);

    $call = $fake->calls[0];

    expect($call['outputPath'])->toBe(storage_path("app/exports/{$exportId}.pdf"));

    $path = storage_path("app/exports/{$exportId}.pdf");

    if (file_exists($path)) {
        unlink($path);
    }
});

it('marks cache as failed when the renderer throws', function (): void {
    /** @var FakePdfRenderer $fake */
    $fake = app(PdfRenderer::class);
    $fake->failNext(new PdfRenderingException('render error'));

    $exportId = Str::ulid()->toBase32();

    $job = new GenerateApiDocsPdfJob($exportId, 'en');

    try {
        $job->handle($fake, app(Generator::class));
    } catch (PdfRenderingException) {
        // Expected — let failed() fire it manually
    }

    $job->failed(new PdfRenderingException('render error'));

    /** @var array{status: string, error: string} $state */
    $state = Cache::get("pdf_export:{$exportId}");

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toBe('render error');
});
