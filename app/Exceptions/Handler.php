<?php
// app/Exceptions/Handler.php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        // Untuk API routes, selalu return JSON
        if ($request->is('api/*')) {

            // Authorization Exception
            if ($exception instanceof AuthorizationException) {
                return response()->json([
                    'status' => 'error',
                    'message' => $exception->getMessage() ?: 'Unauthorized access',
                    'code' => 403
                ], 403);
            }

            // Validation Exception
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $exception->errors(),
                    'code' => 422
                ], 422);
            }
        }

        return parent::render($request, $exception);
    }
}
