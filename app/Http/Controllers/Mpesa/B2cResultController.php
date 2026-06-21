<?php

namespace App\Http\Controllers\Mpesa;

use App\Domain\Mpesa\Actions\HandleB2cResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class B2cResultController extends Controller
{
    public function __construct(private readonly HandleB2cResult $handleB2cResult) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->handleB2cResult->execute($request->all());
        } catch (\Exception $e) {
            return response('', 200);
        }

        return response('', 200);
    }
}