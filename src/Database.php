<?php
namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo) return self::$pdo;

        // 1. 优先读取 Railway 的 DATABASE_URL
        $databaseUrl = $_ENV['DATABASE_URL'] ?? null;

        if ($databaseUrl) {
            // 解析 mysql://user:pass@host:port/dbname
            $parts = parse_url($databaseUrl);
            
            $host = $parts['host'];
            $user = $parts['user'];
            $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            $db   = substr($parts['path'], 1); // 去掉开头的 /
            $port = isset($parts['port']) ? $parts['port'] : 3306;

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        } else {
            // 2. 兼容本地开发环境 (Laragon)
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $_ENV['DB_NAME'] ?? 'books_api',
                $_ENV['DB_CHARSET'] ?? 'utf8mb4'
            );
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('[DB] ' . $e->getMessage());
            throw new \RuntimeException('Database connection failed', 500, $e);
        }

        return self::$pdo;
    }
}
