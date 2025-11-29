<?php

namespace App\Services;

abstract class BaseService
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'code' => $code
        ];
    }

    protected function error(string $message, $errors = null, int $code = 400)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'errors' => $errors, // Selalu gunakan 'errors' untuk detail error
            'code' => $code
        ];
    }

    protected function validationError(array $errors, string $message = 'Validasi gagal')
    {
        return $this->error($message, $errors, 422);
    }

    protected function notFoundError(string $message = 'Data tidak ditemukan')
    {
        return $this->error($message, null, 404);
    }

    protected function serverError(string $message = 'Terjadi kesalahan server', \Exception $e = null)
    {
        // Untuk production, jangan tampilkan detail exception
        $errors = config('app.debug') && $e ? [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null;

        return $this->error($message, $errors, 500);
    }
}
