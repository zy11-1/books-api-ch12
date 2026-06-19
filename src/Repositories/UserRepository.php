<?php
namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    // 登录时通过邮箱查找用户（包含 password_hash 用于验证）
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :e'
        );
        $stmt->execute([':e' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // 通过 ID 查找用户（用于 /auth/me 接口）
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // 注册时创建新用户
    public function create(string $n, string $e, string $hash, string $role = 'member'): int
    {
        $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:n, :e, :h, :r)'
        )->execute([
            ':n' => trim($n),
            ':e' => mb_strtolower(trim($e)),
            ':h' => $hash,
            ':r' => $role
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // 注册时检查邮箱是否已存在
    public function emailExists(string $e): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :e');
        $stmt->execute([':e' => mb_strtolower(trim($e))]);
        return (bool) $stmt->fetchColumn();
    }
}