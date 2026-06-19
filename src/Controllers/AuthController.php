<?php

namespace App\Controllers;

use App\Auth\JwtService;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private UserRepository $users,
        private JwtService $jwt,
        private AuditLogRepository $auditLog,
    ) {}

    // 注册接口
    public function register(Request $r, Response $s): Response
    {
        $b = (array) $r->getParsedBody();
        $errors = [];

        if (empty($b['name']) || mb_strlen($b['name']) < 2) $errors['name'] = 'min 2 chars';
        if (empty($b['email']) || !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'invalid email';
        if (empty($b['password']) || mb_strlen($b['password']) < 6) $errors['password'] = 'min 6 chars';

        if ($errors) return $this->json($s, ['errors' => $errors], 400);
        if ($this->users->emailExists($b['email'])) return $this->json($s, ['error' => 'Email already registered'], 409);

        // 核心：使用 password_hash 加密密码后存入数据库
        $id = $this->users->create(
            $b['name'],
            $b['email'],
            password_hash($b['password'], PASSWORD_DEFAULT)
        );

        // 记录注册日志
        $this->auditLog->record($id, 'register', $b['email'], 'New user registered');

        return $this->json($s, ['message' => 'Registered', 'user' => $this->users->findById($id)], 201);
    }

    // 登录接口
    public function login(Request $r, Response $s): Response
    {
        $b = (array) $r->getParsedBody();
        $email = $b['email'] ?? '';
        $u = $this->users->findByEmail($email);

        // 核心：验证邮箱是否存在，且密码哈希是否匹配
        if (!$u || !password_verify($b['password'] ?? '', $u['password_hash'])) {
            // 记录失败日志
            $this->auditLog->record(null, 'login.fail', $email, 'Invalid credentials');
            return $this->json($s, ['error' => 'Invalid credentials'], 401);
        }

        // 核心：签发 JWT，并将 role 和 email 放入 payload
        $token = $this->jwt->issue((int)$u['id'], ['role' => $u['role'], 'email' => $u['email']]);

        // 记录成功日志
        $this->auditLog->record((int)$u['id'], 'login.success', $u['email'], 'User logged in successfully');

        return $this->json($s, [
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttl(),
            'access_token' => $token,
        ]);
    }

    // 获取当前登录用户信息
    public function me(Request $r, Response $s): Response
    {
        // 从请求属性中获取 middleware 附加的 auth 信息
        $auth = (array) $r->getAttribute('auth', []);
        $u = $this->users->findById((int)($auth['sub'] ?? 0));

        return $u ? $this->json($s, $u) : $this->json($s, ['error' => 'Not found'], 404);
    }

    // JSON 输出辅助方法（XSS防护）
    private function json(Response $s, $d, int $c = 200): Response
    {
        $s->getBody()->write(json_encode(
            $d,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));
        return $s->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($c);
    }
}