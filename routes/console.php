<?php

use App\Http\Controllers\ContratoController;
use App\Http\Controllers\PagosController;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pagos:benchmark {idFeria?} {--runs=3}', function (?int $idFeria = null) {
    $runs = max(1, (int) $this->option('runs'));

    if (!$idFeria) {
        $idFeria = (int) Contrato::query()
            ->where('stand_1', '>', 0)
            ->where('codigo_contrato', '>', 0)
            ->selectRaw('id_feria, COUNT(DISTINCT id_empresa) as empresas')
            ->groupBy('id_feria')
            ->orderByDesc('empresas')
            ->value('id_feria');
    }

    if (!$idFeria) {
        $this->error('No se encontró una feria con contratos para benchmark.');
        return;
    }

    $this->info("Benchmark pagos para feria: {$idFeria} (runs: {$runs})");

    $controller = app(PagosController::class);
    $request = Request::create("/pagos/ferias/{$idFeria}", 'GET');

    $duraciones = [];
    $queryCounts = [];
    $queryTimes = [];
    $payloadBytes = [];
    $empresasCounts = [];
    $contratosCounts = [];
    $pagosAprobadosCounts = [];
    $pagosPendientesCounts = [];

    for ($i = 1; $i <= $runs; $i++) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $response = $controller->getPagosbyFeria($request, $idFeria);
        $durationMs = (microtime(true) - $start) * 1000;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $queryCount = count($queries);
        $queryTime = array_sum(array_map(static fn ($q) => (float) ($q['time'] ?? 0), $queries));

        $payload = $response->getData(true);
        $json = json_encode($payload);

        $empresas = $payload['data']['empresas'] ?? [];
        $empresasCount = count($empresas);
        $contratosCount = 0;
        $pagosAprobados = 0;
        $pagosPendientes = 0;

        foreach ($empresas as $empresa) {
            $contratosCount += count($empresa['contratos'] ?? []);
            $pagosAprobados += count($empresa['pagos_aprobados'] ?? []);
            $pagosPendientes += count($empresa['pagos_pendientes'] ?? []);
        }

        $duraciones[] = $durationMs;
        $queryCounts[] = $queryCount;
        $queryTimes[] = $queryTime;
        $payloadBytes[] = strlen((string) $json);
        $empresasCounts[] = $empresasCount;
        $contratosCounts[] = $contratosCount;
        $pagosAprobadosCounts[] = $pagosAprobados;
        $pagosPendientesCounts[] = $pagosPendientes;

        $this->line(sprintf(
            'Run %d -> tiempo: %.2f ms | queries: %d (sql %.2f ms) | payload: %.2f MB | empresas: %d | contratos: %d | pagosAprobados: %d | pagosPendientes: %d',
            $i,
            $durationMs,
            $queryCount,
            $queryTime,
            strlen((string) $json) / 1024 / 1024,
            $empresasCount,
            $contratosCount,
            $pagosAprobados,
            $pagosPendientes,
        ));
    }

    $avg = fn (array $values) => array_sum($values) / max(1, count($values));

    $this->newLine();
    $this->info('Resumen promedio');
    $this->line(sprintf('Tiempo total endpoint: %.2f ms', $avg($duraciones)));
    $this->line(sprintf('Consultas SQL: %.2f', $avg($queryCounts)));
    $this->line(sprintf('Tiempo SQL acumulado: %.2f ms', $avg($queryTimes)));
    $this->line(sprintf('Payload JSON: %.2f MB', $avg($payloadBytes) / 1024 / 1024));
    $this->line(sprintf('Empresas: %.0f | Contratos: %.0f | Pagos aprobados: %.0f | Pagos pendientes: %.0f', $avg($empresasCounts), $avg($contratosCounts), $avg($pagosAprobadosCounts), $avg($pagosPendientesCounts)));
})->purpose('Benchmark del endpoint de pagos por feria para detectar cuellos de botella');

Artisan::command('contratos:benchmark {idFeria?} {--runs=3}', function (?int $idFeria = null) {
    $runs = max(1, (int) $this->option('runs'));

    if (!$idFeria) {
        $idFeria = (int) Contrato::query()
            ->where('stand_1', '>', 0)
            ->where('codigo_contrato', '>', 0)
            ->selectRaw('id_feria, COUNT(DISTINCT id_empresa) as empresas')
            ->groupBy('id_feria')
            ->orderByDesc('empresas')
            ->value('id_feria');
    }

    if (!$idFeria) {
        $this->error('No se encontró una feria con contratos para benchmark.');
        return;
    }

    $this->info("Benchmark contratos para feria: {$idFeria} (runs: {$runs})");

    $controller = app(ContratoController::class);
    $request = Request::create("/contratos/ferias/{$idFeria}", 'GET');

    $duraciones = [];
    $queryCounts = [];
    $queryTimes = [];
    $payloadBytes = [];
    $contratosCounts = [];
    $anuladosCounts = [];
    $reservasCounts = [];
    $generadosCounts = [];

    for ($i = 1; $i <= $runs; $i++) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $response = $controller->getContratosByFeria($request, $idFeria);
        $durationMs = (microtime(true) - $start) * 1000;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $queryCount = count($queries);
        $queryTime = array_sum(array_map(static fn ($q) => (float) ($q['time'] ?? 0), $queries));

        $payload = $response->getData(true);
        $json = json_encode($payload);
        $contratos = $payload['data']['contratos'] ?? [];

        $totalContratos = count($contratos);
        $anulados = 0;
        $reservas = 0;
        $generados = 0;

        foreach ($contratos as $contrato) {
            $isAnulado = (bool) ($contrato['anulado'] ?? false);
            if ($isAnulado) {
                $anulados++;
                continue;
            }

            $numero = (string) ($contrato['contrato']['numero'] ?? '');
            if ($numero !== '' && !str_ends_with($numero, '00000')) {
                $generados++;
            } else {
                $reservas++;
            }
        }

        $duraciones[] = $durationMs;
        $queryCounts[] = $queryCount;
        $queryTimes[] = $queryTime;
        $payloadBytes[] = strlen((string) $json);
        $contratosCounts[] = $totalContratos;
        $anuladosCounts[] = $anulados;
        $reservasCounts[] = $reservas;
        $generadosCounts[] = $generados;

        $this->line(sprintf(
            'Run %d -> tiempo: %.2f ms | queries: %d (sql %.2f ms) | payload: %.2f MB | contratos: %d | generados: %d | reservas: %d | anulados: %d',
            $i,
            $durationMs,
            $queryCount,
            $queryTime,
            strlen((string) $json) / 1024 / 1024,
            $totalContratos,
            $generados,
            $reservas,
            $anulados,
        ));
    }

    $avg = fn (array $values) => array_sum($values) / max(1, count($values));

    $this->newLine();
    $this->info('Resumen promedio');
    $this->line(sprintf('Tiempo total endpoint: %.2f ms', $avg($duraciones)));
    $this->line(sprintf('Consultas SQL: %.2f', $avg($queryCounts)));
    $this->line(sprintf('Tiempo SQL acumulado: %.2f ms', $avg($queryTimes)));
    $this->line(sprintf('Payload JSON: %.2f MB', $avg($payloadBytes) / 1024 / 1024));
    $this->line(sprintf('Contratos: %.0f | Generados: %.0f | Reservas: %.0f | Anulados: %.0f', $avg($contratosCounts), $avg($generadosCounts), $avg($reservasCounts), $avg($anuladosCounts)));
})->purpose('Benchmark del endpoint de contratos por feria para detectar cuellos de botella');
