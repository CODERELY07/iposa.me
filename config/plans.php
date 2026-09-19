<?php

/*
|--------------------------------------------------------------------------
| Subscription plans
|--------------------------------------------------------------------------
| One business = one branch = one plan. Prices are monthly, in pesos.
| `staff_limit` counts cashier accounts (null = unlimited).
| `features` gates modules: expenses, reports (P&L + ledger), recipes.
| Export is on every plan on purpose: it's the "never locked in" promise.
*/

return [

    'default' => 'negosyo',

    'trial_days' => 14,

    'billing_period_days' => 30,

    'manual_payment' => [
        'gcash_number' => env('BILLING_GCASH_NUMBER', '0917 000 0000'),
        'gcash_name' => env('BILLING_GCASH_NAME', 'iPOSa'),
        'bank' => env('BILLING_BANK', 'BPI · 0000-0000-00 · iPOSa'),
    ],

    'plans' => [

        'tindahan' => [
            'name' => 'Tindahan',
            'price' => 499,
            'pitch' => 'Register, inventory, closing audit, daily sales',
            'staff_limit' => 3,
            'features' => [
                'expenses' => false,
                'reports' => false,
                'recipes' => false,
            ],
            'feature_list' => [
                'Register (POS) with Cash, GCash, Maya',
                'Inventory: menu, pieces, bulk',
                'Closing audit',
                'Today dashboard & low-stock alerts',
                'CSV export',
                'Up to 3 staff',
            ],
        ],

        'negosyo' => [
            'name' => 'Negosyo',
            'price' => 999,
            'pitch' => 'Everything + expenses, P&L ledger, equipment payables, unlimited staff',
            'staff_limit' => null,
            'features' => [
                'expenses' => true,
                'reports' => true,
                'recipes' => true,
            ],
            'feature_list' => [
                'Everything in Tindahan',
                'Ingredient links (recipes)',
                'Expenses & equipment payables',
                'P&L and daily ledger',
                'Unlimited staff',
            ],
        ],

    ],

];
