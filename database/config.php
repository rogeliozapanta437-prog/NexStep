<?php

function getConnection(): PDO
{
    $host = 'localhost';
    $db = 'nexstep_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        return $pdo;

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}