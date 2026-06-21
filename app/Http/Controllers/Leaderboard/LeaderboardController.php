<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function index(): JsonResponse
    {
        $players = User::select([
                'users.id',
                'users.name',
                'users.avatar',
            ])
            ->selectRaw('COUNT(game_players.id) as total_games')
            ->selectRaw('SUM(CASE WHEN game_players.result = "winner" THEN 1 ELSE 0 END) as total_wins')
            ->selectRaw('SUM(CASE WHEN game_players.result = "loser" THEN 1 ELSE 0 END) as total_losses')
            ->selectRaw('COALESCE(wallets.total_won, 0) as total_earned')
            ->join('game_players', 'users.id', '=', 'game_players.user_id')
            ->join('wallets', 'users.id', '=', 'wallets.user_id')
            ->groupBy('users.id', 'users.name', 'users.avatar', 'wallets.total_won')
            ->orderByRaw('total_wins DESC')
            ->limit(50)
            ->get()
            ->map(function ($player, $index) {
                return [
                    'rank'         => $index + 1,
                    'id'           => $player->id,
                    'name'         => $player->name,
                    'avatar'       => $player->avatar,
                    'total_wins'   => (int) $player->total_wins,
                    'total_games'  => (int) $player->total_games,
                    'total_losses' => (int) $player->total_losses,
                    'total_earned' => number_format($player->total_earned, 2),
                    'win_rate'     => $player->total_games > 0
                        ? round(($player->total_wins / $player->total_games) * 100, 1)
                        : 0,
                ];
            });

        return response()->json(['players' => $players]);
    }
}