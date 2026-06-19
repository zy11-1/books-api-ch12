<?php

use App\Controllers\BookController;
use App\Controllers\AuthController;
use App\Database;
use App\Repositories\BookRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Auth\JwtService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimit;
use Slim\App;


return function (App $app): void {
    $db = Database::get();

    // 实例化 Repository
    $auditLogRepo = new AuditLogRepository($db);

    // 实例化 Controller
    $bookCtrl = new BookController(
        new BookRepository($db),
        $auditLogRepo
    );

    $authCtrl = new AuthController(
        new UserRepository($db),
        new JwtService(),
        $auditLogRepo
    );

    // 创建 JwtService 实例（用于 AuthMiddleware）
    $jwtService = new JwtService();

    // ==========================================
    // 公开路由 (Auth)
    // ==========================================
    $app->post('/auth/register', [$authCtrl, 'register']);

    // 限流中间件
    $loginMw = new RateLimit(
        (int)($_ENV['LOGIN_RATE_LIMIT'] ?? 5),
        (int)($_ENV['LOGIN_WINDOW_SECONDS'] ?? 60),
        'login'
    );
    $app->post('/auth/login', [$authCtrl, 'login'])->add($loginMw);

    // ==========================================
    // 受保护的路由 (Books API)
    // ==========================================
    $app->group('/api', function ($g) use ($bookCtrl) {
        $g->get('/books', [$bookCtrl, 'index']);
        $g->get('/books/{id}', [$bookCtrl, 'show']);
        $g->post('/books', [$bookCtrl, 'create']);
        $g->put('/books/{id}', [$bookCtrl, 'update']);
        $g->delete('/books/{id}', [$bookCtrl, 'delete']);
    })->add(new AuthMiddleware($jwtService));
};