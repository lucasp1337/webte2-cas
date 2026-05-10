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
                // The cli container's PHP-FPM runs as root; Chromium refuses
                // to start as root without --no-sandbox. The container-level
                // hardening (cap_drop ALL, no-new-privileges, tmpfs /tmp,
                // memory + cpu caps in docker-compose.yml) is the load-
                // bearing isolation — keep this trade-off in sync with the
                // cli service's compose entry.
                ->noSandbox()
                // Defence in depth for the rendered Blade. The PDF template
                // is fully server-controlled today (Scramble's spec only),
                // but disabling JS and file:// access means a future Blade
                // contributor accidentally referencing a remote/local URL
                // can't trigger SSRF or local-file disclosure from inside
                // Chromium.
                ->disableJavascript()
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
