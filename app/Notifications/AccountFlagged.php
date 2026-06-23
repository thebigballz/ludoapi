<?php

namespace App\Notifications;

use App\Models\FraudFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccountFlagged extends Notification
{
    use Queueable;

    public function __construct(private readonly FraudFlag $flag) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'flag_id'  => $this->flag->id,
            'rule'     => $this->flag->rule,
            'severity' => $this->flag->severity,
            'detail'   => $this->flag->detail,
        ];
    }
}