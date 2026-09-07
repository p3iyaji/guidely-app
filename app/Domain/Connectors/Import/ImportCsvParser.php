<?php

namespace App\Domain\Connectors\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Parse Import Template CSV headers and data rows (including Evidence column detection).
 */
class ImportCsvParser
{
    /**
     * @var list<string>
     */
    public const KNOWN_HEADERS = [
        'pupil_identifier',
        'mis_key',
        'given_name',
        'family_name',
        'date_of_birth',
        'school_name',
        'school_id',
        'year_group',
        'sen_status',
        'send_status',
        'notes',
    ];

    /**
     * Headers that indicate Evidence import intent (including unsupported aliases).
     *
     * @var list<string>
     */
    public const EVIDENCE_HEADER_MARKERS = [
        'evidence',
        'evidence_body',
        'evidence_date',
        'evidence_type',
        'evidence_notes',
        'historical_evidence',
        'evidence_provision_code',
        'evidence_occurred_at',
        'evidence_external_id',
        'evidence_provision',
        'evidence_provision_label',
    ];

    /**
     * @return array{
     *     headers: list<string>,
     *     evidence_headers: list<string>,
     *     rows: array<int, list<string|null>>
     * }
     */
    public function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file could not be read.',
            ]);
        }

        try {
            $lineNumber = 0;
            $headerRow = fgetcsv($handle);
            $lineNumber++;

            if ($headerRow === false || $headerRow === [null] || $this->rowIsEmpty($headerRow)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty or missing a header row.',
                ]);
            }

            if ($this->isSepPreamble($headerRow)) {
                $headerRow = fgetcsv($handle);
                $lineNumber++;

                if ($headerRow === false || $headerRow === [null] || $this->rowIsEmpty($headerRow)) {
                    throw ValidationException::withMessages([
                        'file' => 'The CSV file is empty or missing a header row.',
                    ]);
                }
            }

            $headers = array_map(
                fn ($header): string => $this->normalizeHeaderCell((string) $header),
                $headerRow,
            );

            if ($headers === [] || $this->rowIsEmpty($headers)) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty or missing a header row.',
                ]);
            }

            $nonEmptyHeaders = array_values(array_filter(
                $headers,
                fn (string $header): bool => $header !== '',
            ));

            if (count($nonEmptyHeaders) !== count(array_unique($nonEmptyHeaders))) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file has duplicate column headers.',
                ]);
            }

            $evidenceHeaders = array_values(array_filter(
                $headers,
                fn (string $header): bool => $this->isEvidenceHeader($header),
            ));

            $rows = [];
            $rowNumber = $lineNumber;

            while (($cells = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->rowIsEmpty($cells)) {
                    continue;
                }

                $rows[$rowNumber] = $cells;
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file has no data rows.',
                ]);
            }

            return [
                'headers' => $headers,
                'evidence_headers' => $evidenceHeaders,
                'rows' => $rows,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $cells
     * @return array<string, string|null>
     */
    public function associateRow(array $headers, array $cells): array
    {
        $associative = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = $cells[$index] ?? null;
            $associative[$header] = is_string($value)
                ? Str::of($value)->trim()->toString()
                : null;

            if ($associative[$header] === '') {
                $associative[$header] = null;
            }
        }

        return $associative;
    }

    public function isEvidenceHeader(string $header): bool
    {
        if (in_array($header, self::KNOWN_HEADERS, true)) {
            return false;
        }

        if (in_array($header, self::EVIDENCE_HEADER_MARKERS, true)) {
            return true;
        }

        return Str::startsWith($header, 'evidence_') || Str::startsWith($header, 'evidence ');
    }

    public function normalizeHeaderCell(string $header): string
    {
        $withoutBom = preg_replace('/^\xEF\xBB\xBF|\x{FEFF}/u', '', $header) ?? $header;

        return Str::of($withoutBom)->trim()->lower()->toString();
    }

    /**
     * @param  list<string|null>  $cells
     */
    public function isSepPreamble(array $cells): bool
    {
        $first = isset($cells[0]) ? $this->normalizeHeaderCell((string) $cells[0]) : '';

        return Str::startsWith($first, 'sep=');
    }

    /**
     * @param  list<string|null>  $cells
     */
    public function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (is_string($cell) && trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
