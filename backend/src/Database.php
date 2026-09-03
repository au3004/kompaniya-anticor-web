<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::get('DB_NAME', 'kompaniya_anticor');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');
        $charset = Config::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            Response::error('Bazaga ulanishda xatolik', 'DB_ERROR', 500);
        }

        return self::$pdo;
    }
}
