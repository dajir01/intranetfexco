<?php

namespace App\Http\Controllers;

use App\Services\LaravelLogViewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaravelLogController extends Controller
{
    public function __construct(private readonly LaravelLogViewer $logs) {}

    public function files(): JsonResponse
    {
        return response()->json([
            'data' => $this->logs->files(),
        ]);
    }

    public function entries(Request $request, string $filename): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['nullable', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json($this->logs->entries($filename, $validated));
    }
}
