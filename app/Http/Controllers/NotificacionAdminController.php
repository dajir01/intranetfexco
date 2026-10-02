<?php

namespace App\Http\Controllers;

use App\Models\NotificacionAdmin;
use App\Support\AreaPermissions;
use Illuminate\Http\Request;

class NotificacionAdminController extends Controller
{
    private function authorizeNotifications(Request $request): void
    {
        if (!$request->user()) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(401, 'No autenticado');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeNotifications($request);

        $userId = (int) $request->user()->id_usuario;
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $items = NotificacionAdmin::query()
            ->where('id_usuario', $userId)
            ->latest('id')
            ->limit($limit)
            ->get();

        $unread = NotificacionAdmin::query()
            ->where('id_usuario', $userId)
            ->where('leida', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'unread' => $unread,
            ],
        ]);
    }

    public function marcarLeida(Request $request, int $id)
    {
        $this->authorizeNotifications($request);

        $item = NotificacionAdmin::query()
            ->where('id', $id)
            ->where('id_usuario', $request->user()->id_usuario)
            ->firstOrFail();

        $item->leida = true;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function marcarNoLeida(Request $request, int $id)
    {
        $this->authorizeNotifications($request);

        $item = NotificacionAdmin::query()
            ->where('id', $id)
            ->where('id_usuario', $request->user()->id_usuario)
            ->firstOrFail();

        $item->leida = false;
        $item->save();

        return response()->json(['success' => true]);
    }

    public function marcarTodasLeidas(Request $request)
    {
        $this->authorizeNotifications($request);

        NotificacionAdmin::query()
            ->where('id_usuario', $request->user()->id_usuario)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeNotifications($request);

        $item = NotificacionAdmin::query()
            ->where('id', $id)
            ->where('id_usuario', $request->user()->id_usuario)
            ->firstOrFail();

        $item->delete();

        return response()->json(['success' => true]);
    }
}
