<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response; // 注意：这里引入 Slim 的 Response 类用于创建 200 响应

final class Cors implements MiddlewareInterface
{
    private array $allowed;

    // 【新增】构造函数：从 .env 读取白名单
    public function __construct()
    {
        $list = (string)($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        // 将逗号分隔的字符串拆分成数组，例如 ['http://localhost:5173', 'http://localhost:8080']
        $this->allowed = array_filter(array_map('trim', explode(',', $list)));
    }

    public function process(ServerRequestInterface $req, RequestHandlerInterface $h): ResponseInterface
    {
        // 处理 OPTIONS 预检请求 (Preflight)
        if ($req->getMethod() === 'OPTIONS') {
            $res = new Response(200);
            return $this->withCors($req, $res);
        }

        // 正常请求继续往下走
        return $this->withCors($req, $h->handle($req));
    }

    // 【修改】核心逻辑：根据请求的 Origin 动态设置响应头
    private function withCors(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $origin = $req->getHeaderLine('Origin'); // 获取前端发来的 Origin 头
        $allow = '*';
        $creds = false;

        // 如果前端传来的 Origin 在我们的白名单里，就精准放行
        if ($this->allowed && in_array($origin, $this->allowed, true)) {
            $allow = $origin; // 返回具体的 Origin，而不是 *
            $creds = true;    // 允许携带凭证 (如 JWT Token)
        }

        $res = $res
            ->withHeader('Access-Control-Allow-Origin', $allow)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin'); // 告诉缓存服务器，响应会根据 Origin 变化

        // 如果在白名单内，加上允许凭证的头
        if ($creds) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $res;
    }
}