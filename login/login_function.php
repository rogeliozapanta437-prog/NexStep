<?php

session_start();

require_once '../database/config.php';
require_once '../validation.php';

if (isset($_POST['login'])) {

    $validation = validateLoginInput($_POST);

    $errors = $validation['errors'];
    $data = $validation['data'];

    if (!empty($errors)) {
        $errorMessage = urlencode(implode(' ', $errors));

        header("Location: login.php?error=$errorMessage");
        exit;
    }

    $username = $data['username'];
    $password = $data['password'];

    try {

        $pdo = getConnection();

        $stmt = $pdo->prepare(
            "SELECT id, username, password, role
             FROM users
             WHERE username = ?"
        );

        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {

            header(
                "Location: login.php?error=" .
                urlencode("Invalid username or password.")
            );

            exit;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {

            header("Location: ../admin/dashboard.php");
            exit;
        }

        header("Location: ../homepage/index.php");
        exit;

    } catch (PDOException $e) {

        die(
            "Login failed: " .
            $e->getMessage()
        );
    }

} else {

    header("Location: login.php");
    exit;
}