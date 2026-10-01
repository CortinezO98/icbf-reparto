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
        $assignments = $repo->assignments($id);
        $events = $repo->events($id);
        $managementTypes = $repo->catalogItems('CASE_MANAGEMENT_TYPE');
        $escalations = $repo->catalogItems('ESCALATION_CATEGORY');
        $petitionTypes = $repo->catalogItems('PETITION_TYPE');
        $canManage = $repo->canManage($id, $uid);
        $canReassign = Authorization::hasPermission($this->pdo, $uid, 'CASE_REASSIGN');
        $reassignmentCandidates = $canReassign
            ? (new AssignmentRepository($this->pdo))->reassignmentCandidates(
                $id,
                (int)($case['assigned_user_id'] ?? 0)
            )
            : [];

        $success = $_SESSION['_flash_success'] ?? null;
        $error = $_SESSION['_flash_error'] ?? null;
        unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

        $view = dirname(__DIR__) . '/Views/cases/show.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }

    public function manage(int $id): void
    {