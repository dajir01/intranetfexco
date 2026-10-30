<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificacionAdminController;
use App\Models\NotificacionAdmin;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificacionAdminControllerTest extends TestCase
{
    private string $previousDatabaseDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('admin_notificaciones', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_usuario');
            $table->string('tipo');
            $table->string('titulo');
            $table->text('mensaje');
            $table->json('payload')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config(['database.default' => $this->previousDatabaseDefault]);

        parent::tearDown();
    }

    public function test_index_returns_latest_unread_notifications_and_the_total_unread_count(): void
    {
        for ($index = 1; $index <= 33; $index++) {
            NotificacionAdmin::query()->create([
                'id_usuario' => 17,
                'tipo' => 'pago_creado',
                'titulo' => 'Pago ' . $index,
                'mensaje' => 'Mensaje ' . $index,
                'leida' => false,
            ]);
        }

        for ($index = 1; $index <= 3; $index++) {
            NotificacionAdmin::query()->create([
                'id_usuario' => 17,
                'tipo' => 'pago_creado',
                'titulo' => 'Leída ' . $index,
                'mensaje' => 'Mensaje leído',
                'leida' => true,
            ]);
        }

        $user = new Usuario;
        $user->setAttribute('id_usuario', 17);
        $request = Request::create('/notificaciones/admin?limit=30', 'GET');
        $request->setUserResolver(static fn () => $user);

        $response = app(NotificacionAdminController::class)->index($request);
        $data = $response->getData(true)['data'];

        $this->assertSame(33, $data['unread']);
        $this->assertCount(30, $data['items']);
        $this->assertSame(33, $data['items'][0]['id']);
        $this->assertSame(4, $data['items'][29]['id']);
        $this->assertSame(
            array_fill(0, 30, false),
            array_column($data['items'], 'leida')
        );
    }
}
