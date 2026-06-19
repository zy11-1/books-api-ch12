<?php

/* 
 * 【微调 1】：注释掉原生的 CORS 头。
 * 原因：实验手册 Part F 要求使用 Cors 中间件，原生 header 会导致响应头重复而报错。
 * 代码保留在此，不删除。
 *
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
*/

// 开启错误显示
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

// 添加中间件
// 【微调 2】：严格按照实验手册 Part D Step 6，在最前面加上 SecurityHeaders
$app->add(new App\Middleware\SecurityHeaders()); // ← added FIRST so it runs LAST

$app->add(new App\Middleware\JsonBodyParser());
$app->add(new App\Middleware\Cors());

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

(require __DIR__ . '/../src/routes.php')($app);


// ✅ 关键修复：拦截所有 OPTIONS 预检请求，防止 Slim 报 405 或进入 Auth 中间件报 401
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->run();