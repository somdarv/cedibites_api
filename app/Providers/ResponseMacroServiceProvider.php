<?php

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Success response (200)
        Response::macro('success', function (mixed $data = null): JsonResponse {
            return response()->json([
                'data' => $data,
            ]);
        });

        // Paginated response (200) - Uses Laravel's built-in pagination format
        Response::macro('paginated', function (mixed $data): JsonResponse {
            return response()->json($data);
        });

        // Created response (201)
        Response::macro('created', function (mixed $data = null): JsonResponse {
            return response()->json([
                'data' => $data,
            ], 201);
        });

        // Accepted response (202)
        Response::macro('accepted', function (string $message): JsonResponse {
            return response()->json([
                'message' => $message,
            ], 202);
        });

        // Deleted response (204)
        Response::macro('deleted', function (mixed $data = null): JsonResponse {
            return response()->json(null, 204);
        });

        // Error response (400)
        Response::macro('error', function (string $message, int $code = 400): JsonResponse {
            return response()->json([
                'message' => $message,
            ], $code);
        });

        // Unauthorized response (401)
        Response::macro('unauthorized', function (string $message = 'Invalid credentials.', string $error = 'invalid_credentials'): JsonResponse {
            return response()->json([
                'message' => $message,
                'error' => $error,
            ], 401);
        });

        // Forbidden response (403)
        Response::macro('forbidden', function (string $error): JsonResponse {
            return response()->json([
                'message' => $error,
            ], 403);
        });

        // Unprocessable response (422)
        Response::macro('unprocessable', function (string $error, array $errors = []): JsonResponse {
            return response()->json([
                'message' => $error,
                'errors' => $errors,
            ], 422);
        });

        // Server error response (500)
        Response::macro('server_error', function (mixed $data = null): JsonResponse {
            return response()->json(null, 500);
        });
    }
}
