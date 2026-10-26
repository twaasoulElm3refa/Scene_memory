<?php

return [
    'minimum_topup_minor' => (int) env('WALLET_MINIMUM_TOPUP_MINOR', 100),
    'maximum_topup_minor' => (int) env('WALLET_MAXIMUM_TOPUP_MINOR', 100000000),
    'allow_negative_balance' => false,

    // Fee applied to creator withdrawal requests on the server. Users cannot set
    // their own fee; administrators may still adjust it while reviewing a payout.
    'withdrawal_fee_fixed_minor' => max(0, (int) env('WALLET_WITHDRAWAL_FEE_FIXED_MINOR', 0)),
    'withdrawal_fee_bps' => min(10000, max(0, (int) env('WALLET_WITHDRAWAL_FEE_BPS', 0))),
];
