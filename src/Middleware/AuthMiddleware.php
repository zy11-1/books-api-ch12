<?php
namespace App\Middleware;

use App\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private JwtService $jwt) {}

    public function process(ServerRequestInterface $req, RequestHandlerInterface $h): ResponseInterface
    {
        $header = $req->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->fail('Missing or malformed token');
        }

        try {
            $payload = $this->jwt->verify($matches[1]);
        } catch (\Throwable $e) {
            error_log('[Auth] ' . $e->getMessage());
            return $this->fail('Invalid or expired token');
        }

        $req = $req->withAttribute('auth', $payload);
        return $h->handle($req);
    }

    private function fail(string $msg): ResponseInterface
    {
        $res = new Response(401);
        $res->getBody()->write(json_encode(['error' => $msg]));
        return $res
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}