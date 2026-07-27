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

    public function dompdfPaper(int $width): array
    {
        $pointsPerMillimetre = 72 / 25.4;

        // A standard thermal roll page height lets DomPDF continue long documents
        // onto additional pages without falling back to an A4-width layout.
        return [0, 0, $width * $pointsPerMillimetre, 297 * $pointsPerMillimetre];
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

        return Pdf::loadHtml($html)->setPaper($this->dompdfPaper($width));
    }
}
