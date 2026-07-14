<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @param array<int|string, mixed> $errors
     */
    protected function apiSuccess(array $data, array $meta = [], int $status = 200, array $errors = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<int|string, mixed> $errors
     * @param array<string, mixed> $meta
     */
    protected function apiError(string $message, array $errors = [], int $status = 400, array $meta = []): \Illuminate\Http\JsonResponse
    {
        $payloadErrors = $errors;
        if ($message !== '') {
            $payloadErrors = array_merge(['message' => $message], $errors);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'meta' => $meta,
            'errors' => $payloadErrors,
        ], $status);
    }
}
