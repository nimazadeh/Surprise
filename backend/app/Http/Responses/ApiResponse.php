<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Standard /api/v1 envelope. Every API response uses this shape.
     */
    public static function ok(mixed $data = [], string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error(string $message, string $code = 'SERVER_ERROR', int $status = 500, mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => $data,
            'message' => $message,
            'code' => $code,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => [],
            'message' => $message,
            'code' => 'VALIDATION',
            'errors' => $errors,
        ], 422);
    }
}
