<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class ConexaoBanco
{
    public static function create(
        string $servidor,
        int $porta,
        string $banco,
        string $usuario,
        string $senha,
        string $charset = 'utf8mb4'
    ): Connection {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $servidor,
            'port' => $porta,
            'dbname' => $banco,
            'user' => $usuario,
            'password' => $senha,
            'charset' => $charset,
        ]);
    }
}
