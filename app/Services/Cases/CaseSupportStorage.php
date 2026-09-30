<?php
declare(strict_types=1);

namespace App\Services\Cases;

final class CaseSupportStorage
{
    private const MAX_BYTES = 10_485_760;

    /** @param array<string,mixed> $file */
    public function store(array $file): ?string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('El soporte cargado no es válido.');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('El soporte debe pesar máximo 10 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);

        $allowed = [
            'application/pdf'=>'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'application/msword'=>'doc',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
            'application/vnd.ms-excel'=>'xls',
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
        ];

        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Tipo de soporte no permitido.');
        }

        $dir = dirname(__DIR__, 3) . '/storage/case-supports';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('No fue posible preparar el almacenamiento de soportes.');
        }

        $name = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $path = $dir . '/' . $name;

        if (!move_uploaded_file($tmp, $path)) {
            throw new \RuntimeException('No fue posible almacenar el soporte.');
        }

        return $name;
    }
}
