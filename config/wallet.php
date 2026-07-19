<?php

return [
    'minimum_topup_minor' => (int) env('WALLET_MINIMUM_TOPUP_MINOR', 100),
    'maximum_topup_minor' => (int) env('WALLET_MAXIMUM_TOPUP_MINOR', 100000000),
    'allow_negative_balance' => false,
];
