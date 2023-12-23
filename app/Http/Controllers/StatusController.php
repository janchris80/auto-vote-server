<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $dbStatus = false;

        try {
            // Attempt to connect to the database
            DB::connection()->getPdo();
            $dbStatus = true;
        } catch (\Exception $e) {
            // Connection could not be established
            // You can log the error or handle it as needed
            $dbStatus = false;
        }

        return [
            'php_version' => phpversion(),
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'db_status' => $dbStatus,
            // Add more information as needed
        ];
    }
}
