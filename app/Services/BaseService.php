<?php
// app/Services/BaseService.php

namespace App\Services;

abstract class BaseService
{
    protected function success($data = null, string $message = '', int $code = 200)
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'code' => $code
        ];
    }

    protected function error(string $message = '', $errors = null, int $code = 400)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'code' => $code
        ];
    }
}
