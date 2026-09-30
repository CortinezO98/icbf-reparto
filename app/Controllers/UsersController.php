<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\PasswordPolicy;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;
use App\Services\Users\TemporaryPasswordGenerator;
use App\Services\Users\UserImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

final class UsersController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_VIEW');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim((string)($_GET['search'] ?? ''));
        $active = isset($_GET['active']) && $_GET['active'] !== ''
            ? (int)$_GET['active']
            : null;
        $roleId = isset($_GET['role_id']) && $_GET['role_id'] !== ''
            ? (int)$_GET['role_id']
            : null;
        $queueId = isset($_GET['queue_id']) && $_GET['queue_id'] !== ''
            ? (int)$_GET['queue_id']
            : null;

        $repo = new UserRepository($this->pdo);

        $result = $repo->paginate($page, 20, $search, $active, $roleId, $queueId);
        $users = $result['rows'];
        $pagination = $result;
        $roles = $repo->roles();
        $queues = $repo->queues();
        $stats = $repo->statistics();

        $success = $_SESSION['_flash_success'] ?? null;
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/users/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function createForm(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');

        $repo = new UserRepository($this->pdo);
        $roles = $repo->roles();
        $queues = $repo->queues();

        $error = $_SESSION['_flash_error'] ?? null;
        $old = $_SESSION['_old_user_form'] ?? [];
        unset($_SESSION['_flash_error'], $_SESSION['_old_user_form']);

        $view = dirname(__DIR__) . '/Views/users/create.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function create(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');
        Csrf::validate($_POST['_csrf'] ?? null);

        $repo = new UserRepository($this->pdo);
        $data = $this->commonData();
        $roleIds = $this->intArray($_POST['role_ids'] ?? []);
        $queueIds = $this->intArray($_POST['queue_ids'] ?? []);
        $allQueues = isset($_POST['all_queues']);

        $_SESSION['_old_user_form'] = [
            ...$data,
            'role_ids'=>$roleIds,
            'queue_ids'=>$queueIds,
            'all_queues'=>$allQueues ? 1 : 0,
        ];

        if ($roleIds === []) {
            $this->fail('Debes seleccionar al menos un rol.', '/admin/users/create');
        }

        $roleCodes = $repo->selectedRoleCodes($roleIds);
        if (count($roleCodes) !== count($roleIds)) {
            $this->fail('Uno de los roles seleccionados no es válido.', '/admin/users/create');
        }

        $isAgent = in_array('AGENTE', $roleCodes, true);

        if ($isAgent && $allQueues) {
            $queueIds = $repo->activeQueueIds();
        }

        if ($isAgent && $queueIds === []) {
            $this->fail('Los agentes deben tener al menos una cola asignada.', '/admin/users/create');
        }

        if (!$isAgent) {
            $queueIds = [];
            $data['assign_enabled'] = 0;
        }

        $password = trim((string)($_POST['password'] ?? ''));
        if ($password === '') {
            $password = TemporaryPasswordGenerator::generate();
        }

        $errors = PasswordPolicy::validate($password);
        if ($errors !== []) {
            $this->fail(implode(' ', $errors), '/admin/users/create');
        }

        if ($repo->duplicateExists(
            (string)$data['document_number'],
            (string)$data['username'],
            (string)$data['email']
        )) {
            $this->fail(
                'Documento, usuario o correo ya se encuentra registrado.',
                '/admin/users/create'
            );
        }

        $data['password_hash'] = PasswordPolicy::hash($password);
        $data['created_by'] = (int)(Auth::id() ?? 0);

        try {
            $userId = $repo->create($data, $roleIds, $queueIds);

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'USER_CREATED',
                'USER',
                (string)$userId,
                [
                    'roles'=>$roleCodes,
                    'queue_ids'=>$queueIds,
                    'assign_enabled'=>(int)$data['assign_enabled'],
                ]
            );

            unset($_SESSION['_old_user_form']);
            $_SESSION['_flash_success'] =
                'Usuario creado correctamente. Contraseña temporal: ' . $password;
            header('Location: /admin/users');
            exit;
        } catch (\Throwable $e) {
            error_log('[UsersController::create] ' . $e->getMessage());
            $this->fail(
                'No fue posible crear el usuario. Verifica los datos e inténtalo nuevamente.',
                '/admin/users/create'
            );
        }
    }

    public function editForm(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'USER_EDIT');

        $repo = new UserRepository($this->pdo);
        $editUser = $repo->findById($id);

        if (!$editUser) {
            $_SESSION['_flash_error'] = 'Usuario no encontrado.';
            header('Location: /admin/users');
            exit;
        }

        $roles = $repo->roles();
        $queues = $repo->queues();
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/users/edit.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function update(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'USER_EDIT');
        Csrf::validate($_POST['_csrf'] ?? null);

        $repo = new UserRepository($this->pdo);
        $existing = $repo->findById($id);

        if (!$existing) {
            $_SESSION['_flash_error'] = 'Usuario no encontrado.';
            header('Location: /admin/users');
            exit;
        }

        $data = $this->commonData();
        $roleIds = $this->intArray($_POST['role_ids'] ?? []);
        $queueIds = $this->intArray($_POST['queue_ids'] ?? []);
        $allQueues = isset($_POST['all_queues']);

        if ($roleIds === []) {
            $this->fail('Debes seleccionar al menos un rol.', "/admin/users/{$id}/edit");
        }

        $roleCodes = $repo->selectedRoleCodes($roleIds);
        $isAgent = in_array('AGENTE', $roleCodes, true);

        if ($isAgent && $allQueues) {
            $queueIds = $repo->activeQueueIds();
        }

        if ($isAgent && $queueIds === []) {
            $this->fail('Los agentes deben tener al menos una cola asignada.', "/admin/users/{$id}/edit");
        }

        if (!$isAgent) {
            $queueIds = [];
            $data['assign_enabled'] = 0;
        }

        if ($repo->duplicateExists(
            (string)$data['document_number'],
            (string)$data['username'],
            (string)$data['email'],
            $id
        )) {
            $this->fail(
                'Documento, usuario o correo ya se encuentra registrado.',
                "/admin/users/{$id}/edit"
            );
        }

        $password = trim((string)($_POST['password'] ?? ''));
        if ($password !== '') {
            $errors = PasswordPolicy::validate($password);
            if ($errors !== []) {
                $this->fail(implode(' ', $errors), "/admin/users/{$id}/edit");
            }
            $data['password_hash'] = PasswordPolicy::hash($password);
        }

        try {
            $repo->update(
                $id,
                $data,
                $roleIds,
                $queueIds,
                (int)(Auth::id() ?? 0)
            );

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'USER_UPDATED',
                'USER',
                (string)$id,
                [
                    'roles'=>$roleCodes,
                    'queue_ids'=>$queueIds,
                    'assign_enabled'=>(int)$data['assign_enabled'],
                    'password_changed'=>$password !== '',
                ]
            );

            $_SESSION['_flash_success'] = 'Usuario actualizado correctamente.';
            header('Location: /admin/users');
            exit;
        } catch (\Throwable $e) {
            error_log('[UsersController::update] ' . $e->getMessage());
            $this->fail('No fue posible actualizar el usuario.', "/admin/users/{$id}/edit");
        }
    }

    public function toggleActive(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'USER_EDIT');
        Csrf::validate($_POST['_csrf'] ?? null);

        if ((int)(Auth::id() ?? 0) === $id) {
            $_SESSION['_flash_error'] = 'No puedes desactivar tu propio usuario.';
            header('Location: /admin/users');
            exit;
        }

        try {
            $active = (new UserRepository($this->pdo))->toggleActive($id);

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                $active === 1 ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
                'USER',
                (string)$id
            );

            $_SESSION['_flash_success'] =
                $active === 1 ? 'Usuario activado.' : 'Usuario desactivado.';
        } catch (\Throwable $e) {
            error_log('[UsersController::toggleActive] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible cambiar el estado del usuario.';
        }

        header('Location: /admin/users');
        exit;
    }

    public function importForm(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');

        $error = $_SESSION['_flash_error'] ?? null;
        $result = $_SESSION['_user_import_result'] ?? null;
        unset($_SESSION['_flash_error'], $_SESSION['_user_import_result']);

        $view = dirname(__DIR__) . '/Views/users/import.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function importUsers(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');
        Csrf::validate($_POST['_csrf'] ?? null);

        try {
            $repo = new UserRepository($this->pdo);
            $result = (new UserImportService($this->pdo, $repo))->import(
                $_FILES['users_file'] ?? [],
                (int)(Auth::id() ?? 0),
                isset($_POST['skip_duplicates'])
            );

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'USERS_IMPORTED',
                'USER',
                null,
                [
                    'created'=>$result['created'],
                    'skipped'=>$result['skipped'],
                    'invalid'=>$result['invalid'],
                ]
            );

            $_SESSION['_user_import_result'] = $result;
        } catch (\Throwable $e) {
            error_log('[UsersController::importUsers] ' . $e->getMessage());
            $_SESSION['_flash_error'] = 'No fue posible importar los usuarios. Revisa el archivo y vuelve a intentarlo.';
        }

        header('Location: /admin/users/import');
        exit;
    }

    public function exportTemplate(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_CREATE');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $headers = [
            'Documento',
            'Usuario',
            'Correo',
            'Nombre Completo',
            'Roles',
            'Colas',
            'Habilitado Reparto',
            'Activo',
            'Password',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            '999999001',
            'agente.prueba',
            'agente.prueba@local.test',
            'Agente Prueba ICBF',
            'AGENTE',
            'TODAS',
            '1',
            '1',
            '',
        ], null, 'A2');

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_usuarios_icbf_reparto.xlsx"');
        header('Cache-Control: no-store');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function exportUsers(): void
    {
        Authorization::requirePermission($this->pdo, 'USER_VIEW');

        $repo = new UserRepository($this->pdo);
        $result = $repo->paginate(1, 100, trim((string)($_GET['search'] ?? '')));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $sheet->fromArray([
            'ID','Documento','Usuario','Nombre','Correo','Roles','Colas',
            'Activo','Habilitado Reparto','Presencia'
        ], null, 'A1');

        $row = 2;
        foreach ($result['rows'] as $user) {
            $sheet->fromArray([
                (int)$user['id'],
                (string)$user['document_number'],
                (string)$user['username'],
                (string)$user['full_name'],
                (string)$user['email'],
                (string)($user['roles'] ?? ''),
                (string)($user['queues'] ?? ''),
                (int)$user['is_active'],
                (int)$user['assign_enabled'],
                (string)($user['presence_label'] ?? 'Desconectado'),
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="usuarios_icbf_reparto.xlsx"');
        header('Cache-Control: no-store');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    /** @return array<string,mixed> */
    private function commonData(): array
    {
        $data = [
            'document_number'=>$this->singleLine((string)($_POST['document_number'] ?? '')),
            'username'=>trim((string)($_POST['username'] ?? '')),
            'email'=>mb_strtolower(trim((string)($_POST['email'] ?? ''))),
            'full_name'=>$this->singleLine((string)($_POST['full_name'] ?? '')),
            'is_active'=>isset($_POST['is_active']) ? 1 : 0,
            'assign_enabled'=>isset($_POST['assign_enabled']) ? 1 : 0,
        ];

        if (
            $data['document_number'] === ''
            || $data['username'] === ''
            || $data['full_name'] === ''
            || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $this->fail(
                'Completa correctamente los datos obligatorios.',
                $_SERVER['HTTP_REFERER'] ?? '/admin/users'
            );
        }

        if (
            mb_strlen((string)$data['document_number']) > 50
            || mb_strlen((string)$data['username']) > 100
            || mb_strlen((string)$data['email']) > 180
            || mb_strlen((string)$data['full_name']) > 180
        ) {
            $this->fail(
                'Uno de los campos supera la longitud máxima permitida.',
                $_SERVER['HTTP_REFERER'] ?? '/admin/users'
            );
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', (string)$data['username'])) {
            $this->fail(
                'El nombre de usuario debe tener entre 3 y 100 caracteres y solo puede contener letras, números, punto, guion o guion bajo.',
                $_SERVER['HTTP_REFERER'] ?? '/admin/users'
            );
        }

        return $data;
    }

    private function singleLine(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /** @return list<int> */
    private function intArray(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn(int $id): bool => $id > 0
        )));
    }

    private function fail(string $message, string $redirect): never
    {
        $_SESSION['_flash_error'] = $message;
        header('Location: ' . $redirect);
        exit;
    }
}
