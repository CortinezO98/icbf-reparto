<?php
declare(strict_types=1);

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Config\App;
use App\Config\Database;

/** @var string $view */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAuthPage = $path === '/login';

$currentUserRoles = [];
$isAgent = false;
$canViewAgentStatus = false;
$canViewUsers = false;
$canViewStructures = false;
$canViewQueues = false;
$canViewReports = false;
$canViewShifts = false;

if (Auth::check() && Auth::id() !== null) {
    $pdo = Database::connection();
    $uid = (int)Auth::id();

    $currentUserRoles = Authorization::roles($pdo, $uid);
    $isAgent = in_array('AGENTE', $currentUserRoles, true);
    $canViewAgentStatus = Authorization::hasPermission($pdo, $uid, 'QUEUE_VIEW');
    $canViewUsers = Authorization::hasPermission($pdo, $uid, 'USER_VIEW');
    $canViewStructures = Authorization::hasPermission($pdo, $uid, 'STRUCTURE_VIEW');
    $canViewQueues = Authorization::hasPermission($pdo, $uid, 'QUEUE_VIEW');
    $canViewReports = Authorization::hasPermission($pdo, $uid, 'REPORT_VIEW');
    $canViewShifts = Authorization::hasPermission($pdo, $uid, 'SHIFT_VIEW');
}

$isActive = static function (string $prefix) use ($path): bool {
    return $path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/');
};

$user = Auth::user() ?? [];
$fullName = (string)($user['full_name'] ?? $user['username'] ?? '');
$rolesLabel = $currentUserRoles !== [] ? implode(', ', $currentUserRoles) : '';

$globalFlashError = $_SESSION['_flash_error'] ?? null;
$globalFlashSuccess = $_SESSION['_flash_success'] ?? null;
$globalFlashWarning = $_SESSION['_flash_warning'] ?? null;
unset($_SESSION['_flash_error'], $_SESSION['_flash_success'], $_SESSION['_flash_warning']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Módulo de reparto de peticiones ICBF">
    <title><?= htmlspecialchars(App::name(), ENT_QUOTES, 'UTF-8') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
          rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="/assets/css/app.css?v=3" rel="stylesheet">
    <?php if (str_starts_with($path, '/cases/')): ?>
        <link href="/assets/css/case-detail.css?v=1" rel="stylesheet">
    <?php endif; ?>
</head>

<body class="bg-light <?= $isAuthPage ? 'page-login' : 'page-app' ?>">
<?php if (!$isAuthPage && Auth::check()): ?>
<nav class="navbar navbar-expand-lg navbar-dark"
     style="background-color:var(--color-primary);"
     aria-label="Navegación principal">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2"
           href="<?= $isAgent ? '/cases' : '/sla' ?>"
           aria-label="ICBF Reparto">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <span>ICBF Reparto</span>
        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Alternar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (Authorization::hasPermission(Database::connection(), (int)Auth::id(), 'SLA_VIEW')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive('/sla') || $path === '/' || $path === '/dashboard' ? 'active' : '' ?>" href="/sla">
                            <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Tablero ANS
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link <?= $isActive('/cases') ? 'active' : '' ?>" href="/cases">
                        <i class="bi bi-inbox me-1" aria-hidden="true"></i>Casos
                    </a>
                </li>

                <?php if ($canViewReports): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive('/reports') ? 'active' : '' ?>" href="/reports">
                            <i class="bi bi-file-earmark-bar-graph me-1" aria-hidden="true"></i>Reportes
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (Authorization::hasPermission(Database::connection(), (int)Auth::id(), 'IMPORT_UPLOAD')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive('/imports') ? 'active' : '' ?>" href="/imports">
                            <i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>Cargas
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($canViewAgentStatus): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive('/supervisor/agents') ? 'active' : '' ?>" href="/supervisor/agents">
                            <i class="bi bi-person-workspace me-1" aria-hidden="true"></i>Estado agentes
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($canViewUsers || $canViewStructures || $canViewQueues || $canViewShifts): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $isActive('/admin') ? 'active' : '' ?>"
                           href="#"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">
                            <i class="bi bi-gear me-1" aria-hidden="true"></i>Administración
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($canViewUsers): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/users">
                                        <i class="bi bi-people me-2" aria-hidden="true"></i>Usuarios
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canViewStructures): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/structures">
                                        <i class="bi bi-file-earmark-spreadsheet me-2" aria-hidden="true"></i>Estructuras
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canViewQueues): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/queues">
                                        <i class="bi bi-diagram-2 me-2" aria-hidden="true"></i>Colas
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($canViewShifts): ?>
                                <li>
                                    <a class="dropdown-item" href="/admin/shifts">
                                        <i class="bi bi-calendar3 me-2" aria-hidden="true"></i>Turnos y cronograma
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <?php if ($isAgent): ?>
                    <div class="dropdown" id="agentPresenceWidget">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2 agent-presence-toggle"
                                type="button"
                                id="agentPresenceToggle"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <span class="agent-presence-dot" id="agentPresenceDot" style="background:#94a3b8"></span>
                            <span id="agentPresenceLabel">Desconectado</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end agent-presence-menu p-2"
                             id="agentPresenceMenu"
                             aria-labelledby="agentPresenceToggle"></div>
                    </div>
                <?php endif; ?>

                <div class="text-white small text-end d-none d-md-block">
                    <div class="fw-semibold"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="opacity-75">
                        <span class="badge badge-role">
                            <?= htmlspecialchars($rolesLabel !== '' ? $rolesLabel : 'SIN ROLES', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <form method="post" action="/logout" class="m-0" id="logoutForm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Salir
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="<?= $isAuthPage ? '' : 'container py-4 app-shell' ?>" role="main" id="mainContent">
    <?php require $view; ?>

    <?php if ($globalFlashError || $globalFlashSuccess || $globalFlashWarning): ?>
    <script>
    (() => {
        if (!window.Swal) return;

        const flash = <?= json_encode([
            'error' => $globalFlashError,
            'success' => $globalFlashSuccess,
            'warning' => $globalFlashWarning,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        const type = flash.error ? 'error' : (flash.warning ? 'warning' : 'success');
        const title = type === 'error'
            ? 'No fue posible completar la operación'
            : (type === 'warning' ? 'Atención' : 'Operación completada');

        const text = flash.error || flash.warning || flash.success;

        Swal.fire({
            icon: type,
            title,
            text,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#4CAF50'
        });
    })();
    </script>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<?php if (!$isAuthPage && Auth::check()): ?>
<script>
(() => {
    const logoutForm = document.getElementById('logoutForm');
    logoutForm?.addEventListener('submit', event => {
        event.preventDefault();

        if (!window.Swal) {
            if (window.confirm('¿Seguro que deseas cerrar sesión?')) logoutForm.submit();
            return;
        }

        Swal.fire({
            title:'Cerrar sesión',
            text:'¿Seguro que deseas cerrar sesión?',
            icon:'question',
            showCancelButton:true,
            confirmButtonText:'Sí, salir',
            cancelButtonText:'Cancelar',
            confirmButtonColor:'#4CAF50'
        }).then(result => {
            if (result.isConfirmed) logoutForm.submit();
        });
    });
})();
</script>
<?php endif; ?>

<?php if ($isAgent): ?>
<script>
(() => {
    const toggle = document.getElementById('agentPresenceToggle');
    const menu = document.getElementById('agentPresenceMenu');
    const dot = document.getElementById('agentPresenceDot');
    const label = document.getElementById('agentPresenceLabel');
    const csrf = <?= json_encode(Csrf::token()) ?>;
    let heartbeatSeconds = 30;
    let heartbeatTimer = null;

    if (!toggle || !menu || !dot || !label) return;

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const applyPresence = presence => {
        const safe = presence || {};
        label.textContent = safe.status_label || 'Desconectado';
        dot.style.background = safe.color || '#94a3b8';

        menu.querySelectorAll('[data-presence-code]').forEach(button => {
            button.classList.toggle(
                'active',
                button.dataset.presenceCode === safe.status_code
            );
        });
    };

    const renderStatuses = statuses => {
        menu.innerHTML = (statuses || []).map(status => `
            <button class="dropdown-item rounded-2" type="button"
                    data-presence-code="${escapeHtml(status.code)}">
                <span class="agent-presence-dot" style="background:${escapeHtml(status.color)}"></span>
                <span>${escapeHtml(status.label)}</span>
            </button>
        `).join('');

        menu.querySelectorAll('[data-presence-code]').forEach(button => {
            button.addEventListener('click', async () => {
                toggle.disabled = true;

                try {
                    const body = new URLSearchParams({
                        _csrf:csrf,
                        status_code:button.dataset.presenceCode
                    });

                    const response = await fetch('/agent/presence', {
                        method:'POST',
                        headers:{
                            'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept':'application/json'
                        },
                        body:body.toString(),
                        credentials:'same-origin',
                        cache:'no-store'
                    });

                    const data = await response.json();

                    if (!response.ok || !data.ok) {
                        throw new Error(data.message || 'No fue posible cambiar el estado.');
                    }

                    applyPresence(data.presence);

                    const instance = bootstrap.Dropdown.getInstance(toggle);
                    instance?.hide();
                } catch (error) {
                    if (window.Swal) {
                        Swal.fire({
                            icon:'error',
                            title:'No fue posible cambiar el estado',
                            text:error.message || 'Intenta nuevamente.',
                            confirmButtonColor:'#4CAF50'
                        });
                    }
                } finally {
                    toggle.disabled = false;
                }
            });
        });
    };

    const heartbeat = async () => {
        try {
            const body = new URLSearchParams({_csrf:csrf});
            const response = await fetch('/agent/heartbeat', {
                method:'POST',
                headers:{
                    'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8',
                    'Accept':'application/json'
                },
                body:body.toString(),
                credentials:'same-origin',
                cache:'no-store'
            });

            if (!response.ok) return;

            const data = await response.json();
            if (data.ok) applyPresence(data.presence);
        } catch (_) {}
    };

    const loadCurrent = async () => {
        try {
            const response = await fetch('/agent/presence', {
                headers:{'Accept':'application/json'},
                credentials:'same-origin',
                cache:'no-store'
            });

            if (!response.ok) return;

            const data = await response.json();
            if (!data.ok) return;

            heartbeatSeconds = Math.max(10, Number(data.heartbeat_seconds || 30));
            renderStatuses(data.statuses || []);
            applyPresence(data.presence);

            if (heartbeatTimer !== null) window.clearInterval(heartbeatTimer);
            heartbeatTimer = window.setInterval(heartbeat, heartbeatSeconds * 1000);
        } catch (_) {}
    };

    loadCurrent();
})();
</script>
<?php endif; ?>
</body>
</html>