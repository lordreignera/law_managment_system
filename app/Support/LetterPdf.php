<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\LegalLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;
use Illuminate\Support\Facades\Storage;

class LetterPdf
{
    public function render(LegalLetter $letter, ?string $signature = null): Document
    {
        if ($signature === null && $letter->signature_path) {
            $disk = Storage::disk(StorageUrl::profileDisk());
            if ($disk->exists($letter->signature_path)) {
                $signature = 'data:'.$disk->mimeType($letter->signature_path).';base64,'.base64_encode($disk->get($letter->signature_path));
            }
        }

        $document = Pdf::loadView('modules.letters.pdf.letter', [
            'letter' => $letter,
            'company' => CompanySetting::current(),
            'signatureImage' => $signature,
        ])->setPaper('a4');

        $document->render();
        $dompdf = $document->getDomPDF();
        $dompdf->getCanvas()->page_text(495, 810, '{PAGE_NUM} / {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.33, 0.39, 0.45]);

        return $document;
    }

    public function filename(LegalLetter $letter): string
    {
        return str($letter->reference_no ?: 'letter')->replaceMatches('/[^a-zA-Z0-9_-]/', '-')->lower().'.pdf';
    }
}
