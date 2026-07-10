<?php

return [
    /*
     | Allow stock to go negative on OUT movements. Default false: an OUT that
     | exceeds the current balance is rejected by StockService.
     */
    'allow_negative' => env('INVENTORY_ALLOW_NEGATIVE', false),

    // Numbering prefixes and starting numbers (matches pre-printed booklets).
    'numbering' => [
        'dr' => ['prefix' => 'DR', 'start' => 1],
        'ws' => ['prefix' => 'WS', 'start' => 320301],
        'ts' => ['prefix' => 'TS', 'start' => 22501],
    ],
];
