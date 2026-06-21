<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('mpesa.base_url');
    }

    // -------------------------------------------------------
    // Auth
    // -------------------------------------------------------

    public function getAccessToken(): string
    {
        return Cache::remember('mpesa_access_token', 3500, function () {
            $response = Http::withBasicAuth(
                config('mpesa.consumer_key'),
                config('mpesa.consumer_secret')
            )->get("{$this->baseUrl}/oauth/v1/generate", [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                Log::error('MPESA auth failed', $response->json());
                throw new \Exception('Failed to get MPESA access token.');
            }

            return $response->json('access_token');
        });
    }

    // -------------------------------------------------------
    // STK Push
    // -------------------------------------------------------

    public function stkPush(string $phone, float $amount, string $reference, string $description): array
    {
        $timestamp = now()->format('YmdHis');
        $password  = base64_encode(
            config('mpesa.shortcode') . config('mpesa.passkey') . $timestamp
        );

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                'BusinessShortCode' => config('mpesa.shortcode'),
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => (int) ceil($amount),
                'PartyA'            => $phone,
                'PartyB'            => config('mpesa.shortcode'),
                'PhoneNumber'       => $phone,
                'CallBackURL'       => config('mpesa.callback_url'),
                'AccountReference'  => $reference,
                'TransactionDesc'   => $description,
            ]);

        if ($response->failed()) {
            Log::error('MPESA STK push failed', $response->json());
            throw new \Exception('Failed to initiate STK push.');
        }

        return $response->json();
    }

    // -------------------------------------------------------
    // B2C Payout
    // -------------------------------------------------------

    public function b2cPayout(string $phone, float $amount, string $reference): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/mpesa/b2c/v1/paymentrequest", [
                'InitiatorName'      => config('mpesa.b2c_initiator'),
                'SecurityCredential' => config('mpesa.b2c_security_credential'),
                'CommandID'          => 'BusinessPayment',
                'Amount'             => (int) floor($amount),
                'PartyA'             => config('mpesa.shortcode'),
                'PartyB'             => $phone,
                'Remarks'            => $reference,
                'QueueTimeOutURL'    => config('mpesa.b2c_queue_url'),
                'ResultURL'          => config('mpesa.b2c_result_url'),
                'Occasion'           => 'Ludo Withdrawal',
            ]);

        if ($response->failed()) {
            Log::error('MPESA B2C failed', $response->json());
            throw new \Exception('Failed to initiate B2C payout.');
        }

        return $response->json();
    }
}