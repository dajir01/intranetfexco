<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReglamentoFeriaUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->unsignedInteger('id_feria')->primary();
        });
        Schema::create('reglamentos_feria', function (Blueprint $table): void {
            $table->bigIncrements('id_reglamento');
            $table->unsignedInteger('id_feria');
            $table->string('nombre_reglamento');
            $table->text('descripcion');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->timestamps();
        });

        DB::table('eventos')->insert(['id_feria' => 42]);
        Storage::fake('private');
    }

    public function test_it_stores_regulations_in_the_shared_private_directory(): void
    {
        $response = $this->withoutMiddleware()->post('/ferias/42/reglamentos', [
            'nombre_reglamento' => 'Reglamento de prueba',
            'descripcion' => 'Descripción de prueba',
            'archivo' => UploadedFile::fake()->createWithContent('reglamento.pdf', "%PDF-1.4\ncontenido"),
        ]);

        $response->assertCreated();

        $path = DB::table('reglamentos_feria')->value('ruta_archivo');

        $this->assertSame('reglamentos-feria', dirname($path));
        Storage::disk('private')->assertExists($path);
    }
}
