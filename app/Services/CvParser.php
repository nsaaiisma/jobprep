<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use ZipArchive;

class CvParser
{
    public function extract(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            $text = match ($ext) {
                'pdf' => (new Parser)->parseFile($path)->getText(),
                'docx' => $this->docx($path),
                default => file_get_contents($path),
            };
        } catch (\Throwable $e) {
            logger()->warning('CV parse failed.', ['ext' => $ext, 'message' => $e->getMessage()]);

            return '';
        }

        if (! is_string($text)) {
            return '';
        }

        // Batasi 8000 karakter agar request AI cepat (input besar = lambat + boros kuota).
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 8000);
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        $xml = str_replace('</w:p>', "\n", $xml);

        return html_entity_decode(strip_tags($xml));
    }
}
