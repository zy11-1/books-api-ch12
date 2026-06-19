<?php

namespace App\Repositories;

use PDO;

final class BookRepository
{
    public function __construct(private PDO $pdo) {}

    // 获取所有书籍，支持搜索(q)和限制(limit)
    public function all(string $q = '', int $limit = 0): array
    {
        $sql = 'SELECT * FROM books';
        $args = [];

        if ($q !== '') {
            $sql .= ' WHERE title LIKE :q_title OR author LIKE :q_author';
            $args[':q_title'] = '%' . $q . '%';
            $args[':q_author'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY id ASC';
        if ($limit > 0) $sql .= ' LIMIT ' . max(1, $limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    // 根据 ID 查找单一书籍
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // 创建新书籍，记录所有者
    public function create(array $b, int $createdBy): int
    {
        $sql = 'INSERT INTO books(title, author, year, genre, created_by) VALUES(:title, :author, :year, :genre, :owner)';
        $this->pdo->prepare($sql)->execute([
            ':title' => trim($b['title']),
            ':author' => trim($b['author']),
            ':year' => (int)$b['year'],
            ':genre' => trim($b['genre'] ?? 'Uncategorised'),
            ':owner' => $createdBy,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // 更新书籍
    public function update(int $id, array $b): int
    {
        $sets = [];
        $args = [':id' => $id];

        foreach (['title', 'author', 'genre'] as $f) {
            if (array_key_exists($f, $b)) {
                $sets[] = "$f = :$f";
                $args[":$f"] = trim($b[$f]);
            }
        }

        if (array_key_exists('year', $b)) {
            $sets[] = 'year = :year';
            $args[':year'] = (int)$b['year'];
        }

        if (!$sets) return 0;

        $sql = 'UPDATE books SET ' . implode(',', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->rowCount();
    }

    // 删除书籍
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() === 1;
    }
}