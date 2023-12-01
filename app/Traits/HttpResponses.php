<?php

namespace App\Traits;

trait HttpResponses
{
    protected function success($data, $message = 'Request was successful.', $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($data, $message = 'Error has occurred...', $code)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
