<?php
declare(strict_types=1);

use App\Auth\Auth;
use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Config\App;

/** @var string $view */

$currentUserRoles = [];
$isAgent = false;
$canViewAgentStatus = false;

if (Auth::check() && Auth::id() !== null) {
    $currentUserRoles = Authorization::roles(\App\Config\Database::connection(), (int)Auth::id());
    $isAgent = in_array('AGENTE', $currentUserRoles, true);
    $canViewAgentStatus = Authorization::hasPermission(
        \App\Config\Database::connection(),
        (int)Auth::id(),
        'QUEUE_VIEW'
    );
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(App::name(), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --primary:#4CAF50;
            --primary-dark:#3f9143;
            --primary-soft:#eef8ef;
            --ink:#1f2937;
            --muted:#64748b;
            --border:#e5e7eb;
            --surface:#ffffff;
            --background:#f4f7fb;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--background); color:var(--ink); }
        header {
            background:var(--primary);
            color:#fff;
            min-height:64px;
            padding:10px 24px;
            display:flex;
            gap:22px;
            align-items:center;
            justify-content:space-between;
            box-shadow:0 2px 12px rgba(15,23,42,.12);
        }
        .brand { font-weight:750; letter-spacing:.2px; white-space:nowrap; }
        .header-right { display:flex; align-items:center; gap:12px; }
        nav { display:flex; gap:4px; flex-wrap:wrap; }
        nav a {
            padding:9px 10px;
            border-radius:8px;
            text-decoration:none;
            color:rgba(255,255,255,.94);
            font-weight:650;
        }
        nav a:hover { background:rgba(255,255,255,.14); }
        main { max-width:1240px; margin:30px auto; padding:0 18px; }
        .card {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:16px;
            padding:22px;
            box-shadow:0 6px 20px rgba(15,23,42,.045);
        }
        .btn {
            border:0;
            border-radius:9px;
            padding:10px 14px;
            cursor:pointer;
            font-weight:650;
            text-decoration:none;
            display:inline-block;
        }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-light { background:#eef2f7; color:#334155; }
        .btn-header { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.3); }
        label { display:block; font-weight:650; margin:12px 0 5px; }
        input, select { width:100%; padding:10px 11px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:11px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
        th { background:#f8fafc; font-size:.9rem; color:#475569; }
        .alert { padding:12px 14px; border-radius:9px; margin-bottom:14px; background:#fee2e2; color:#991b1b; }
        .muted { color:var(--muted); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
        .page-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap; margin-bottom:20px; }
        .page-heading h1 { margin:0 0 5px; font-size:1.8rem; }
        .refresh-note { color:var(--muted); font-size:.88rem; }
        .metric-grid { display:grid; grid-template-columns:repeat(4,minmax(150px,1fr)); gap:14px; }
        .metric-card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:18px; box-shadow:0 4px 16px rgba(15,23,42,.04); }
        .metric-card span { display:block; color:var(--muted); font-size:.88rem; }
        .metric-card strong { display:block; margin-top:6px; font-size:1.85rem; }
        .presence { position:relative; }
        .presence-toggle { min-width:170px; display:flex; align-items:center; justify-content:flex-start; gap:8px; }
        .presence-dot { width:10px; height:10px; border-radius:999px; display:inline-block; flex:0 0 auto; }
        .presence-menu {
            display:none;
            position:absolute;
            right:0;
            top:calc(100% + 8px);
            z-index:50;
            width:245px;
            background:#fff;
            border:1px solid var(--border);
            border-radius:12px;
            box-shadow:0 16px 40px rgba(15,23,42,.16);
            padding:7px;
        }
        .presence.open .presence-menu { display:block; }
        .presence-option {
            width:100%;
            border:0;
            background:transparent;
            padding:10px;
            border-radius:8px;
            display:flex;
            align-items:center;
            gap:10px;
            cursor:pointer;
            text-align:left;
            color:var(--ink);
        }
        .presence-option:hover, .presence-option.active { background:var(--primary-soft); }
        .presence-badge { display:inline-flex; align-items:center; gap:7px; }
        .user-name { color:rgba(255,255,255,.92); font-size:.9rem; white-space:nowrap; }
        @media (max-width:900px) {
            header { align-items:flex-start; flex-direction:column; }
            .header-right { width:100%; flex-wrap:wrap; }
            .metric-grid { grid-template-columns:repeat(2,minmax(140px,1fr)); }
        }
    </style>
</head>
<body>
<header>
    <div class="brand"><?= htmlspecialchars(App::name(), ENT_QUOTES, 'UTF-8') ?></div>

    <?php if (Auth::check()): ?>
        <div class="header-right">
            <nav>
                <a href="/">Inicio</a>
                <a href="/cases">Casos</a>
                <a href="/imports">Cargas</a>
                <a href="/admin/users">Usuarios</a>
                <a href="/admin/structures">Estructuras</a>
                <a href="/admin/queues">Colas</a>
                <?php if ($canViewAgentStatus): ?>
                    <a href="/supervisor/agents">Estado agentes</a>
                <?php endif; ?>
            </nav>

            <?php if ($isAgent): ?>
                <div class="presence" id="agentPresenceWidget">
                    <button class="btn btn-header presence-toggle" type="button" id="agentPresenceToggle">
                        <span class="presence-dot" id="agentPresenceDot" style="background:#94a3b8"></span>
                        <span id="agentPresenceLabel">Desconectado</span>
                        <span aria-hidden="true">▾</span>
                    </button>
                    <div class="presence-menu" id="agentPresenceMenu"></div>
                </div>
            <?php endif; ?>

            <span class="user-name"><?= htmlspecialchars((string)(Auth::user()['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>

            <form method="post" action="/logout" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn btn-header" type="submit">Salir</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<main><?php require $view; ?></main>

<?php if ($isAgent): ?>
<script>
(() => {
    const widget = document.getElementById('agentPresenceWidget');
    const toggle = document.getElementById('agentPresenceToggle');
    const menu = document.getElementById('agentPresenceMenu');
    const dot = document.getElementById('agentPresenceDot');
    const label = document.getElementById('agentPresenceLabel');
    const csrf = <?= json_encode(Csrf::token()) ?>;
    let heartbeatSeconds = 30;

    if (!widget || !toggle || !menu || !dot || !label) return;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');

    const applyPresence = (presence) => {
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

    const renderStatuses = (statuses) => {
        menu.innerHTML = (statuses || []).map(status => `
            <button class="presence-option" type="button" data-presence-code="${escapeHtml(status.code)}">
                <span class="presence-dot" style="background:${escapeHtml(status.color)}"></span>
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
                    widget.classList.remove('open');
                } catch (error) {
                    window.alert(error.message || 'No fue posible cambiar el estado.');
                } finally {
                    toggle.disabled = false;
                }
            });
        });
    };

    const loadCurrent = async () => {
        const response = await fetch('/agent/presence', {
            headers:{'Accept':'application/json'},
            credentials:'same-origin',
            cache:'no-store'
        });

        if (!response.ok) return;

        const data = await response.json();
        if (!data.ok) return;

        heartbeatSeconds = Number(data.heartbeat_seconds || 30);
        renderStatuses(data.statuses || []);
        applyPresence(data.presence);
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

    toggle.addEventListener('click', () => widget.classList.toggle('open'));

    document.addEventListener('click', event => {
        if (!widget.contains(event.target)) widget.classList.remove('open');
    });

    loadCurrent().finally(() => {
        window.setInterval(
            heartbeat,
            Math.max(10, heartbeatSeconds) * 1000
        );
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') heartbeat();
    });
})();
</script>
<?php endif; ?>
</body>
</html>
