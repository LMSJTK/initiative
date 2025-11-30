<?php

namespace StartupGame;

use PDO;
use PDOException;

/**
 * Database connection handler using PDO
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Initialize database configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get PDO instance (singleton pattern)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            if (empty(self::$config)) {
                throw new \RuntimeException('Database not initialized. Call Database::init() first.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['port'],
                self::$config['database'],
                self::$config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    self::$config['username'],
                    self::$config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Execute a query and return all results
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single row
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute an insert/update/delete query
     */
    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::getInstance()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get the last inserted ID
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
}
