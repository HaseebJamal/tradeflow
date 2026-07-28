<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use LogicException;

class ThermalDocumentService
{
    public function width(Request $request): int
    {
        return $request->query('paper') === '58' ? 58 : 80;
    }

    public function dompdfPaper(int $width, ?float $heightMm = null): array
    {
        $pointsPerMillimetre = 72 / 25.4;

        return [
            0,
            0,
            $width * $pointsPerMillimetre,
            ($heightMm ?? $this->minimumPaperHeight($width)) * $pointsPerMillimetre,
        ];
    }

    public function loadPdf(string $view, array $data, int $width)
    {
        $html = view($view, $data)->render();

        if (! str_contains($html, 'tf-thermal-document') || ! str_contains($html, 'tf-thermal-document__items')) {
            throw new LogicException('The thermal document could not be rendered.');
        }

        if (! str_contains(strtolower($html), '<body')) {
            $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';
        }

        $height = $this->estimatePaperHeight($html, $width);
        $maximumHeight = 1500.0;

        do {
            $pdf = Pdf::loadHtml($html)->setPaper($this->dompdfPaper($width, $height));
            $pdf->render();

            if ($pdf->getDomPDF()->getCanvas()->get_page_count() <= 1 || $height >= $maximumHeight) {
                return $pdf;
            }

            // DomPDF can move a final wrapped total/footer line to page two. Only
            // extend the paper when its completed layout proves that it overflowed.
            $height = min($height + ($width === 58 ? 18 : 15), $maximumHeight);
        } while (true);
    }

    /**
     * DomPDF requires an explicit page height for custom thermal paper. Estimate
     * it from the rendered shared document instead of using an A4-length roll.
     */
    private function estimatePaperHeight(string $html, int $width): float
    {
        $itemCount = max(1, $this->countElementsWithClass($html, 'tf-thermal-document__item'));
        $rowCount = $this->countElementsWithClass($html, 'tf-thermal-document__row');
        $footerLineCount = $this->footerLineCount($html);

        $isNarrowPaper = $width === 58;
        $height = ($isNarrowPaper ? 46 : 40) // header, separators, page margins and safe bottom buffer
            + ($itemCount * ($isNarrowPaper ? 15 : 12))
            + ($rowCount * ($isNarrowPaper ? 7 : 6))
            + ($footerLineCount * ($isNarrowPaper ? 6 : 5));

        // Keep a short receipt printable while avoiding clipped wrapped content.
        return min(max($height, $this->minimumPaperHeight($width)), 1500.0);
    }

    private function minimumPaperHeight(int $width): float
    {
        return $width === 58 ? 80.0 : 75.0;
    }

    private function countElementsWithClass(string $html, string $class): int
    {
        preg_match_all(
            '/<(?:div|span)\\b[^>]*\\bclass\\s*=\\s*(["\\\'])[^"\\\']*(?<![A-Za-z0-9_-])'.preg_quote($class, '/').'(?![A-Za-z0-9_-])[^"\\\']*\\1[^>]*>/i',
            $html,
            $matches
        );

        return count($matches[0]);
    }

    private function footerLineCount(string $html): int
    {
        if (! preg_match('/<footer\\b[^>]*\\bclass\\s*=\\s*(["\\\'])[^"\\\']*\\btf-document-footer\\b[^"\\\']*\\1[^>]*>(.*?)<\\/footer>/is', $html, $footer)) {
            return 0;
        }

        return preg_match_all('/<div\\b/i', $footer[2]);
    }
}
