<?php
declare(strict_types=1);

use App\Auth\Auth;
use App\Auth\Csrf;
use App\Config\App;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(App::name(), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fb; color: #1f2937; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 14px 24px; display:flex; align-items:center; justify-content:space-between; }
        nav a { margin-right: 14px; text-decoration:none; color:#1f4e78; font-weight:600; }
        main { max-width: 1180px; margin: 30px auto; padding: 0 18px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow:0 5px 18px rgba(15,23,42,.04); }
        .btn { border:0; border-radius:9px; padding:10px 14px; cursor:pointer; font-weight:600; text-decoration:none; display:inline-block; }
        .btn-primary { background:#1f4e78; color:#fff; }
        .btn-light { background:#eef2f7; color:#334155; }
        label { display:block; font-weight:600; margin:12px 0 5px; }
        input, select { width:100%; padding:10px 11px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; }
        th { background:#f8fafc; }
        .alert { padding:12px 14px; border-radius:9px; margin-bottom:14px; background:#fee2e2; color:#991b1b; }
        .muted { color:#64748b; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
    </style>
</head>
<body>
<header>
    <div>
        <strong><?= htmlspecialchars(App::name(), ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <?php if (Auth::check()): ?>
        <div>
            <nav style="display:inline-block">
                <a href="/">Inicio</a>
                <a href="/admin/users">Usuarios</a>
            </nav>
            <span class="muted"><?= htmlspecialchars((string)(Auth::user()['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <form method="post" action="/logout" style="display:inline;margin-left:10px">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn btn-light" type="submit">Salir</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main>
    <?php require $view; ?>
</main>
</body>
</html>
