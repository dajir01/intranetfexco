<?php

namespace Tests\Unit;

use App\Models\Contrato;
use App\Models\Empresa;
use App\Services\EmpresaNitResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmpresaNitResolverTest extends TestCase
{
    private string $previousDatabaseDefault;
    private mixed $previousSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        $this->previousSqliteDatabase = config('database.connections.sqlite.database');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id_empresa');
            $table->string('nombre_empresa');
            $table->string('nit')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('web')->nullable();
            $table->string('pais')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('nombre_responsable')->nullable();
            $table->string('telefono_responsable')->nullable();
            $table->string('email_representante')->nullable();
            $table->string('nombre_gerente')->nullable();
            $table->string('ci_gerente')->nullable();
            $table->string('exp_ci_gerente')->nullable();
            $table->string('fono_gerente')->nullable();
            $table->string('cargo_gerente')->nullable();
            $table->string('rubro')->nullable();
            $table->string('subrubro')->nullable();
            $table->string('otro_rubro')->nullable();
            $table->string('cluster')->nullable();
            $table->string('aniversario')->nullable();
            $table->string('nr_escritura')->nullable();
            $table->string('fecha_nr_escritura')->nullable();
            $table->string('matricula')->nullable();
            $table->string('nr_poder')->nullable();
            $table->string('nr_notaria')->nullable();
            $table->string('fecha_nr_poder')->nullable();
            $table->string('distrito')->nullable();
            $table->string('id_tipo_representante')->nullable();
            $table->dateTime('fecha_creacion')->nullable();
            $table->dateTime('fecha_actualizacion_empresa')->nullable();
            $table->string('pendiente_llenado')->nullable();
            $table->timestamps();
        });

        Schema::create('contrato', function (Blueprint $table): void {
            $table->increments('id_contrato');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_feria');
            $table->dateTime('fecha_creacion_reserva')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);
        Config::set('database.connections.sqlite.database', $this->previousSqliteDatabase);

        parent::tearDown();
    }

    public function test_lookup_prefers_valid_update_date_and_returns_only_form_data(): void
    {
        $older = $this->createCompany('Ficha antigua', '123-456', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        $newer = $this->createCompany('Ficha actualizada', '123456', '2024-01-01 10:00:00', '2025-05-01 10:00:00');
        DB::table('contrato')->insert([
            ['id_empresa' => $older, 'id_feria' => 1, 'fecha_creacion_reserva' => '2025-01-01 10:00:00'],
            ['id_empresa' => $newer, 'id_feria' => 1, 'fecha_creacion_reserva' => '2025-06-01 10:00:00'],
        ]);

        $result = (new EmpresaNitResolver())->lookup('123456');

        $this->assertSame($newer, $result['empresa']['id_empresa']);
        $this->assertFalse($result['requiere_revision']);
        $this->assertArrayNotHasKey('fecha_creacion', $result['empresa']);
        $this->assertArrayNotHasKey('fecha_actualizacion_empresa', $result['empresa']);
        $this->assertArrayNotHasKey('ferias', $result);
        $this->assertArrayNotHasKey('cantidad_contratos', $result);
    }

    public function test_lookup_requires_review_for_equal_date_candidates(): void
    {
        $date = '2025-01-01 10:00:00';
        $this->createCompany('Ficha uno', '123456', $date, $date);
        $this->createCompany('Ficha dos', '123456', $date, $date);

        $result = (new EmpresaNitResolver())->lookup('123456');

        $this->assertTrue($result['requiere_revision']);
        $this->assertArrayNotHasKey('empresa', $result);
    }

    public function test_lookup_normalizes_all_whitespace_allowed_in_nit_values(): void
    {
        $this->createCompany('Ficha con separadores', "123\t\n\v\f\r 456", '2025-01-01 10:00:00', '2025-02-01 10:00:00');

        $result = (new EmpresaNitResolver())->lookup('123456');

        $this->assertSame('Ficha con separadores', $result['empresa']['nombre_empresa']);
    }

    public function test_lookup_uses_creation_date_when_update_dates_are_invalid(): void
    {
        $olderId = $this->createCompany('Ficha antigua', '123456', '2025-01-01 10:00:00', '2035-01-01 10:00:00');
        $newerId = $this->createCompany('Ficha reciente', '123456', '2025-05-01 10:00:00', '2035-01-01 10:00:00');

        $result = (new EmpresaNitResolver())->lookup('123456');

        $this->assertSame($newerId, $result['empresa']['id_empresa']);
        $this->assertNotSame($olderId, $result['empresa']['id_empresa']);
    }

    public function test_contract_switches_only_to_the_confirmed_company_id(): void
    {
        $previousId = $this->createCompany('Provisional', '', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        $targetId = $this->createCompany('Empresa correcta', '987654', '2025-01-01 10:00:00', '2025-02-01 10:00:00');
        $contractId = DB::table('contrato')->insertGetId([
            'id_empresa' => $previousId,
            'id_feria' => 1,
        ]);
        $contract = Contrato::query()->findOrFail($contractId);

        DB::beginTransaction();
        $result = (new EmpresaNitResolver())->resolveForContract(
            $contract,
            Request::create('/', 'POST', ['nit' => '987654', 'id_empresa_destino' => $targetId])
        );
        DB::commit();

        $this->assertSame($targetId, (int) $result['empresa']->id_empresa);
        $this->assertSame($previousId, $result['empresa_anterior_id']);
        $this->assertSame($targetId, (int) DB::table('contrato')->where('id_contrato', $contractId)->value('id_empresa'));
    }

    public function test_unmatched_nit_is_assigned_to_current_company_and_can_be_found_in_normalized_format(): void
    {
        $currentId = $this->createCompany('Provisional', '', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        $contractId = DB::table('contrato')->insertGetId([
            'id_empresa' => $currentId,
            'id_feria' => 1,
        ]);
        $contract = Contrato::query()->findOrFail($contractId);

        DB::beginTransaction();
        $result = (new EmpresaNitResolver())->resolveForContract(
            $contract,
            Request::create('/', 'POST', ['nit' => '987654321', 'id_empresa_destino' => null])
        );
        $result['empresa']->nit = '987-654-321';
        $result['empresa']->save();
        DB::commit();

        $this->assertSame($currentId, (int) $result['empresa']->id_empresa);
        $this->assertSame('987-654-321', DB::table('empresas')->where('id_empresa', $currentId)->value('nit'));
        $this->assertTrue((new EmpresaNitResolver())->existsByNit('987654321'));
    }

    public function test_second_contract_cannot_assign_a_nit_created_by_another_transaction(): void
    {
        $firstCompanyId = $this->createCompany('Provisional uno', '', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        $secondCompanyId = $this->createCompany('Provisional dos', '', '2025-02-01 10:00:00', '2025-02-01 10:00:00');
        $firstContractId = DB::table('contrato')->insertGetId([
            'id_empresa' => $firstCompanyId,
            'id_feria' => 1,
        ]);
        $secondContractId = DB::table('contrato')->insertGetId([
            'id_empresa' => $secondCompanyId,
            'id_feria' => 1,
        ]);
        $resolver = new EmpresaNitResolver();

        DB::beginTransaction();
        $firstResolution = $resolver->resolveForContract(
            Contrato::query()->findOrFail($firstContractId),
            Request::create('/', 'POST', ['nit' => '987654321'])
        );
        $firstResolution['empresa']->nit = '987654321';
        $firstResolution['empresa']->save();
        DB::commit();

        DB::beginTransaction();
        try {
            $resolver->resolveForContract(
                Contrato::query()->findOrFail($secondContractId),
                Request::create('/', 'POST', ['nit' => '987654321'])
            );
            $this->fail('El segundo contrato no debe poder reutilizar el NIT sin confirmarlo.');
        } catch (ValidationException $exception) {
            DB::rollBack();
            $this->assertArrayHasKey('nit', $exception->errors());
        }

        $this->assertSame(1, DB::table('empresas')->where('nit', '987654321')->count());
    }

    public function test_existing_nit_is_still_confirmed_when_already_linked_to_the_contract(): void
    {
        $companyId = $this->createCompany('Empresa ya vinculada', '12312312312', '2025-01-01 10:00:00', '2025-02-01 10:00:00');
        $contractId = DB::table('contrato')->insertGetId([
            'id_empresa' => $companyId,
            'id_feria' => 1,
        ]);
        $contract = Contrato::query()->findOrFail($contractId);

        DB::beginTransaction();
        $result = (new EmpresaNitResolver())->resolveForContract(
            $contract,
            Request::create('/', 'POST', ['nit' => '12312312312'])
        );
        DB::commit();

        $this->assertSame($companyId, (int) $result['empresa']->id_empresa);
        $this->assertNull($result['empresa_anterior_id']);
    }

    public function test_provisional_company_is_deleted_only_when_orphaned(): void
    {
        $orphanId = $this->createCompany('Provisional', ' ', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        DB::table('empresas')->where('id_empresa', $orphanId)->update([
            'pendiente_llenado' => EmpresaNitResolver::provisionalMarker(),
        ]);

        $this->assertTrue((new EmpresaNitResolver())->deleteProvisionalIfOrphaned($orphanId));
        $this->assertNull(Empresa::query()->find($orphanId));

        $referencedId = $this->createCompany('Provisional referenciada', ' ', '2025-01-01 10:00:00', '2025-01-01 10:00:00');
        DB::table('empresas')->where('id_empresa', $referencedId)->update([
            'pendiente_llenado' => EmpresaNitResolver::provisionalMarker(),
        ]);
        DB::table('contrato')->insert(['id_empresa' => $referencedId, 'id_feria' => 1]);

        $this->assertFalse((new EmpresaNitResolver())->deleteProvisionalIfOrphaned($referencedId));
        $this->assertNotNull(Empresa::query()->find($referencedId));
    }

    private function createCompany(string $name, string $nit, string $createdAt, string $updatedAt): int
    {
        return (int) DB::table('empresas')->insertGetId([
            'nombre_empresa' => $name,
            'nit' => $nit,
            'fecha_creacion' => $createdAt,
            'fecha_actualizacion_empresa' => $updatedAt,
            'pendiente_llenado' => '',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ], 'id_empresa');
    }
}
