<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Services\Pdf\Exceptions\PdfRenderingException;
use Spatie\Browsershot\Browsershot;
use Throwable;

final readonly class BrowsershotPdfRenderer implements PdfRenderer
{
    public function __construct(private string $chromePath) {}

    /**
     * @throws PdfRenderingException
     */
    public function render(string $html, string $outputPath, string $headerHtml, string $footerHtml): void
    {
        try {
            Browsershot::html($html)
                ->setChromePath($this->chromePath)
                ->paperSize(210, 297, 'mm')
                ->margins(20, 18, 22, 18, 'mm')
                ->showBrowserHeaderAndFooter()
                ->headerHtml($headerHtml)
                ->footerHtml($footerHtml)
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new PdfRenderingException(
                "Browsershot failed to render PDF: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
