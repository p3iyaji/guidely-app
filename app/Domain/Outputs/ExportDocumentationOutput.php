<?php

namespace App\Domain\Outputs;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ExportDocumentationOutput
{
    public const HTML_FILENAME = 'document.html';

    public const SIDECAR_FILENAME = 'sidecar.json';

    /**
     * Build a print-ready HTML + JSON sidecar ZIP, encrypt it, and persist it on the private local disk.
     *
     * @return array{file_disk: string, file_path: string, checksum: string, byte_size: int}
     */
    public function handle(DocumentationOutput $output): array
    {
        $output->loadMissing(['confirmer', 'pupil']);

        $html = $this->htmlDocument($output);
        $sidecar = $this->sidecar($output, $html);
        $plaintext = $this->zipArchive($html, $sidecar);
        $checksum = hash('sha256', $plaintext);
        $encrypted = Crypt::encryptString($plaintext);
        $disk = 'local';
        $path = 'documentation-outputs/'.$output->id.'/bundle.zip.enc';

        if (! Storage::disk($disk)->put($path, $encrypted)) {
            throw new RuntimeException('Unable to store the encrypted Documentation Output bundle.');
        }

        return [
            'file_disk' => $disk,
            'file_path' => $path,
            'checksum' => $checksum,
            'byte_size' => strlen($plaintext),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sidecar(DocumentationOutput $output, string $html): array
    {
        $payload = is_array($output->payload) ? $output->payload : [];

        return [
            'type' => $output->type instanceof DocumentationOutputType
                ? $output->type->value
                : (string) $output->type,
            'version' => $output->version,
            'confirmer_user_id' => $output->confirmer_user_id,
            'evidence_ids' => $this->stringIds($payload['evidence_ids'] ?? []),
            'determination_ids' => $this->stringIds($payload['determination_ids'] ?? []),
            'gap_ids' => $this->stringIds($payload['gap_ids'] ?? []),
            'checksum' => hash('sha256', $html),
        ];
    }

    private function htmlDocument(DocumentationOutput $output): string
    {
        $typeLabel = $output->type instanceof DocumentationOutputType
            ? $output->type->label()
            : (string) $output->type;
        $pupil = $output->pupil;
        $pupilName = $pupil === null
            ? 'Pupil'
            : (trim(($pupil->given_name ?? '').' '.($pupil->family_name ?? '')) ?: 'Pupil');
        $confirmerName = $output->confirmer?->name ?? 'Confirmed User';
        $confirmedAt = $output->confirmed_at?->utc()->toIso8601String() ?? '';
        $disclaimer = (string) $output->disclaimer_text;
        $payload = is_array($output->payload) ? $output->payload : [];
        $evidenceIds = $this->stringIds($payload['evidence_ids'] ?? []);
        $determinationIds = $this->stringIds($payload['determination_ids'] ?? []);
        $gapIds = $this->stringIds($payload['gap_ids'] ?? []);

        $purposeBlock = '';

        if (is_string($output->purpose) && trim($output->purpose) !== '') {
            $purposeBlock = '<p><strong>Purpose</strong> '.e($output->purpose).'</p>';
        }

        return '<!DOCTYPE html><html lang="en-GB"><head><meta charset="utf-8">'
            .'<title>'.e($typeLabel).' v'.e((string) $output->version).'</title>'
            .'<style>body{font-family:Georgia,serif;margin:2rem;color:#111}'
            .'h1{font-size:1.5rem}p,li{line-height:1.5}</style></head><body>'
            .'<h1>'.e($typeLabel).' v'.e((string) $output->version).'</h1>'
            .'<p><strong>Disclaimer</strong> '.e($disclaimer).'</p>'
            .'<p><strong>Pupil</strong> '.e($pupilName).'</p>'
            .'<p><strong>Confirmed by</strong> '.e($confirmerName).'</p>'
            .'<p><strong>Confirmed at</strong> '.e($confirmedAt).'</p>'
            .$purposeBlock
            .'<h2>Citations</h2>'
            .'<p><strong>Evidence</strong> '.e($this->idList($evidenceIds)).'</p>'
            .'<p><strong>Determinations</strong> '.e($this->idList($determinationIds)).'</p>'
            .'<p><strong>Gaps</strong> '.e($this->idList($gapIds)).'</p>'
            .'</body></html>';
    }

    private function zipArchive(string $html, array $sidecar): string
    {
        $workingPath = tempnam(sys_get_temp_dir(), 'guidely-doc-zip-');

        if ($workingPath === false) {
            throw new RuntimeException('Unable to create a temporary archive for the Documentation Output.');
        }

        $zip = new ZipArchive;

        if ($zip->open($workingPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($workingPath);

            throw new RuntimeException('Unable to open a ZIP archive for the Documentation Output.');
        }

        if ($zip->addFromString(self::HTML_FILENAME, $html) === false) {
            $zip->close();
            @unlink($workingPath);

            throw new RuntimeException('Unable to add the HTML document to the Documentation Output archive.');
        }

        if ($zip->addFromString(
            self::SIDECAR_FILENAME,
            json_encode($sidecar, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ) === false) {
            $zip->close();
            @unlink($workingPath);

            throw new RuntimeException('Unable to add the sidecar to the Documentation Output archive.');
        }

        if ($zip->close() !== true) {
            @unlink($workingPath);

            throw new RuntimeException('Unable to close the Documentation Output archive.');
        }

        $plaintext = file_get_contents($workingPath);
        @unlink($workingPath);

        if ($plaintext === false) {
            throw new RuntimeException('Unable to read the Documentation Output archive.');
        }

        return $plaintext;
    }

    /**
     * @return list<string>
     */
    private function stringIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            if (! is_string($id) || $id === '') {
                continue;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $ids
     */
    private function idList(array $ids): string
    {
        return $ids === [] ? 'None' : implode(', ', $ids);
    }
}
