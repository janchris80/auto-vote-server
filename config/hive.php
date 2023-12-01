<?php

return [
    'account' => env('HIVE_ACCOUNT', null),
    'private_key' => [
        'owner' => env('HIVE_PRIVATE_KEY_OWNER', null),
        'active' => env('HIVE_PRIVATE_KEY_ACTIVE', null),
        'posting' => env('HIVE_PRIVATE_KEY_POSTING', null),
        'memo' => env('HIVE_PRIVATE_KEY_MEMO', null),
    ],

    'public_key' => [
        'owner' => env('HIVE_PRIVATE_KEY_OWNER', null),
        'active' => env('HIVE_PRIVATE_KEY_ACTIVE', null),
        'posting' => env('HIVE_PRIVATE_KEY_POSTING', null),
        'memo' => env('HIVE_PRIVATE_KEY_MEMO', null),
    ],
];
