<?php

namespace Tests\Feature;

use App\Http\Controllers\ControlAccesosOperadoresController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ControlAccesosOperadoresControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'controlaccesos.operador_permissions' => [
                'controlaccesos.validar_qr' => 'Validar credenciales QR',
                'controlaccesos.registrar_ingreso' => 'Registrar ingresos',
                'controlaccesos.consultar_ingresos_propios' => 'Consultar ingresos propios',
            ],
        ]);
        DB::purge('sqlite');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->bigIncrements('id_feria');
            $table->string('nombre_feria');
            $table->string('puertas_acceso')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->integer('inicio')->nullable();
        });
        Schema::create('usuarios_control', function (Blueprint $table): void {
            $table->bigIncrements('id_usuario_control');
            $table->string('nombre');
            $table->string('usuario')->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('usuarios_control_permisos', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_usuario_control');
            $table->string('permiso');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['id_usuario_control', 'permiso']);
        });
        Schema::create('usuarios_control_asignaciones', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_usuario_control');
            $table->bigInteger('id_feria');
            $table->unsignedSmallInteger('numero_puerta');
            $table->timestamps();
            $table->unique(['id_usuario_control', 'id_feria', 'numero_puerta']);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_operator_password_permissions_and_event_door_assignment_are_saved(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2;3',
        ], 'id_feria');

        $response = $this->controller()->store(Request::create('/control-accesos/operadores', 'POST', [
            'id_feria' => $eventId,
            'nombre' => 'Operadora de prueba',
            'usuario' => '  OPERADORA_1 ',
            'password' => '12345678',
            'activo' => true,
            'permissions' => ['controlaccesos.validar_qr', 'controlaccesos.registrar_ingreso'],
            'puertas' => [1, 3],
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $operatorId = (int) $response->getData(true)['data']['id_usuario_control'];
        $operator = DB::table('usuarios_control')->where('id_usuario_control', $operatorId)->first();
        $this->assertSame('operadora_1', $operator->usuario);
        $this->assertTrue(Hash::check('12345678', $operator->password));
        $this->assertNotSame('12345678', $operator->password);
        $this->assertSame(
            ['controlaccesos.registrar_ingreso', 'controlaccesos.validar_qr'],
            DB::table('usuarios_control_permisos')->where('id_usuario_control', $operatorId)->orderBy('permiso')->pluck('permiso')->all()
        );
        $this->assertSame(
            [1, 3],
            DB::table('usuarios_control_asignaciones')->where('id_usuario_control', $operatorId)->orderBy('numero_puerta')->pluck('numero_puerta')->all()
        );
    }

    public function test_operator_cannot_be_assigned_to_a_door_not_active_on_the_event(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2;3',
        ], 'id_feria');

        $this->expectException(ValidationException::class);
        $this->controller()->store(Request::create('/control-accesos/operadores', 'POST', [
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_2',
            'id_feria' => $eventId,
            'password' => '1234',
            'permissions' => ['controlaccesos.validar_qr'],
            'puertas' => [4],
        ]));
    }

    public function test_operator_list_does_not_return_password_hashes(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $operatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_3',
            'password' => Hash::make('tercera-clave-segura-123'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuario_control');
        DB::table('usuarios_control_asignaciones')->insert([
            'id_usuario_control' => $operatorId,
            'id_feria' => $eventId,
            'numero_puerta' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->controller()->index(Request::create('/control-accesos/operadores', 'GET', [
            'id_feria' => $eventId,
        ]));
        $data = $response->getData(true)['data'][0];

        $this->assertArrayNotHasKey('password', $data);
        $this->assertSame([2], $data['puertas']);
    }

    public function test_available_doors_are_read_from_the_event_configuration(): void
    {
        DB::table('eventos')->insert([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1; 2;3;3;0;Puerta Norte',
        ]);

        $response = $this->controller()->eventos(Request::create('/control-accesos/operadores/eventos', 'GET'));

        $this->assertSame([1, 2, 3], $response->getData(true)['data'][0]['puertas']);
    }

    public function test_operator_password_cannot_exceed_eight_characters(): void
    {
        $this->expectException(ValidationException::class);
        $this->controller()->store(Request::create('/control-accesos/operadores', 'POST', [
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_4',
            'id_feria' => DB::table('eventos')->insertGetId([
                'nombre_feria' => 'Feria de prueba',
                'puertas_acceso' => '1;2',
            ], 'id_feria'),
            'password' => '123456789',
            'permissions' => ['controlaccesos.validar_qr'],
            'puertas' => [1],
        ]));
    }

    public function test_operator_password_requires_at_least_four_characters(): void
    {
        Log::spy();
        try {
            $this->controller()->store(Request::create('/control-accesos/operadores', 'POST', [
                'nombre' => 'Operador de prueba',
                'usuario' => 'operador_corto',
                'id_feria' => DB::table('eventos')->insertGetId([
                    'nombre_feria' => 'Feria de prueba',
                    'puertas_acceso' => '1;2',
                ], 'id_feria'),
                'password' => '123',
                'permissions' => ['controlaccesos.validar_qr'],
                'puertas' => [1],
            ]));
            $this->fail('Se esperaba el rechazo de la contraseña corta.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Validación rechazada al guardar operador de acceso.'
                && $context['operacion'] === 'crear'
                && $context['errores_validacion']['password'] !== []
                && ! str_contains(json_encode($context), '"password":"123"'),
        );
    }

    public function test_unexpected_save_failure_returns_a_reference_and_logs_the_operation_context(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        Schema::drop('usuarios_control_permisos');
        Log::spy();

        $response = $this->controller()->store(Request::create('/control-accesos/operadores', 'POST', [
            'id_feria' => $eventId,
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_error',
            'password' => '1234',
            'permissions' => ['controlaccesos.validar_qr'],
            'puertas' => [1],
        ]));
        $data = $response->getData(true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString($data['error_id'], $data['message']);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Error inesperado al guardar operador de acceso.'
                && $context['error_id'] === $data['error_id']
                && $context['operacion'] === 'crear'
                && $context['id_feria'] === $eventId
                && $context['excepcion'] !== '',
        );
    }

    public function test_operator_update_rejects_passwords_shorter_than_four_characters(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $operatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_actualizar',
            'password' => Hash::make('1234'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuario_control');

        $this->expectException(ValidationException::class);
        $this->controller()->update(Request::create('/control-accesos/operadores/'.$operatorId, 'PUT', [
            'id_feria' => $eventId,
            'nombre' => 'Operador de prueba',
            'usuario' => 'operador_actualizar',
            'password' => '123',
            'permissions' => ['controlaccesos.validar_qr'],
            'puertas' => [1],
        ]), $operatorId);
    }

    public function test_operator_listing_is_scoped_to_the_selected_event(): void
    {
        $firstEventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Primera feria',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $secondEventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Segunda feria',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $operatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador de dos eventos',
            'usuario' => 'operador_multi',
            'password' => Hash::make('1234'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuario_control');
        DB::table('usuarios_control_asignaciones')->insert([
            [
                'id_usuario_control' => $operatorId,
                'id_feria' => $firstEventId,
                'numero_puerta' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_usuario_control' => $operatorId,
                'id_feria' => $secondEventId,
                'numero_puerta' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->controller()->index(Request::create('/control-accesos/operadores', 'GET', [
            'id_feria' => $secondEventId,
        ]));
        $data = $response->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame([2], $data[0]['puertas']);
    }

    public function test_operator_listing_can_be_sorted_by_allowed_columns(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        foreach (['usuario_z', 'usuario_a'] as $username) {
            $operatorId = DB::table('usuarios_control')->insertGetId([
                'nombre' => 'Operador',
                'usuario' => $username,
                'password' => Hash::make('1234'),
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_usuario_control');
            DB::table('usuarios_control_asignaciones')->insert([
                'id_usuario_control' => $operatorId,
                'id_feria' => $eventId,
                'numero_puerta' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->controller()->index(Request::create('/control-accesos/operadores', 'GET', [
            'id_feria' => $eventId,
            'sort_by' => 'usuario',
            'sort_order' => 'asc',
        ]));

        $this->assertSame(
            ['usuario_a', 'usuario_z'],
            array_column($response->getData(true)['data'], 'usuario')
        );
    }

    public function test_updating_operator_in_event_keeps_other_event_assignments(): void
    {
        $firstEventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Primera feria',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $secondEventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Segunda feria',
            'puertas_acceso' => '1;2',
        ], 'id_feria');
        $operatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador de dos eventos',
            'usuario' => 'operador_multi',
            'password' => Hash::make('1234'),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_usuario_control');
        DB::table('usuarios_control_asignaciones')->insert([
            [
                'id_usuario_control' => $operatorId,
                'id_feria' => $firstEventId,
                'numero_puerta' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_usuario_control' => $operatorId,
                'id_feria' => $secondEventId,
                'numero_puerta' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->controller()->update(Request::create('/control-accesos/operadores/'.$operatorId, 'PUT', [
            'id_feria' => $firstEventId,
            'nombre' => 'Operador actualizado',
            'usuario' => 'operador_multi',
            'activo' => true,
            'permissions' => ['controlaccesos.validar_qr'],
            'puertas' => [2],
        ]), $operatorId);

        $assignments = DB::table('usuarios_control_asignaciones')
            ->where('id_usuario_control', $operatorId)
            ->orderBy('id_feria')
            ->get(['id_feria', 'numero_puerta'])
            ->map(fn (object $assignment): array => [(int) $assignment->id_feria, (int) $assignment->numero_puerta])
            ->all();

        $this->assertSame([[$firstEventId, 2], [$secondEventId, 2]], $assignments);
    }

    private function controller(): ControlAccesosOperadoresController
    {
        return app(ControlAccesosOperadoresController::class);
    }
}
