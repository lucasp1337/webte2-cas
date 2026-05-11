<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Services\Pdf\Exceptions\PdfRenderingException;

interface PdfRenderer
{
    /**
     * Render `html` to a PDF saved at `outputPath`. Header/footer HTML use
     * Chromium's pageNumber/totalPages spans for footer page numbering.
     *
     * @throws PdfRenderingException
     */
    public function render(string $html, string $outputPath, string $headerHtml, string $footerHtml): void;
}
