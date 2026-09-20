<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_use_the_api_error_contract(): void
    {
        $response = $this->postJson('/api/v1/public/forms/unknown/submissions', []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['codigo', 'mensaje', 'datos', 'errores'])
            ->assertJsonPath('codigo', 422)
            ->assertJsonPath('mensaje', 'Los datos enviados no son válidos. Revisa los campos indicados.')
            ->assertJsonPath('datos', null);
    }

    public function test_missing_resources_use_a_user_friendly_message(): void
    {
        $response = $this->getJson('/api/v1/public/forms/unknown');

        $response->assertNotFound()
            ->assertJson(['codigo' => 404, 'mensaje' => 'Formulario no disponible.', 'datos' => null]);
    }

    public function test_unknown_api_routes_do_not_expose_framework_details(): void
    {
        $response = $this->getJson('/api/v1/resource-that-does-not-exist');

        $response->assertNotFound()
            ->assertJsonStructure(['codigo', 'mensaje', 'datos'])
            ->assertJsonPath('codigo', 404)
            ->assertJsonPath('datos', null);
    }
}
