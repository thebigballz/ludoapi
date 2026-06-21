<?php

return [
    'platform_fee_percent' => env('LUDO_PLATFORM_FEE_PERCENT', 10),
    'min_stake'            => env('LUDO_MIN_STAKE', 10),
    'max_stake'            => env('LUDO_MAX_STAKE', 5000),
    'max_daily_withdrawal' => env('LUDO_MAX_DAILY_WITHDRAWAL', 50000),
    'turn_timeout_seconds' => env('LUDO_TURN_TIMEOUT', 30),
    'max_reconnect_seconds'=> env('LUDO_MAX_RECONNECT', 60),
    'players_per_game'     => env('LUDO_PLAYERS_PER_GAME', 2),
];