<?php

namespace Tests\Feature;

use App\Http\Controllers\CredencialPinController;
use App\Services\CredentialPhotoStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CredencialPinRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->bigIncrements('id_feria');
            $table->date('fecha_fin')->nullable();
        });
        Schema::create('credencial_pines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_feria');
            $table->string('pin_hash');
            $table->string('estado');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_failed_pin_attempts_are_limited_across_both_public_endpoints_per_event(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'fecha_fin' => now()->addDay()->toDateString(),
        ], 'id_feria');
        $key = 'autoacreditacion-pin-fallos-feria-'.$eventId;
        RateLimiter::clear($key);

        $controller = app(CredencialPinController::class);
        $blocked = false;
        $validationFailures = 0;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $parameters = [
                'id_feria' => $eventId,
                'pin' => 'ABC234',
            ];

            try {
                if ($attempt % 2 === 0) {
                    $controller->validarPublico(Request::create('/api/autoacreditacion/validar-pin', 'POST', $parameters));
                } else {
                    $request = Request::create(
                        '/api/autoacreditacion/registrar',
                        'POST',
                        $parameters + [
                            'ci' => '123456',
                            'tipo_documento' => 'CI',
                            'nombre' => 'Persona de prueba',
                        ],
                        [],
                        ['foto' => UploadedFile::fake()->image('persona.png')],
                    );
                    $controller->registrarPublico($request, new CredentialPhotoStorage);
                }
            } catch (HttpException $exception) {
                $this->assertSame(429, $exception->getStatusCode());
                $blocked = true;
                break;
            } catch (ValidationException) {
                $validationFailures++;
            }
        }

        $this->assertTrue($blocked);
        $this->assertSame(99, $validationFailures);
        $this->assertSame(100, RateLimiter::attempts($key));
    }
}
