<?php

require_once '../database/config.php';
require_once '../validation.php';

if (isset($_POST['signup'])) {

    $validation = validateSignupInput($_POST);

    $errors = $validation['errors'];
    $data = $validation['data'];

    if (!empty($errors)) {
        $errorMessage = urlencode(implode(' ', $errors));

        header("Location: signup.php?error=$errorMessage");
        exit;
    }

    $username = $data['username'];
    $email = $data['email'];
    $password = $data['password'];

    try {

        $pdo = getConnection();

        // Check if username or email already exists
        $checkUser = $pdo->prepare(
            "SELECT id FROM users 
             WHERE username = :username 
             OR email = :email"
        );

        $checkUser->bindValue(':username', $username);
        $checkUser->bindValue(':email', $email);

        $checkUser->execute();

        if ($checkUser->fetch()) {
            header(
                "Location: signup.php?error=" .
                urlencode("Username or email already exists.")
            );
            exit;
        }

        // Hash password before saving
        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        // Public signup is always customer
        $role = 'customer';

        $stmt = $pdo->prepare(
            "INSERT INTO users
            (username, email, password, role)
            VALUES
            (:username, :email, :password, :role)"
        );

        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':role', $role);

        $stmt->execute();

        header(
            "Location: ../login/login.php?success=" .
            urlencode("Account created successfully. You can now login.")
        );
        exit;

    } catch (PDOException $e) {

        die(
            "Signup failed: " .
            $e->getMessage()
        );
    }

} else {

    header("Location: signup.php");
    exit;
}