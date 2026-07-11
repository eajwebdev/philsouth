<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grantable pages
    |--------------------------------------------------------------------------
    |
    | The set of sections an engineer (or administrator) can switch on/off for a
    | staff member who has been given a login. Each entry maps a human label to
    | the Spatie permission that gates the page (see resources/js/lib/nav.ts and
    | the route/policy checks). Granting a page = giving the user that permission
    | directly. Employee-created logins get the permissionless `staff` role, so
    | their access is defined entirely by the pages toggled here.
    |
    */

    'pages' => [
        ['key' => 'inventory.view', 'label' => 'Stock & Inventory'],
        ['key' => 'items.manage', 'label' => 'Items'],
        ['key' => 'receiving.manage', 'label' => 'Receiving'],
        ['key' => 'withdrawal.create', 'label' => 'Withdrawals — create'],
        ['key' => 'withdrawal.release', 'label' => 'Withdrawals — release'],
        ['key' => 'withdrawal.receive', 'label' => 'Withdrawals — receive'],
        ['key' => 'transfer.create', 'label' => 'Transfers — create'],
        ['key' => 'transfer.receive', 'label' => 'Transfers — receive'],
        ['key' => 'reports.view', 'label' => 'Reports'],
    ],

];
