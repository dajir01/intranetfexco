<?php

namespace Tests\Feature;

use App\Http\Controllers\StandController;
use App\Models\Pabellon;
use App\Models\Stand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('pabellones', function (Blueprint $table): void {
            $table->increments('id_pabellon');
            $table->string('nombre_pabellon');
            $table->integer('feria');
        });

        Schema::create('stands', function (Blueprint $table): void {
            $table->bigIncrements('id_stand');
            $table->integer('id_pabellon');
            $table->text('numero_stand');
            $table->float('area_stand');
            $table->float('precio_stand');
            $table->float('sup');
            $table->float('izq');
            $table->text('coord');
            $table->text('lat');
            $table->text('lon');
            $table->integer('tipo');
            $table->integer('anterior');
            $table->integer('feria');
        });
    }

    public function test_it_lists_stands_in_id_order(): void
    {
        $pabellon = $this->createPabellon();
        $first = $this->createStand($pabellon, '20');
        $second = $this->createStand($pabellon, '3');

        $response = (new StandController())->index($pabellon->id_pabellon);
        $standIds = collect($response->getData(true)['data']['stands'])->pluck('id_stand')->all();

        $this->assertSame([$first->id_stand, $second->id_stand], $standIds);
    }

    public function test_it_preserves_the_selected_painted_mode_with_two_points(): void
    {
        $pabellon = $this->createPabellon();
        $stand = $this->createStand($pabellon, '1');

        $request = Request::create('/stands', 'PATCH', [
            'tipo' => 2,
            'coord' => '100,200',
            'sup' => 100,
            'izq' => 200,
        ]);
        $response = (new StandController())->update($request, $pabellon->id_pabellon, $stand->id_stand);

        $this->assertSame(200, $response->getStatusCode());
        $stand->refresh();
        $this->assertSame(2, (int) $stand->tipo);
        $this->assertSame('100,200', $stand->coord);
        $this->assertSame(0.0, (float) $stand->sup);
        $this->assertSame(0.0, (float) $stand->izq);
    }

    public function test_it_clears_painted_coordinates_when_switching_to_reservation_mode(): void
    {
        $pabellon = $this->createPabellon();
        $stand = $this->createStand($pabellon, '1', [
            'tipo' => 2,
            'coord' => '100,200,300,400',
        ]);

        $request = Request::create('/stands', 'PATCH', [
            'tipo' => 1,
            'coord' => '100,200,300,400',
            'sup' => 250,
            'izq' => 350,
        ]);
        $response = (new StandController())->update($request, $pabellon->id_pabellon, $stand->id_stand);

        $this->assertSame(200, $response->getStatusCode());
        $stand->refresh();
        $this->assertSame(1, (int) $stand->tipo);
        $this->assertSame('', $stand->coord);
        $this->assertSame(250.0, (float) $stand->sup);
        $this->assertSame(350.0, (float) $stand->izq);
    }

    private function createPabellon(): Pabellon
    {
        return Pabellon::create([
            'nombre_pabellon' => 'Pabellón de prueba',
            'feria' => 1,
        ]);
    }

    private function createStand(Pabellon $pabellon, string $numero, array $overrides = []): Stand
    {
        return Stand::create(array_merge([
            'id_pabellon' => $pabellon->id_pabellon,
            'numero_stand' => $numero,
            'area_stand' => 10,
            'precio_stand' => 0,
            'sup' => 0,
            'izq' => 0,
            'coord' => '',
            'lat' => '',
            'lon' => '',
            'tipo' => 1,
            'anterior' => 0,
            'feria' => $pabellon->feria,
        ], $overrides));
    }
}
