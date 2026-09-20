<?php

namespace App\Console\Commands;

use App\Models\District;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyUbigeo extends Command
{
    protected $signature = 'ubigeo:import
        {source : Ruta al SQL legado que contiene tb_ubigeo}
        {--dry-run : Analiza y muestra resultados sin guardar cambios}';

    protected $description = 'Migra tb_ubigeo al catalogo districts con trazabilidad e ID UBIGEO';

    public function handle(): int
    {
        $source = $this->argument('source');
        if (! is_file($source)) {
            $this->error("No existe el archivo fuente: {$source}");

            return self::FAILURE;
        }

        $rows = $this->parseRows((string) file_get_contents($source));
        if ($rows === []) {
            $this->error('No se encontraron registros de tb_ubigeo.');

            return self::FAILURE;
        }

        $this->info(sprintf('Registros encontrados: %d', count($rows)));
        $this->line('Los codigos se construyen como DDPPDD, usando la secuencia del distrito dentro de su provincia.');

        if ($this->option('dry-run')) {
            $this->table(['UBIGEO', 'Departamento', 'Provincia', 'Distrito'], array_map(
                static fn (array $row): array => [$row['code'], $row['department'], $row['province'], $row['name']],
                array_slice($rows, 0, 10),
            ));
            $this->warn('Dry-run: no se realizaron cambios.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $district = District::withTrashed()->firstOrNew(['code' => $row['code']]);

                if ($district->exists && $district->trashed()) {
                    $district->restore();
                }

                $district->fill($row);
                $district->save();
            }
        });

        $this->info(sprintf('Importacion completada: %d registros procesados.', count($rows)));

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function parseRows(string $sql): array
    {
        $pattern = "/\\((\\d+),\\s*(\\d+),\\s*'((?:[^'\\\\]|\\\\.)*)',\\s*(\\d+),\\s*'((?:[^'\\\\]|\\\\.)*)',\\s*'((?:[^'\\\\]|\\\\.)*)',\\s*'((?:[^'\\\\]|\\\\.)*)',\\s*'([^']*)',\\s*(NULL|'[^']*'),\\s*(\\d+)\\)/s";
        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        $rows = [];
        $provinceCounters = [];
        foreach ($matches as $match) {
            $departmentCode = str_pad($match[2], 2, '0', STR_PAD_LEFT);
            $provinceCode = str_pad($match[4], 4, '0', STR_PAD_LEFT);
            $counterKey = $departmentCode.'-'.$provinceCode;
            $provinceCounters[$counterKey] = ($provinceCounters[$counterKey] ?? 0) + 1;
            $districtNumber = $provinceCounters[$counterKey];

            if ($districtNumber > 99) {
                throw new RuntimeException("La provincia {$provinceCode} excede 99 distritos.");
            }

            $rows[] = [
                'code' => $departmentCode.substr($provinceCode, 2).str_pad((string) $districtNumber, 2, '0', STR_PAD_LEFT),
                'name' => $this->decodeSqlValue($match[6]),
                'province' => $this->decodeSqlValue($match[5]),
                'department' => $this->decodeSqlValue($match[3]),
                'department_code' => $departmentCode,
                'province_code' => $provinceCode,
                'macroregion' => $this->decodeSqlValue($match[7]),
                'is_active' => (bool) $match[9],
            ];
        }

        return $rows;
    }

    private function decodeSqlValue(string $value): string
    {
        return trim(str_replace(["\\'", '\\\\'], ["'", '\\'], $value));
    }
}
