<?php

namespace App\Services\Imports;

use App\Models\Import;
use App\Models\Order;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoogleSheetsImporter
{
    public function run(Import $import): Import
    {
        $import->update(['status' => 'running', 'started_at' => now()]);

        try {
            $rows = $this->readRows($import->spreadsheet_id, $import->range);
            $errors = [];
            $inserted = $duplicated = $invalid = $failed = 0;

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                try {
                    $data = $this->mapRow($row, $rowNumber, $import);
                    if ($data === null) {
                        $invalid++;
                        $errors[] = ['row' => $rowNumber, 'message' => 'Faltan campos obligatorios.'];

                        continue;
                    }

                    $exists = Order::withTrashed()->where([
                        'campaign_id' => $import->campaign_id,
                        'external_source' => 'google_sheets',
                        'external_key' => $data['external_key'],
                    ])->exists();

                    if ($exists) {
                        $duplicated++;

                        continue;
                    }

                    DB::transaction(fn () => Order::create($data));
                    $inserted++;
                } catch (\Throwable $exception) {
                    $failed++;
                    if (count($errors) < 100) {
                        $errors[] = ['row' => $rowNumber, 'message' => 'No se pudo procesar la fila.'];
                    }
                }
            }

            $import->update([
                'status' => 'completed', 'total_rows' => count($rows), 'inserted_rows' => $inserted,
                'duplicated_rows' => $duplicated, 'invalid_rows' => $invalid, 'failed_rows' => $failed,
                'errors' => $errors, 'completed_at' => now(),
            ]);

            return $import->refresh();
        } catch (\Throwable $exception) {
            $import->update(['status' => 'failed', 'errors' => [['message' => 'No se pudo leer Google Sheets.']], 'completed_at' => now()]);
            throw new RuntimeException('No se pudo leer Google Sheets.', 0, $exception);
        }
    }

    private function readRows(string $spreadsheetId, string $range): array
    {
        $credentials = config('services.google_sheets.credentials');
        if (! $credentials || ! is_file($credentials)) {
            throw new RuntimeException('La credencial de Google no está configurada.');
        }

        $client = new GoogleClient;
        $client->setAuthConfig($credentials);
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
        $client->setApplicationName(config('app.name'));
        $response = (new Sheets($client))->spreadsheets_values->get($spreadsheetId, $range);

        return $response->getValues() ?: [];
    }

    private function mapRow(array $row, int $rowNumber, Import $import): ?array
    {
        $required = [0, 2, 3, 4, 5, 6, 7, 8];
        foreach ($required as $index) {
            if (! isset($row[$index]) || trim((string) $row[$index]) === '') {
                return null;
            }
        }

        return [
            'campaign_id' => $import->campaign_id, 'branch_id' => $import->branch_id,
            'external_source' => 'google_sheets', 'external_key' => trim((string) $row[0]), 'order_number' => $rowNumber,
            'product_name' => trim((string) $row[2]), 'sender_name' => trim((string) $row[3]), 'sender_phone' => trim((string) $row[4]),
            'recipient_name' => trim((string) $row[5]), 'recipient_phone' => trim((string) $row[6]), 'district' => trim((string) $row[7]),
            'address' => trim((string) $row[8]), 'dedication' => isset($row[10]) ? trim((string) $row[10]) : null,
            'delivery_date' => $this->parseDate($row[9] ?? null), 'delivery_time' => $this->parseTime($row[11] ?? null),
            'imported_at' => now(),
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : Carbon::parse((string) $value)->toDateString();
    }

    private function parseTime(mixed $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : Carbon::parse((string) $value)->format('H:i:s');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
