<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Repositories\AuditRepository;
use App\Repositories\CaseOperationsRepository;
use App\Services\Cases\CaseManagementRules;
use App\Services\Cases\CaseSupportStorage;
use App\Services\Sla\SlaService;
use App\Repositories\SlaRepository;
use PDO;

final class CasesController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Authorization::requirePermission($this->pdo, 'CASE_VIEW_OWN');

        $uid = (int)(Auth::id() ?? 0);
        $teamView =
            Authorization::hasPermission($this->pdo, $uid, 'CASE_VIEW_TEAM')
            || Authorization::hasPermission($this->pdo, $uid, 'CASE_VIEW_ALL');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim((string)($_GET['search'] ?? ''));
        $state = trim((string)($_GET['state'] ?? ''));
        $queueId = isset($_GET['queue_id']) && $_GET['queue_id'] !== ''
            ? (int)$_GET['queue_id']
            : null;
        $slaStatus = strtoupper(trim((string)($_GET['sla_status'] ?? '')));
        $allowedSla = ['GREEN','YELLOW','RED','BREACHED','RED_OR_BREACHED'];
        if (!in_array($slaStatus, $allowedSla, true)) {
            $slaStatus = '';
        }
        $managed = (string)($_GET['managed'] ?? '') === '1';

        $repo = new CaseOperationsRepository($this->pdo);
        $result = $repo->paginate(
            $uid,
            $teamView,
            $page,
            25,
            $search,
            $state,
            $queueId,
            $slaStatus,
            $managed
        );

        $cases = $result['rows'];
        $pagination = $result;
        $queues = $repo->queues();
        $scopeLabel = $teamView ? 'Casos del equipo' : 'Mis casos';

        $view = dirname(__DIR__) . '/Views/cases/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function show(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'CASE_VIEW_OWN');

        $uid = (int)(Auth::id() ?? 0);
        $repo = new CaseOperationsRepository($this->pdo);
        $case = $repo->findCase($id);

        if (!$case) {
            http_response_code(404);
            echo 'Caso no encontrado.';
            return;
        }

        $teamView =
            Authorization::hasPermission($this->pdo, $uid, 'CASE_VIEW_TEAM')
            || Authorization::hasPermission($this->pdo, $uid, 'CASE_VIEW_ALL');

        if (!$teamView && (int)($case['assigned_user_id'] ?? 0) !== $uid) {
            http_response_code(403);
            echo 'No tienes permiso para consultar este caso.';
            return;
        }

        $managements = $repo->managements($id);
        $events = $repo->events($id);
        $managementTypes = $repo->catalogItems('CASE_MANAGEMENT_TYPE');
        $escalations = $repo->catalogItems('ESCALATION_CATEGORY');
        $petitionTypes = $repo->catalogItems('PETITION_TYPE');
        $canManage = $repo->canManage($id, $uid);
        $canReassign = Authorization::hasPermission($this->pdo, $uid, 'CASE_REASSIGN');
        $reassignmentCandidates = $canReassign && (string)($case['current_state'] ?? '') !== 'CLOSED'
            ? $repo->reassignmentCandidates($id)
            : [];

        $success = $_SESSION['_flash_success'] ?? null;
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/cases/show.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function manage(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'CASE_MANAGE_OWN');
        Csrf::validate($_POST['_csrf'] ?? null);

        $uid = (int)(Auth::id() ?? 0);
        $type = strtoupper(trim((string)($_POST['management_type_code'] ?? '')));

        if (!in_array($type, CaseManagementRules::allowedManagementTypes(), true)) {
            $this->redirectError($id, 'Tipo de gestión no permitido.');
        }

        $escalation = trim((string)($_POST['escalation_category_code'] ?? ''));
        $newPetition = trim((string)($_POST['new_petition_type'] ?? ''));

        if (CaseManagementRules::requiresEscalationCategory($type) && $escalation === '') {
            $this->redirectError($id, 'Selecciona una categoría de escalamiento.');
        }

        if (CaseManagementRules::requiresNewPetitionType($type) && $newPetition === '') {
            $this->redirectError($id, 'Selecciona el nuevo tipo de petición.');
        }

        $supportPath = null;

        try {
            $supportPath = (new CaseSupportStorage())->store($_FILES['support'] ?? []);

            (new CaseOperationsRepository($this->pdo))->addManagement(
                $id,
                $uid,
                [
                    'management_type_code'=>$type,
                    'escalation_category_code'=>$escalation !== '' ? $escalation : null,
                    'petition_type_selected'=>trim((string)($_POST['petition_type_selected'] ?? '')) ?: null,
                    'new_petition_type'=>$newPetition !== '' ? $newPetition : null,
                    'observation'=>trim((string)($_POST['observation'] ?? '')) ?: null,
                    'support_path'=>$supportPath,
                ]
            );

            if ($type === 'CLOSED') {
                try {
                    (new SlaService(new SlaRepository($this->pdo)))->evaluateCase($id);
                } catch (\Throwable $slaError) {
                    error_log('[CasesController::manage][SLA_CLOSE] ' . $slaError->getMessage());
                }
            }

            (new AuditRepository($this->pdo))->log(
                $uid,
                'CASE_MANAGED',
                'CASE',
                (string)$id,
                ['management_type'=>$type]
            );

            $_SESSION['_flash_success'] = 'Gestión registrada correctamente.';
        } catch (\Throwable $e) {
            error_log('[CasesController::manage] ' . $e->getMessage());

            if ($supportPath !== null) {
                @unlink(dirname(__DIR__, 2) . '/storage/case-supports/' . $supportPath);
            }

            $_SESSION['_flash_error'] = 'No fue posible registrar la gestión.';
        }

        header('Location: /cases/' . $id);
        exit;
    }

    public function reassign(int $id): void
    {
        Authorization::requirePermission($this->pdo, 'CASE_REASSIGN');
        Csrf::validate($_POST['_csrf'] ?? null);

        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId <= 0) {
            $this->redirectError($id, 'Selecciona un agente de destino.');
        }

        try {
            (new CaseOperationsRepository($this->pdo))->reassignCase(
                $id,
                $targetUserId,
                (int)(Auth::id() ?? 0)
            );

            (new AuditRepository($this->pdo))->log(
                Auth::id(),
                'CASE_REASSIGNED',
                'CASE',
                (string)$id,
                ['new_user_id'=>$targetUserId]
            );

            $_SESSION['_flash_success'] = 'Caso reasignado correctamente.';
        } catch (\Throwable $e) {
            error_log('[CasesController::reassign] ' . $e->getMessage());
            $_SESSION['_flash_error'] = $e->getMessage();
        }

        header('Location: /cases/' . $id);
        exit;
    }

    private function redirectError(int $id, string $message): never
    {
        $_SESSION['_flash_error'] = $message;
        header('Location: /cases/' . $id);
        exit;
    }
}
