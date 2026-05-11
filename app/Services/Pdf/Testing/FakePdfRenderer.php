<?php

declare(strict_types=1);

namespace App\Services\Pdf\Testing;

use App\Services\Pdf\Exceptions\PdfRenderingException;
use App\Services\Pdf\PdfRenderer;
use Throwable;

/**
 * In-memory fake for PdfRenderer — swap in via the service container
 * in feature tests to avoid real Chromium calls.
 *
 * Usage:
 *   $this->app->bind(PdfRenderer::class, FakePdfRenderer::class);
 *   $fake = app(PdfRenderer::class);
 */
final class FakePdfRenderer implements PdfRenderer
{
    /** @var array<int, array{html: string, outputPath: string, headerHtml: string, footerHtml: string}> */
    public array $calls = [];

    private ?Throwable $nextException = null;

    public function failNext(Throwable $e): void
    {
        $this->nextException = $e;
    }

    /**
     * @throws PdfRenderingException
     */
    public function render(string $html, string $outputPath, string $headerHtml, string $footerHtml): void
    {
        $this->calls[] = [
            'html' => $html,
            'outputPath' => $outputPath,
            'headerHtml' => $headerHtml,
            'footerHtml' => $footerHtml,
        ];

        if ($this->nextException !== null) {
            $exception = $this->nextException;
            $this->nextException = null;

            throw new PdfRenderingException(
                $exception->getMessage(),
                previous: $exception,
            );
        }

        // Write a placeholder PDF so callers can assert file existence.
        file_put_contents($outputPath, '%PDF-1.4 fake');
    }
}
