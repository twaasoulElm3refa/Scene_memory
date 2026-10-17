<?php

return [
    'enabled' => env('VIRTUAL_GIFTS_ENABLED', true),
    'holding_days' => (int) env('GIFT_HOLDING_DAYS', 7),
    // MinorMoney and the current wallet use two decimal places. No FX is performed.
    'currencies' => ['USD', 'EUR', 'GBP', 'EGP', 'SAR', 'AED'],
    'paypal_currencies' => ['AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'ILS', 'MYR', 'MXN', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD'],
    'max_quantity' => 100,
    'max_gross_minor' => 99999999, // Existing purchases.amount is DECIMAL(8,2).
];
