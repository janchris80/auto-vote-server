<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait HelperTrait
{
    protected function canMakeRequest($name)
    {
        return !Cache::has('last_api_request_time.' . $name);
    }

    protected function makeHttpRequest($data)
    {
        return Http::post('https://rpc.d.buzz/', $data)->json()['result'] ?? [];
    }

    protected function getApiData(string $method, $params)
    {
        $response = Http::post(config('hive.api_url_node'), [
            'jsonrpc' => '2.0',
            'method' => (string) $method,
            'params' => $params,
            'id' => 1,
        ]);

        // Decode and return the JSON response
        return $response->json()['result'] ?? [];
    }
}
