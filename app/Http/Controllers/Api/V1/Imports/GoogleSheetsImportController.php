<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Imports\StoreGoogleSheetsImportRequest;
use App\Http\Resources\Api\V1\ImportResource;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Import;
use App\Models\User;
use App\Services\Imports\GoogleSheetsImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Importaciones', description: 'Importación de pedidos desde Google Sheets')]
class GoogleSheetsImportController extends Controller
{
    #[OA\Post(path: '/api/v1/imports/google-sheets', operationId: 'importGoogleSheets', tags: ['Importaciones'], summary: 'Importar pedidos desde Google Sheets', responses: [new OA\Response(response: 201, description: 'Importación procesada'), new OA\Response(response: 502, description: 'Error de conexión con Google')])]
    public function store(StoreGoogleSheetsImportRequest $request, GoogleSheetsImporter $importer): JsonResponse
    {
        $data = $request->validated();
        $campaign = Campaign::findOrFail($data['campaign_id']);
        $branch = Branch::findOrFail($data['branch_id']);
        $this->ensureScope($request->user(), $campaign, $branch);
        abort_if($campaign->status !== 'open', 422, 'La campaña debe estar abierta para importar pedidos.');

        $import = Import::create([
            'campaign_id' => $campaign->id, 'branch_id' => $branch->id, 'requested_by' => $request->user()->id,
            'source' => 'google_sheets', 'spreadsheet_id' => $data['spreadsheet_id'], 'range' => $data['range'],
        ]);

        try {
            $import = $importer->run($import);
        } catch (\Throwable) {
            return response()->json(['codigo' => 502, 'mensaje' => 'No se pudo leer la fuente de Google Sheets.', 'datos' => new ImportResource($import->refresh())], 502);
        }

        return response()->json(['codigo' => 201, 'mensaje' => 'Importación procesada.', 'datos' => new ImportResource($import)], 201);
    }

    #[OA\Get(path: '/api/v1/imports/{import}', operationId: 'showImport', tags: ['Importaciones'], summary: 'Consultar importación', responses: [new OA\Response(response: 200, description: 'Importación obtenida')])]
    public function show(Request $request, int $import): JsonResponse
    {
        $model = Import::with(['campaign', 'branch'])->findOrFail($import);
        $this->ensureScope($request->user(), $model->campaign, $model->branch);
        return response()->json(['codigo' => 200, 'mensaje' => 'Importación obtenida.', 'datos' => new ImportResource($model)]);
    }

    private function ensureScope(User $actor, Campaign $campaign, Branch $branch): void
    {
        abort_unless($campaign->branches()->whereKey($branch->id)->exists(), 422, 'La sucursal no pertenece a la campaña.');
        if (! $actor->hasRole('super_admin')) abort_unless($campaign->users()->whereKey($actor->id)->exists() && $branch->users()->whereKey($actor->id)->exists(), 403, 'La importación está fuera de tu ámbito.');
    }
}
