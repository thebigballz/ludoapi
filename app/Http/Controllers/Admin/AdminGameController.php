<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGameController extends Controller
{
    // Unlike the player-facing index(), this returns every status,
    // not just 'waiting' — admins need to see active/finished/cancelled too.
    public function index(Request $request): JsonResponse
    {
        $games = Game::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->with('players.user')
            ->latest()
            ->paginate(25);

        return response()->json([
            'games' => GameResource::collection($games),
            'meta'  => [
                'current_page' => $games->currentPage(),
                'last_page'    => $games->lastPage(),
                'total'        => $games->total(),
            ],
        ]);
    }
}