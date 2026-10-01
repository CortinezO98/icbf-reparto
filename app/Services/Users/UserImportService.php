<?php
declare(strict_types=1);

namespace App\Services\Users;

use App\Auth\PasswordPolicy;
use App\Repositories\UserRepository;
use App\Services\Users\QueueSelection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;

final class UserImportService
{
    private const MAX_BYTES = 5_242_880;
    private const MAX_ROWS = 5000;

    public function __construct(
        private PDO $pdo,
        private UserRepository $users
    ) {
    }

    /**
     * @param array<string,mixed> $file
     * @return array{created:int,skipped:int,invalid:int,generated_passwords:list<array{username:string,password:string}>,errors:list<string>}
     */
    public function import(array $file, int $actorUserId, bool $skipDuplicates = true): array
    {
        $path = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK || $path === '' || !is_uploaded_file($path)) {
            throw new \RuntimeException('No se recibió un archivo válido.');
        }

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('El archivo debe pesar máximo 5 MB.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx','csv'], true)) {
            throw new \RuntimeException('Solo se permiten archivos XLSX o CSV.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $highestRow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 1);
        $highestCol = min($sheet->getHighestDataColumn(), 'K');

        $matrix = $sheet->rangeToArray(
            'A1:' . $highestCol . $highestRow,
            null,
            true,
            false
        );

        if ($matrix === []) {
            throw new \RuntimeException('El archivo está vacío.');
        }

        $headers = array_map(
            static fn(mixed $v): string => self::normalizeHeader((string)$v),
            array_values($matrix[0])
        );

        $required = ['usuario','correo','nombre_completo','roles'];
        foreach ($required as $header) {
            if (!in_array($header, $headers, true)) {
                throw new \RuntimeException("Falta la columna obligatoria: {$header}.");
            }
        }

        $created = 0;
        $skipped = 0;
        $invalid = 0;
        $errors = [];
        $generated = [];

        $this->pdo->beginTransaction();

        try {
            for ($i = 1; $i < count($matrix); $i++) {
                $sourceRow = $i + 1;
                $values = array_values($matrix[$i]);

                if (count(array_filter($values, static fn(mixed $v): bool => trim((string)$v) !== '')) === 0) {
                    continue;
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $row[$header] = trim((string)($values[$index] ?? ''));
                    }
                }

                $document = trim((string)($row['documento'] ?? ''));
                $username = trim((string)($row['usuario'] ?? ''));
                $email = mb_strtolower(trim((string)($row['correo'] ?? '')));
                $fullName = trim((string)($row['nombre_completo'] ?? ''));
                $rolesRaw = (string)($row['roles'] ?? '');
                $queuesRaw = (string)($row['colas'] ?? '');
                $active = self::boolValue($row['activo'] ?? '1');
                $assignEnabled = self::boolValue($row['habilitado_reparto'] ?? '1');

                if (
                    $username === ''
                    || $fullName === ''
                    || !filter_var($email, FILTER_VALIDATE_EMAIL)
                    || trim($rolesRaw) === ''
                ) {
                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: usuario, correo, nombre y roles son obligatorios.";
                    continue;
                }

                if ($document === '') {
                    $document = 'TMP-' . strtoupper(substr(hash('sha256', $username . '|' . $email), 0, 12));
                }

                if ($this->users->duplicateExists($document, $username, $email)) {
                    if ($skipDuplicates) {
                        $skipped++;
                        continue;
                    }

                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: documento, usuario o correo ya existe.";
                    continue;
                }

                $roleCodes = self::splitCodes($rolesRaw);
                $roleIds = $this->users->roleIdsFromCodes($roleCodes);

                if (count($roleIds) !== count($roleCodes)) {
                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: contiene roles inexistentes.";
                    continue;
                }

                $isAgent = in_array('AGENTE', $roleCodes, true);

                if ($isAgent && QueueSelection::meansAll($queuesRaw)) {
                    $queueCodes = [];
                    $queueIds = $this->users->activeQueueIds();
                } else {
                    $queueCodes = self::splitCodes($queuesRaw);
                    $queueIds = $this->users->queueIdsFromCodes($queueCodes);
                }

                if ($isAgent && $queueIds === []) {
                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: un AGENTE debe tener al menos una cola activa.";
                    continue;
                }

                if ($queueCodes !== [] && count($queueIds) !== count($queueCodes)) {
                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: contiene colas inexistentes.";
                    continue;
                }

                if (!$isAgent) {
                    $queueIds = [];
                    $assignEnabled = 0;
                }

                $password = trim((string)($row['password'] ?? ''));
                $passwordWasGenerated = $password === '';
                if ($passwordWasGenerated) {
                    $password = TemporaryPasswordGenerator::generate();
                    $generated[] = ['username'=>$username, 'password'=>$password];
                }

                $passwordErrors = PasswordPolicy::validate($password);
                if ($passwordErrors !== []) {
                    $invalid++;
                    $errors[] = "Fila {$sourceRow}: " . implode(' ', $passwordErrors);
                    continue;
                }

                $this->users->create([
                    'document_number'=>$document,
                    'username'=>$username,
                    'email'=>$email,
                    'full_name'=>$fullName,
                    'password_hash'=>PasswordPolicy::hash($password),
                    'password_must_change'=>$passwordWasGenerated ? 1 : 0,
                    'is_active'=>$active,
                    'assign_enabled'=>$assignEnabled,
                    'created_by'=>$actorUserId,
                ], $roleIds, $queueIds);

                $created++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'created'=>$created,
            'skipped'=>$skipped,
            'invalid'=>$invalid,
            'generated_passwords'=>$generated,
            'errors'=>array_slice($errors, 0, 100),
        ];
    }

    private static function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = strtr($header, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        ]);
        $header = preg_replace('/[^a-z0-9]+/u', '_', $header) ?? '';
        return trim($header, '_');
    }

    /** @return list<string> */
    private static function splitCodes(string $value): array
    {
        $parts = preg_split('/[,;|]+/', strtoupper($value)) ?: [];
        return array_values(array_unique(array_filter(array_map('trim', $parts))));
    }

    private static function boolValue(mixed $value): int
    {
        return in_array(
            mb_strtolower(trim((string)$value)),
            ['1','si','sí','yes','true','activo','habilitado'],
            true
        ) ? 1 : 0;
    }
}
