<?php

function validateRequired(string $value, string $fieldName): ?string
{
    if (empty(trim($value))) {
        return $fieldName . " is required.";
    }

    return null;
}


function validateUsername(string $username): ?string
{
    if (strlen($username) < 3) {
        return "Username must be at least 3 characters.";
    }

    return null;
}


function validateEmail(string $email): ?string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }

    return null;
}


function validatePasswordStrength(string $password): ?string
{
    $requirements = [];

    if (strlen($password) < 8) {
        $requirements[] = "at least 8 characters";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $requirements[] = "at least one lowercase letter";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $requirements[] = "at least one uppercase letter";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $requirements[] = "at least one number";
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $requirements[] = "at least one special character";
    }

    if (!empty($requirements)) {
        return "Password must contain: " . implode(", ", $requirements) . ".";
    }

    return null;
}


function validateSignupInput(array $input): array
{
    $errors = [];

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';


    $error = validateRequired($username, "Username");

    if ($error) {
        $errors['username'] = $error;
    } else {
        $error = validateUsername($username);

        if ($error) {
            $errors['username'] = $error;
        }
    }


    $error = validateRequired($email, "Email");

    if ($error) {
        $errors['email'] = $error;
    } else {
        $error = validateEmail($email);

        if ($error) {
            $errors['email'] = $error;
        }
    }


    $error = validateRequired($password, "Password");

    if ($error) {
        $errors['password'] = $error;
    } else {
        $error = validatePasswordStrength($password);

        if ($error) {
            $errors['password'] = $error;
        }
    }


    $error = validateRequired($confirmPassword, "Confirm Password");

    if ($error) {
        $errors['confirm_password'] = $error;
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = "Passwords do not match.";
    }


    return [
        'errors' => $errors,

        'data' => [
            'username' => htmlspecialchars($username),
            'email' => $email,
            'password' => $password
        ]
    ];
}


function validateLoginInput(array $input): array
{
    $errors = [];

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';


    $error = validateRequired($username, "Username");

    if ($error) {
        $errors['username'] = $error;
    }


    $error = validateRequired($password, "Password");

    if ($error) {
        $errors['password'] = $error;
    }


    return [
        'errors' => $errors,

        'data' => [
            'username' => htmlspecialchars($username),
            'password' => $password
        ]
    ];
}