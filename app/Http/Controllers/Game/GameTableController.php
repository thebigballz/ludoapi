<?php

namespace App\Http\Controllers\Game;

use App\Domain\Game\Actions\CancelGame;
use App\Domain\Game\Actions\CreateGameTable;
use App\Domain\Game\Actions\JoinGameTable;
use App\Domain\Game\Actions\LeaveGameTable;
use App\Domain\Game\Actions\MovePawn;
use App\Domain\Game\Actions\RecordGameResult;
use App\Domain\Game\Actions\RollDice;
use App\Domain\Game\Actions\SkipTurn;
use App\Domain\Game\DTOs\CreateTableDTO;
use App\Domain\Game\DTOs\GameResultDTO;
use App\Domain\Game\Exceptions\GameAlreadyStartedException;
use App\Domain\Game\Exceptions\InvalidGameStateException;
use App\Domain\Game\Exceptions\TableFullException;
use App\Domain\Wallet\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\CreateTableRequest;
use App\Http\Requests\Game\GameResultRequest;
use App\Http\Requests\Game\JoinTableRequest;
use App\Http\Requests\Game\MovePawnRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameTableController extends Controller
{
    public function __construct(
        private readonly CreateGameTable  $createGameTable,
        private readonly JoinGameTable    $joinGameTable,
        private readonly LeaveGameTable   $leaveGameTable,
        private readonly CancelGame       $cancelGame,
        private readonly RecordGameResult $recordGameResult,
        private readonly RollDice         $rollDice,
        private readonly MovePawn         $movePawn,
        private readonly SkipTurn         $skipTurn,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tables = Game::where('status', 'waiting')
            ->when($request->stake_amount, fn ($q) =>
                $q->where('stake_amount', $request->stake_amount)
            )
            ->with('players.user')
            ->latest()
            ->paginate(20);

        return response()->json([
            'tables' => GameResource::collection($tables),
        ]);
    }

    public function create(CreateTableRequest $request): JsonResponse
    {
        $game = $this->createGameTable->execute(
            CreateTableDTO::fromRequest($request->validated(), $request->user()->id)
        );

        return response()->json([
            'message' => 'Table created successfully.',
            'game'    => new GameResource($game->load('players')),
        ], 201);
    }

    public function join(JoinTableRequest $request): JsonResponse
    {
        $game = Game::findOrFail($request->game_id);

        try {
            $this->joinGameTable->execute($game, $request->user());
        } catch (GameAlreadyStartedException|TableFullException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Joined table successfully.',
            'game'    => new GameResource($game->fresh()->load('players')),
        ]);
    }

    public function leave(Request $request, Game $game): JsonResponse
    {
        try {
            $this->leaveGameTable->execute($game, $request->user());
        } catch (InvalidGameStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Left table successfully.']);
    }

    public function result(GameResultRequest $request): JsonResponse
    {
        try {
            $game = $this->recordGameResult->execute(
                GameResultDTO::fromRequest($request->validated())
            );
        } catch (InvalidGameStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Game result recorded.',
            'game'    => new GameResource($game->load('players')),
        ]);
    }

    public function cancel(Request $request, Game $game): JsonResponse
    {
        try {
            $this->cancelGame->execute($game);
        } catch (InvalidGameStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Game cancelled and stakes refunded.']);
    }

    public function roll(Request $request, Game $game): JsonResponse
    {
        try {
            $roll = $this->rollDice->execute(
                $game,
                $request->user()
            );
        } catch (InvalidGameStateException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Dice rolled successfully.',
            'dice_roll' => $roll,
        ]);
    }

    public function move(MovePawnRequest $request, Game $game): JsonResponse
    {
        try {
            $result = $this->movePawn->execute(
                $game,
                $request->user(),
                $request->integer('pawn_index'),
            );
        } catch (InvalidGameStateException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Pawn moved successfully.',
            'move' => [
                'pawn' => $result['pawn'],
                'from' => $result['from'],
                'to' => $result['to'],
                'captured' => $result['captured'],
            ],
            'game' => new GameResource($result['game']),
        ]);
    }

    public function skip(Request $request, Game $game): JsonResponse
    {
        try {
            $game = $this->skipTurn->execute($game, $request->user());
        } catch (InvalidGameStateException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Turn skipped because no legal move was available.',
            'game' => new GameResource($game),
        ]);
    }
}
