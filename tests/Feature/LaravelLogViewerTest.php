<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaravelLogViewerTest extends TestCase
{
    private $previousDatabaseDefault;

    private string $testLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseDefault = config('database.default');
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nombre_usuario');
            $table->string('nick_usuario')->unique();
            $table->string('email')->nullable();
            $table->string('area')->nullable();
            $table->integer('nivel_usuario')->default(1);
            $table->boolean('jefatura')->default(false);
            $table->boolean('estado')->default(true);
            $table->string('pass_usuario')->nullable();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('module');
            $table->string('action');
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('permission_id');
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        $this->testLog = storage_path('logs/laravel-2026-10-28.log');
        file_put_contents($this->testLog, implode("\n", [
            '[2026-10-28 10:00:00] production.ERROR: Falló la auditoría',
            '#0 /var/www/app.php(10)',
            '[2026-10-28 10:01:00] production.INFO: Sesión registrada',
        ]));
    }

    protected function tearDown(): void
    {
        if (isset($this->testLog) && is_file($this->testLog)) {
            unlink($this->testLog);
        }

        DB::purge('sqlite');
        Config::set('database.default', $this->previousDatabaseDefault);
        parent::tearDown();
    }

    public function test_only_authorized_users_can_view_logs(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'auditoria_logs.ver',
            'module' => 'auditoria_logs',
            'action' => 'ver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Auditor',
            'nick_usuario' => 'auditor',
        ]);
        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/auditoria/logs/laravel-2026-10-28.log')->assertUnauthorized();

        $response = $this->actingAs(Usuario::query()->findOrFail($userId))
            ->getJson('/api/auditoria/logs/laravel-2026-10-28.log?level=error&search=ditor');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.level', 'error')
            ->assertJsonPath('data.0.details', '#0 /var/www/app.php(10)')
            ->assertJsonPath('meta.filename', 'laravel-2026-10-28.log');
    }

    public function test_unsafe_log_filenames_are_rejected(): void
    {
        $this->assertContains('auditoria_logs', config('acl.permissions') === [] ? [] : array_column(config('acl.permissions'), 'module'));

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'auditoria_logs.ver',
            'module' => 'auditoria_logs',
            'action' => 'ver',
        ]);
        $userId = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'Auditor',
            'nick_usuario' => 'auditor',
        ]);
        DB::table('user_permissions')->insert([
            'user_id' => $userId,
            'permission_id' => $permissionId,
        ]);

        $this->actingAs(Usuario::query()->findOrFail($userId))
            ->getJson('/api/auditoria/logs/laravel-2026-10-99.log')
            ->assertNotFound();
    }
}
