<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Auth;

class FirebaseTokenController extends Controller
{
    public function __construct(private readonly Auth $auth) {}

    public function __invoke(Request $request): JsonResponse
    {
        $uid = (string) $request->user()->id;

        $customToken = $this->auth->createCustomToken($uid);

        return response()->json([
            'firebase_token' => $customToken->toString(),
        ]);
    }
}