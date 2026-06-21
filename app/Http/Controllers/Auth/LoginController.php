<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\LoginUser;
use App\Domain\Auth\DTOs\LoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private readonly LoginUser $loginUser) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->loginUser->execute(
                LoginDTO::fromRequest($request->validated())
            );
        } catch (AuthenticationException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json([
            'token'   => $result['token'],
            'message' => 'Login successful.',
            'user'    => new UserResource($result['user']->load('wallet')),
        ]);
    }
}