<?php

namespace App\Http\Controllers\Mpesa;

use App\Domain\Mpesa\Actions\HandleStkCallback;
use App\Domain\Mpesa\DTOs\StkCallbackDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StkCallbackController extends Controller
{
    public function __construct(private readonly HandleStkCallback $handleStkCallback) {}

    public function __invoke(Request $request): Response
    {
        try {
            $dto = StkCallbackDTO::fromCallback($request->all());
            $this->handleStkCallback->execute($dto);
        } catch (\Exception $e) {
            // Always return 200 to Safaricom — they retry on non-200
            return response('', 200);
        }

        return response('', 200);
    }
}