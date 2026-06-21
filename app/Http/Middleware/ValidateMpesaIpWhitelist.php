<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateMpesaIpWhitelist
{
    // Safaricom's official sandbox + production callback IPs
    private array $allowedIps = [
        '196.201.214.200',
        '196.201.214.206',
        '196.201.213.114',
        '196.201.214.207',
        '196.201.214.208',
        '196.201.213.44',
        '196.201.212.127',
        '196.201.212.138',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.74',
        '196.201.212.69',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Skip IP check in sandbox/local — ngrok changes IPs
        if (config('mpesa.env') === 'sandbox') {
            return $next($request);
        }

        if (! in_array($request->ip(), $this->allowedIps)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}