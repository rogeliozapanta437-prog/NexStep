<?php

session_start();

// ========================================
// PROTECT PAGE
// ========================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login/login.php");
    exit;
}

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {

    header("Location: ../admin/dashboard.php");
    exit;
}


// ========================================
// DATABASE
// ========================================

require_once '../database/config.php';

$pdo = getConnection();

$userId = (int) $_SESSION['user_id'];

$message = '';
$error = '';


// ========================================
// LOAD CURRENT PASSWORD
// ========================================

$userStmt = $pdo->prepare(
    "SELECT password
     FROM users
     WHERE id = :id"
);

$userStmt->bindValue(
    ':id',
    $userId,
    PDO::PARAM_INT
);

$userStmt->execute();

$user = $userStmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$user) {

    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// CHANGE PASSWORD
// ========================================

if (isset($_POST['change_password'])) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    if (
        empty($currentPassword) ||
        empty($newPassword) ||
        empty($confirmPassword)
    ) {

        $error =
            'Please complete all password fields.';

    } elseif (
        !password_verify(
            $currentPassword,
            $user['password']
        )
    ) {

        $error =
            'Current password is incorrect.';

    } elseif (
        strlen($newPassword) < 6
    ) {

        $error =
            'New password must be at least 6 characters.';

    } elseif (
        !preg_match('/[a-z]/', $newPassword)
    ) {

        $error =
            'New password must contain at least one lowercase letter.';

    } elseif (
        !preg_match('/[0-9]/', $newPassword)
    ) {

        $error =
            'New password must contain at least one number.';

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $error =
            'New password and confirmation do not match.';

    } else {

        $hashedPassword =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

        $updateStmt = $pdo->prepare(
            "UPDATE users
             SET password = :password
             WHERE id = :id"
        );

        $updateStmt->bindValue(
            ':password',
            $hashedPassword
        );

        $updateStmt->bindValue(
            ':id',
            $userId,
            PDO::PARAM_INT
        );

        $updateStmt->execute();

        $message =
            'Password changed successfully.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>NexStep | Change Password</title>

    <link
        rel="stylesheet"
        href="/webapp/account/account.css?v=2"
    >

</head>

<body>


<!-- ========================================
     HEADER
======================================== -->

<header class="shop-header">

    <a
        href="/webapp/homepage/index.php"
        class="shop-logo"
    >
        <img
            src="/webapp/homepage/images/nexstep-logo.png"
            alt="NexStep"
            width="200"
            style="width:200px; max-width:200px; height:auto; display:block;"
        >
    </a>

    <nav>
        <a href="/webapp/products/products.php?category=Men">MEN</a>
        <a href="/webapp/products/products.php?category=Women">WOMEN</a>
        <a href="/webapp/products/products.php?category=Kids">KIDS</a>
        <a href="/webapp/homepage/index.php#brands">BRANDS</a>
        <a href="/webapp/products/products.php?filter=new">NEW ARRIVALS</a>
        <a href="/webapp/products/products.php?filter=sale">SALE</a>
    </nav>

    <div class="header-actions">
        <a href="/webapp/account/account.php">MY ACCOUNT</a>

        <a
            href="/webapp/homepage/index.php"
            class="back-home"
        >
            HOME
        </a>
    </div>

</header>


<!-- ========================================
     PAGE HEADING
======================================== -->

<section class="account-heading">

    <a
        href="/webapp/account/account.php"
        class="back-link"
    >
        ← BACK TO MY ACCOUNT
    </a>

    <p class="eyebrow">
        ACCOUNT SECURITY
    </p>

    <h1>
        CHANGE PASSWORD
    </h1>

    <p class="heading-copy">
        Choose a secure password for your NexStep account.
    </p>

</section>


<main class="password-page">


    <?php if (!empty($message)): ?>

        <div class="account-message success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="account-message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <section class="password-card">

        <div class="password-card-heading">

            <p class="section-label">
                PASSWORD SETTINGS
            </p>

            <h2>
                Update Password
            </h2>

            <p>
                Your new password must be at least
                6 characters and contain a lowercase
                letter and a number.
            </p>

        </div>


        <form
            method="POST"
            class="password-form"
        >

            <div class="password-field">

                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <div class="password-field">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="password-field">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                name="change_password"
                class="change-password-button"
            >
                CHANGE PASSWORD
            </button>

        </form>

    </section>


</main>


</body>
</html>
