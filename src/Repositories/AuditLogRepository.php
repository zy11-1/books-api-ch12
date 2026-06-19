<?php

namespace App\Repositories;

use PDO;

final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 记录审计日志
     *
     * @param int|null $actorId 操作者ID（未登录为null）
     * @param string $action 操作类型（register, login.success, login.fail, book.create, book.update, book.delete, book.update.forbidden）
     * @param string|null $target 操作目标（如email或book_id）
     * @param string $detail 详细信息
     */
    public function record(?int $actorId, string $action, ?string $target, string $detail = ''): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
        
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (actor_id, action, target, ip_address, detail, occurred_at) 
             VALUES (:actor, :action, :target, :ip, :detail, NOW())'
        );
        $stmt->execute([
            ':actor' => $actorId,
            ':action' => $action,
            ':target' => $target,
            ':ip' => $ip,
            ':detail' => $detail
        ]);
    }
}