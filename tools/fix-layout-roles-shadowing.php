<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/app/Views/layout.php';
$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "No se pudo leer app/Views/layout.php\n");
    exit(1);
}

$replacements = [
    '$roles = [];' => '$currentUserRoles = [];',
    '$roles = Authorization::roles(\App\Config\Database::connection(), (int)Auth::id());'
        => '$currentUserRoles = Authorization::roles(\App\Config\Database::connection(), (int)Auth::id());',
    "in_array('AGENTE', \$roles, true)"
        => "in_array('AGENTE', \$currentUserRoles, true)",
];

$changed = false;

foreach ($replacements as $from => $to) {
    if (str_contains($content, $from)) {
        $content = str_replace($from, $to, $content);
        $changed = true;
    }
}

if (!$changed) {
    if (str_contains($content, '$currentUserRoles')) {
        echo "El layout ya tiene aplicada la corrección de roles.\n";
        exit(0);
    }

    fwrite(STDERR, "No se encontraron los patrones esperados en layout.php.\n");
    exit(1);
}

if (file_put_contents($path, $content) === false) {
    fwrite(STDERR, "No se pudo actualizar app/Views/layout.php\n");
    exit(1);
}

echo "Corrección aplicada: layout ya no sobrescribe \$roles de las vistas.\n";
