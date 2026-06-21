<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\RegisterUser;
use App\Domain\Auth\DTOs\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly RegisterUser $registerUser) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUser->execute(
            RegisterDTO::fromRequest($request->validated())
        );

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        return response()->json([
            'token'   => $token,
            'message' => 'Account created successfully.',
            'user'    => new UserResource($user->load('wallet')),
        ], 201);
    }
}