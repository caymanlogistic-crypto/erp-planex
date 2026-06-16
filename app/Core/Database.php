<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private ?PDO $pdo = null;

    private string $host;
    private string $port;
    private string $database;
    private string $username;
    private string $password;
    private string $charset;

    public function __construct(array $config)
    {
        $this->host     = $config['host']     ?? '127.0.0.1';
        $this->port     = $config['port']     ?? '3306';
        $this->database = $config['database'] ?? 'erp_planex';
        $this->username = $config['username'] ?? 'root';
        $this->password = $config['password'] ?? '';
        $this->charset  = $config['charset']  ?? 'utf8mb4';
    }

    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createConnection();
        }

        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public static function fromConfig(array $config): self
    {
        return new self($config['database'] ?? []);
    }

    private function createConnection(): PDO
    {
        if ($this->database !== '') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->host,
                $this->port,
                $this->database,
                $this->charset
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $this->host,
                $this->port,
                $this->charset
            );
        }

        try {
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                (class_exists('Pdo\\Mysql') ? Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY) => true,
            ]);

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed');
        }
    }
}
