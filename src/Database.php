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

        // ✅ 1. 优先尝试 MySQL (生产环境标准做法)
        if (in_array('mysql', PDO::getAvailableDrivers())) {
            $databaseUrl = $_ENV['DATABASE_URL'] ?? null;
            
            if ($databaseUrl) {
                $parts = parse_url($databaseUrl);
                $host = $parts['host'];
                $user = $parts['user'];
                $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
                $db   = substr($parts['path'], 1);
                $port = isset($parts['port']) ? $parts['port'] : 3306;
                
                $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
                
                try {
                    self::$pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    error_log('[DB] Connected to MySQL successfully');
                    return self::$pdo; // MySQL 成功，直接返回
                } catch (PDOException $e) {
                    error_log('[DB] MySQL connection failed: ' . $e->getMessage());
                    // MySQL 失败，继续往下走尝试 SQLite
                }
            }
        }

        // ✅ 2. 降级使用 SQLite (兼容 Railway 无 pdo_mysql 的情况)
        if (in_array('sqlite', PDO::getAvailableDrivers())) {
            $dbPath = '/app/database.sqlite';
            if (!file_exists($dbPath)) {
                touch($dbPath);
                chmod($dbPath, 0777);
            }
            
            $dsn = "sqlite:$dbPath";
            
            try {
                self::$pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$pdo->exec("PRAGMA foreign_keys = ON");
                error_log('[DB] Using SQLite fallback');
                
                // 如果是第一次用 SQLite，自动建表
                self::$pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL UNIQUE,
                    password TEXT NOT NULL,
                    role TEXT DEFAULT 'user'
                )");
                
                return self::$pdo;
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed (both MySQL and SQLite): ' . $e->getMessage(), 500, $e);
            }
        }

        throw new \RuntimeException('No database driver available (need mysql or sqlite)');
    }
}
