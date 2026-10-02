<?php
declare(strict_types=1);

namespace App\Http;

final class ErrorResponse
{
    public static function render(
        int $status,
        string $title,
        string $message,
        string $actionUrl = '/',
        string $actionLabel = 'Volver'
    ): never {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
        $statusLabel = htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $safeTitle . ' · ICBF Reparto</title>
<style>
:root{--brand:#4caf50;--brand-dark:#3d9642;--text:#172033;--muted:#667085;--border:#e4e7ec;--bg:#f5f7f9}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--text)}
.error-wrap{width:min(620px,calc(100% - 32px));text-align:center}.brand{display:inline-flex;align-items:center;gap:9px;color:var(--brand-dark);font-weight:800;font-size:1.05rem;margin-bottom:18px}
.card{background:#fff;border:1px solid var(--border);border-radius:18px;padding:38px 34px;box-shadow:0 10px 35px rgba(16,24,40,.07)}
.icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;display:grid;place-items:center;background:#eef8ef;color:var(--brand-dark);font-size:2rem;font-weight:800}
.code{display:inline-block;margin-bottom:10px;padding:4px 10px;border-radius:999px;background:#f2f4f7;color:#667085;font-size:.75rem;font-weight:700}
h1{margin:0 0 10px;font-size:1.65rem}p{margin:0 auto 24px;max-width:500px;color:var(--muted);line-height:1.6}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:9px;background:var(--brand);color:#fff;text-decoration:none;font-weight:700}.btn:hover{background:var(--brand-dark)}
.help{margin-top:18px;color:#98a2b3;font-size:.78rem}
</style>
</head>
<body>
<main class="error-wrap">
<div class="brand"><span>◈</span> ICBF Reparto</div>
<section class="card">
<div class="icon">!</div>
<div class="code">Error ' . $statusLabel . '</div>
<h1>' . $safeTitle . '</h1>
<p>' . nl2br($safeMessage) . '</p>
<a class="btn" href="' . $safeUrl . '">' . $safeLabel . '</a>
<div class="help">Si el problema continúa, actualiza la página o vuelve a iniciar sesión.</div>
</section>
</main>
</body>
</html>';

        exit;
    }
}
