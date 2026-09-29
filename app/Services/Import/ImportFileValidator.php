<?php
declare(strict_types=1);

namespace App\Services\Import;

final class ImportFileValidator
{
    private const MAX_BYTES = 20971520;

    /** @param array<string,mixed> $file
     *  @return array{extension:string,mime:string,size:int,sha256:string,original_name:string,tmp_name:string}
     */
    public function validate(array $file, bool $allowCsv, bool $allowXlsx): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('No fue posible recibir el archivo.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('El archivo debe pesar máximo 20 MB.');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Carga de archivo inválida.');
        }

        $original = basename((string)($file['name'] ?? 'archivo'));
        $ext = mb_strtolower(pathinfo($original, PATHINFO_EXTENSION), 'UTF-8');

        $allowed = [];
        if ($allowXlsx) $allowed[] = 'xlsx';
        if ($allowCsv) $allowed[] = 'csv';
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('El tipo de archivo no está permitido para esta estructura.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);

        $xlsxMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ];
        $csvMimes = [
            'text/plain','text/csv','application/csv',
            'application/vnd.ms-excel','application/octet-stream',
        ];

        $validMime = $ext === 'xlsx'
            ? in_array($mime, $xlsxMimes, true)
            : in_array($mime, $csvMimes, true);

        if (!$validMime) {
            throw new \RuntimeException('El contenido del archivo no corresponde al formato permitido.');
        }

        $sha = hash_file('sha256', $tmp);
        if (!is_string($sha) || strlen($sha) !== 64) {
            throw new \RuntimeException('No fue posible calcular la huella del archivo.');
        }

        return [
            'extension'=>$ext,
            'mime'=>$mime,
            'size'=>$size,
            'sha256'=>$sha,
            'original_name'=>$original,
            'tmp_name'=>$tmp,
        ];
    }
}
