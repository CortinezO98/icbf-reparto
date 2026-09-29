<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Authorization;
use PDO;

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        $roles = Authorization::roles($this->pdo, (int)Auth::id());

        $view = dirname(__DIR__) . '/Views/dashboard/index.php';
        require dirname(__DIR__) . '/Views/layout.php';
    }
}
