<?php

namespace App\Controllers;

use App\Repositories\BookRepository;
use App\Repositories\AuditLogRepository;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookController
{
    public function __construct(
        private BookRepository $books,
        private AuditLogRepository $auditLog,
    ) {}

    public function index(Request $r, Response $s): Response
    {
        $p = $r->getQueryParams();
        $rows = $this->books->all((string)($p['q'] ?? ''), (int)($p['limit'] ?? 0));
        return $this->json($s, ['count' => count($rows), 'data' => $rows]);
    }

    public function show(Request $r, Response $s, array $a): Response
    {
        $book = $this->books->find((int)$a['id']);
        return $book ? $this->json($s, $book) : $this->json($s, ['error' => 'not found'], 404);
    }

    public function create(Request $r, Response $s): Response
    {
        $body = (array)$r->getParsedBody();
        $auth = (array)$r->getAttribute('auth', []);
        $userId = (int)($auth['sub'] ?? 0);

        $errors = (new Validator())
            ->required('title', 'author', 'year')
            ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
            ->validate($body);

        if ($errors) return $this->json($s, ['errors' => $errors], 400);

        $id = $this->books->create($body, $userId);
        $this->auditLog->record($userId, 'book.create', "book_id={$id}", "Created book: {$body['title']}");

        return $this->json($s, ['message' => 'Book created', 'data' => $this->books->find($id)], 201)
            ->withHeader('Location', '/api/books/' . $id);
    }

    public function update(Request $r, Response $s, array $args): Response
    {
        $id = (int)$args['id'];
        $book = $this->books->find($id);
        
        if (!$book) {
            return $this->json($s, ['error' => 'Not found'], 404);
        }

        // IDOR 检查
        $auth = (array)$r->getAttribute('auth', []);
        $isOwner = (int)$book['created_by'] === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';

        if (!$isOwner && !$isAdmin) {
            $this->auditLog->record(
                (int)($auth['sub'] ?? 0),
                'book.update.forbidden',
                "book_id={$id}",
                'IDOR attempt: user not owner'
            );
            return $this->json($s, ['error' => 'Forbidden'], 403);
        }

        // 验证
        $body = (array)$r->getParsedBody();
        $errors = (new Validator())
            ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
            ->validate($body, true);

        if ($errors) return $this->json($s, ['errors' => $errors], 400);

        // 执行更新
        $affected = $this->books->update($id, $body);

        // 记录审计日志
        $this->auditLog->record((int)($auth['sub'] ?? 0), 'book.update', "book_id={$id}", 'Book updated');

        // ✅ 修复：即使没有变化也返回 200
        if ($affected === 0) {
            return $this->json($s, ['message' => 'No changes detected', 'data' => $this->books->find($id)], 200);
        }

        return $this->json($s, ['message' => 'Book updated', 'data' => $this->books->find($id)]);
    }

    public function delete(Request $r, Response $s, array $a): Response
    {
        $id = (int)$a['id'];
        $book = $this->books->find($id);
        
        if (!$book) {
            return $this->json($s, ['error' => 'not found'], 404);
        }

        $auth = (array)$r->getAttribute('auth', []);
        $isOwner = (int)$book['created_by'] === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';

        if (!$isOwner && !$isAdmin) {
            $this->auditLog->record(
                (int)($auth['sub'] ?? 0),
                'book.delete.forbidden',
                "book_id={$id}",
                'IDOR attempt on delete'
            );
            return $this->json($s, ['error' => 'Forbidden'], 403);
        }

        $deleted = $this->books->delete($id);

        if ($deleted) {
            $this->auditLog->record((int)($auth['sub'] ?? 0), 'book.delete', "book_id={$id}", 'Book deleted');
        }

        return $this->json($s, ['message' => 'Book deleted']);
    }

    private function json(Response $r, $data, int $code = 200): Response
    {
        $r->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));
        return $r->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($code);
    }
}