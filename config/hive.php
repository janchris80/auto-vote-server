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
    'api_url_node' => env('API_NODE', 'https://rpc.d.buzz/'),
    'resource_credit_limit' => env('RESOURCE_CREDIT_LIMIT', 5),
    'account_history_limit' => env('ACCOUNT_HISTORY_LIMIT', 50),
    'account_posts_limit' => env('ACCOUNT_POST_LIMIT', 10),
];
