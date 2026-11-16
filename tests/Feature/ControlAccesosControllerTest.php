<?php

namespace Tests\Feature;

use App\Http\Controllers\ControlAccesosController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ControlAccesosControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->bigIncrements('id_feria');
            $table->string('nombre_feria');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->integer('inicio')->nullable();
        });
        Schema::create('expositores', function (Blueprint $table): void {
            $table->increments('id_exp');
            $table->string('nombre');
            $table->string('ci')->nullable();
        });
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id_empresa');
            $table->string('nombre_empresa');
        });
        Schema::create('credenciales', function (Blueprint $table): void {
            $table->increments('id_credencial');
            $table->integer('id_expositor');
            $table->integer('id_empresa')->nullable();
        });
        Schema::create('usuarios_control', function (Blueprint $table): void {
            $table->bigIncrements('id_usuario_control');
            $table->string('nombre');
            $table->string('usuario');
        });
        Schema::create('usuarios_control_asignaciones', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_usuario_control');
            $table->bigInteger('id_feria');
            $table->unsignedSmallInteger('numero_puerta');
        });
        Schema::create('credencial_ingresos', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('id_credencial');
            $table->bigInteger('id_feria');
            $table->unsignedBigInteger('id_usuario_control')->nullable();
            $table->unsignedBigInteger('id_qr_token')->nullable();
            $table->unsignedTinyInteger('tipo_credencial');
            $table->unsignedTinyInteger('modalidad_credencial');
            $table->dateTime('fecha_ingreso');
            $table->string('idempotency_key', 80)->unique();
            $table->string('dispositivo_ref', 100)->nullable();
            $table->string('punto_acceso', 150)->nullable();
            $table->unsignedSmallInteger('numero_puerta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_summary_counts_reentries_as_accesses_but_counts_distinct_credentials(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_feria' => 'Feria de prueba',
            'fecha_inicio' => '2026-11-06',
            'fecha_fin' => '2026-11-08',
        ], 'id_feria');
        $this->insertAccess($eventId, 17, '2026-11-06 09:00:00');
        $this->insertAccess($eventId, 17, '2026-11-06 11:00:00');
        $this->insertAccess($eventId, 18, '2026-11-06 12:00:00');
        $this->insertAccess($eventId, 18, '2026-11-06 23:59:59');
        $this->insertAccess($eventId, 19, '2026-11-07 00:00:00');

        $response = $this->controller()->resumen(Request::create('/control-accesos/resumen', 'GET', [
            'id_feria' => $eventId,
            'desde' => '2026-11-06',
            'hasta' => '2026-11-06',
        ]))->getData(true);

        $this->assertSame(4, $response['data']['ingresos']);
        $this->assertSame(2, $response['data']['credenciales_unicas']);
        $this->assertSame(4, $response['data']['por_credencial'][0]['ingresos']);
        $this->assertSame(2, $response['data']['por_credencial'][0]['credenciales_unicas']);
        $this->assertSame('Expositor', $response['data']['por_credencial'][0]['tipo_credencial']);
        $this->assertSame('Feria de prueba', DB::table('eventos')->where('id_feria', $eventId)->value('nombre_feria'));
    }

    public function test_summary_filters_by_event_and_counts_credentials(): void
    {
        $eventA = DB::table('eventos')->insertGetId(['nombre_feria' => 'Evento A'], 'id_feria');
        $eventB = DB::table('eventos')->insertGetId(['nombre_feria' => 'Evento B'], 'id_feria');
        $this->insertAccess($eventA, 21, '2026-11-06 09:00:00', 2, 1);
        $this->insertAccess($eventB, 22, '2026-11-06 10:00:00', 3, 0);

        $response = $this->controller()->resumen(Request::create('/control-accesos/resumen', 'GET', [
            'id_feria' => $eventB,
        ]))->getData(true);

        $this->assertSame(1, $response['data']['ingresos']);
        $this->assertSame(1, $response['data']['credenciales_unicas']);
        $this->assertSame('Oficial', $response['data']['por_credencial'][0]['tipo_credencial']);
        $this->assertSame('Física', $response['data']['por_credencial'][0]['modalidad_credencial']);
        $this->assertArrayNotHasKey('por_tipo_evento', $response['data']);
    }

    public function test_summary_filters_by_credential_type(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');
        $this->insertAccess($eventId, 21, '2026-11-06 09:00:00', 2);
        $this->insertAccess($eventId, 22, '2026-11-06 10:00:00', 3);

        $response = $this->controller()->resumen(Request::create('/control-accesos/resumen', 'GET', [
            'id_feria' => $eventId,
            'tipo_credencial' => 2,
        ]))->getData(true);

        $this->assertSame(1, $response['data']['ingresos']);
        $this->assertSame(1, $response['data']['credenciales_unicas']);
        $this->assertCount(1, $response['data']['por_credencial']);
        $this->assertSame('Prensa', $response['data']['por_credencial'][0]['tipo_credencial']);
    }

    public function test_summary_groups_daily_accesses_by_credential_type(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');
        $this->insertAccess($eventId, 21, '2026-11-06 09:00:00', 1);
        $this->insertAccess($eventId, 21, '2026-11-06 10:00:00', 1);
        $this->insertAccess($eventId, 22, '2026-11-06 11:00:00', 2);
        $this->insertAccess($eventId, 23, '2026-11-07 09:00:00', 2);

        $response = $this->controller()->resumen(Request::create('/control-accesos/resumen', 'GET', [
            'id_feria' => $eventId,
        ]))->getData(true);

        $this->assertSame([
            [
                'fecha' => '2026-11-06',
                'tipo_credencial' => 'Expositor',
                'tipo_credencial_codigo' => 1,
                'ingresos' => 2,
            ],
            [
                'fecha' => '2026-11-06',
                'tipo_credencial' => 'Prensa',
                'tipo_credencial_codigo' => 2,
                'ingresos' => 1,
            ],
            [
                'fecha' => '2026-11-07',
                'tipo_credencial' => 'Prensa',
                'tipo_credencial_codigo' => 2,
                'ingresos' => 1,
            ],
        ], $response['data']['ingresos_por_dia']);
    }

    public function test_summary_and_records_can_be_filtered_by_operator(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');
        $firstOperatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador Uno',
            'usuario' => 'operador_uno',
        ], 'id_usuario_control');
        $secondOperatorId = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador Dos',
            'usuario' => 'operador_dos',
        ], 'id_usuario_control');
        $this->insertAccess($eventId, 21, '2026-11-06 09:00:00', 1, 1, $firstOperatorId);
        $this->insertAccess($eventId, 21, '2026-11-06 10:00:00', 1, 1, $firstOperatorId);
        $this->insertAccess($eventId, 22, '2026-11-06 11:00:00', 2, 0, $secondOperatorId);

        $summary = $this->controller()->resumen(Request::create('/control-accesos/resumen', 'GET', [
            'id_feria' => $eventId,
            'id_usuario_control' => $firstOperatorId,
        ]))->getData(true);
        $records = $this->controller()->registros(Request::create('/control-accesos/registros', 'GET', [
            'id_feria' => $eventId,
            'id_usuario_control' => $firstOperatorId,
        ]))->getData(true);

        $this->assertSame(2, $summary['data']['ingresos']);
        $this->assertSame(1, $summary['data']['credenciales_unicas']);
        $this->assertSame(2, $records['data']['total']);
        $this->assertSame('Operador Uno', $records['data']['data'][0]['nombre_operador']);
        $this->assertSame('operador_uno', $records['data']['data'][0]['usuario_operador']);
    }

    public function test_operator_filter_options_only_include_operators_assigned_to_selected_event(): void
    {
        $eventA = DB::table('eventos')->insertGetId(['nombre_feria' => 'Evento A'], 'id_feria');
        $eventB = DB::table('eventos')->insertGetId(['nombre_feria' => 'Evento B'], 'id_feria');
        $operatorB = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador B',
            'usuario' => 'operador_b',
        ], 'id_usuario_control');
        $operatorA = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Operador A',
            'usuario' => 'operador_a',
        ], 'id_usuario_control');
        $unassignedOperator = DB::table('usuarios_control')->insertGetId([
            'nombre' => 'Sin asignación',
            'usuario' => 'sin_asignacion',
        ], 'id_usuario_control');
        DB::table('usuarios_control_asignaciones')->insert([
            ['id_usuario_control' => $operatorB, 'id_feria' => $eventA, 'numero_puerta' => 1],
            ['id_usuario_control' => $operatorA, 'id_feria' => $eventA, 'numero_puerta' => 2],
            ['id_usuario_control' => $operatorA, 'id_feria' => $eventA, 'numero_puerta' => 3],
            ['id_usuario_control' => $operatorB, 'id_feria' => $eventB, 'numero_puerta' => 1],
        ]);

        $response = $this->controller()->operadores(Request::create('/control-accesos/operadores-filtro', 'GET', [
            'id_feria' => $eventA,
        ]))->getData(true);
        $otherEventResponse = $this->controller()->operadores(Request::create('/control-accesos/operadores-filtro', 'GET', [
            'id_feria' => $eventB,
        ]))->getData(true);

        $this->assertSame(['Operador A', 'Operador B'], array_column($response['data'], 'nombre'));
        $this->assertCount(2, array_unique(array_column($response['data'], 'id_usuario_control')));
        $this->assertSame(['Operador B'], array_column($otherEventResponse['data'], 'nombre'));
        $this->assertNotContains($unassignedOperator, array_column($response['data'], 'id_usuario_control'));
    }

    public function test_records_list_includes_each_reentry_and_person_name(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');
        DB::table('expositores')->insert([
            'id_exp' => 17,
            'nombre' => 'Expositor Uno',
            'ci' => '12345678',
        ]);
        DB::table('credenciales')->insert(['id_credencial' => 21, 'id_expositor' => 17]);
        $this->insertAccess($eventId, 21, '2026-11-06 09:00:00');
        $this->insertAccess($eventId, 21, '2026-11-06 10:00:00');
        $this->insertAccess($eventId, 21, '2026-11-06 11:00:00', 2);

        $response = $this->controller()->registros(Request::create('/control-accesos/registros', 'GET', [
            'id_feria' => $eventId,
        ]))->getData(true);

        $this->assertSame(3, $response['data']['total']);
        $this->assertCount(3, $response['data']['data']);
        $this->assertSame('Expositor Uno', $response['data']['data'][0]['nombre_persona']);
        $this->assertSame('12345678', $response['data']['data'][0]['ci_persona']);
        $this->assertArrayNotHasKey('id_credencial', $response['data']['data'][0]);
        $this->assertArrayNotHasKey('id_feria', $response['data']['data'][0]);
        $this->assertArrayNotHasKey('nombre_feria', $response['data']['data'][0]);

        $searchedResponse = $this->controller()->registros(Request::create('/control-accesos/registros', 'GET', [
            'id_feria' => $eventId,
            'search' => '12345678',
        ]))->getData(true);

        $this->assertSame(3, $searchedResponse['data']['total']);

        $filteredResponse = $this->controller()->registros(Request::create('/control-accesos/registros', 'GET', [
            'id_feria' => $eventId,
            'tipo_credencial' => 2,
        ]))->getData(true);

        $this->assertSame(1, $filteredResponse['data']['total']);
        $this->assertSame('Prensa', $filteredResponse['data']['data'][0]['tipo_credencial']);
    }

    public function test_report_endpoints_require_an_event_filter(): void
    {
        $controller = $this->controller();
        $requests = [
            'resumen' => fn () => $controller->resumen(Request::create('/control-accesos/resumen', 'GET')),
            'registros' => fn () => $controller->registros(Request::create('/control-accesos/registros', 'GET')),
            'exportar' => fn () => $controller->exportar(Request::create('/control-accesos/exportar', 'GET')),
        ];

        foreach ($requests as $endpoint => $request) {
            try {
                $request();
                $this->fail("El endpoint {$endpoint} debía exigir id_feria.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('id_feria', $exception->errors());
            }
        }
    }

    public function test_records_reject_unbounded_page_size(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');

        try {
            $this->controller()->registros(Request::create('/control-accesos/registros', 'GET', [
                'id_feria' => $eventId,
                'per_page' => -1,
            ]));
            $this->fail('El endpoint debía rechazar per_page=-1.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('per_page', $exception->errors());
        }
    }

    public function test_excel_export_has_filtered_daily_summary_and_complete_access_details(): void
    {
        $eventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Feria de prueba'], 'id_feria');
        $otherEventId = DB::table('eventos')->insertGetId(['nombre_feria' => 'Otra feria'], 'id_feria');
        $companyId = DB::table('empresas')->insertGetId(['nombre_empresa' => 'Empresa de prueba'], 'id_empresa');
        DB::table('expositores')->insert([
            'id_exp' => 17,
            'nombre' => 'Expositor Uno',
            'ci' => '00123456',
        ]);
        DB::table('credenciales')->insert([
            'id_credencial' => 21,
            'id_expositor' => 17,
            'id_empresa' => $companyId,
        ]);
        $this->insertAccess($eventId, 21, '2026-11-06 09:00:00', 1, 1, null, 2, 'Acceso principal');
        $this->insertAccess($eventId, 21, '2026-11-06 10:00:00', 1, 1, null, null, 'Entrada lateral');
        $this->insertAccess($eventId, 21, '2026-11-06 11:00:00', 2);
        $this->insertAccess($eventId, 21, '2026-11-07 09:00:00');
        $this->insertAccess($otherEventId, 21, '2026-11-06 12:00:00');

        Excel::fake();
        Excel::matchByRegex();
        $this->controller()->exportar(Request::create('/control-accesos/exportar', 'GET', [
            'id_feria' => $eventId,
            'tipo_credencial' => 1,
            'desde' => '2026-11-06',
            'hasta' => '2026-11-06',
            'search' => '00123456',
        ]));

        Excel::assertDownloaded('/^ControlAccesos_feria_de_prueba_\\d{8}_\\d{6}\\.xlsx$/', function ($export): bool {
            $sheets = $export->sheets();
            $this->assertCount(2, $sheets);
            $this->assertSame('Resumen diario', $sheets[0]->title());
            $this->assertSame('Detalle de ingresos', $sheets[1]->title());
            $this->assertSame(['Tipo de credencial', 'Fecha', 'Ingresos'], $sheets[0]->headings());
            $this->assertSame(['Expositor', '2026-11-06', 2], $sheets[0]->collection()->first());
            $this->assertSame(
                ['Fecha', 'Hora', 'Puerta', 'Nombre completo', 'CI', 'Empresa'],
                $sheets[1]->headings()
            );

            $rows = $sheets[1]->query()->get();
            $this->assertCount(2, $rows);
            $this->assertSame([
                '06/11/2026',
                '09:00:00',
                '2',
                'Expositor Uno',
                '00123456',
                'Empresa de prueba',
            ], $sheets[1]->map($rows->first()));
            $this->assertSame('Entrada lateral', $sheets[1]->map($rows->last())[2]);

            return true;
        });
    }

    private function insertAccess(
        int $eventId,
        int $credentialId,
        string $date,
        int $credentialType = 1,
        int $modality = 1,
        ?int $operatorId = null,
        ?int $numeroPuerta = null,
        ?string $puntoAcceso = null
    ): void {
        DB::table('credencial_ingresos')->insert([
            'id_credencial' => $credentialId,
            'id_feria' => $eventId,
            'id_usuario_control' => $operatorId,
            'tipo_credencial' => $credentialType,
            'modalidad_credencial' => $modality,
            'fecha_ingreso' => $date,
            'numero_puerta' => $numeroPuerta,
            'punto_acceso' => $puntoAcceso,
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
    }

    private function controller(): ControlAccesosController
    {
        return app(ControlAccesosController::class);
    }
}
