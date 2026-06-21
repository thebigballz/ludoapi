<?php

namespace App\Domain\Mpesa\DTOs;

readonly class StkCallbackDTO
{
    public function __construct(
        public string  $merchantRequestId,
        public string  $checkoutRequestId,
        public int     $resultCode,
        public string  $resultDesc,
        public ?string $mpesaReceiptNumber,
        public ?float  $amount,
        public ?string $phone,
        public array   $rawPayload,
    ) {}

    public static function fromCallback(array $data): self
    {
        $body      = $data['Body']['stkCallback'];
        $metadata  = $body['CallbackMetadata']['Item'] ?? [];

        $get = fn (string $key) => collect($metadata)
            ->firstWhere('Name', $key)['Value'] ?? null;

        return new self(
            merchantRequestId:  $body['MerchantRequestID'],
            checkoutRequestId:  $body['CheckoutRequestID'],
            resultCode:         $body['ResultCode'],
            resultDesc:         $body['ResultDesc'],
            mpesaReceiptNumber: $get('MpesaReceiptNumber'),
            amount:             $get('Amount'),
            phone:              $get('PhoneNumber') ? (string) $get('PhoneNumber') : null,
            rawPayload:         $data,
        );
    }
}