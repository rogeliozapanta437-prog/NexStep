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


// ========================================
// LOAD USER
// ========================================

$userStmt = $pdo->prepare(
    "SELECT
        id,
        username,
        email,
        role
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


// ========================================
// USER NOT FOUND
// ========================================

if (!$user) {
    session_destroy();
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// ACCOUNT SUMMARY
// ========================================

$orderSummaryStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(
            SUM(
                CASE
                    WHEN status != 'Cancelled'
                    THEN total_amount
                    ELSE 0
                END
            ),
            0
        ) AS total_spent
     FROM orders
     WHERE user_id = :user_id"
);

$orderSummaryStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$orderSummaryStmt->execute();

$orderSummary = $orderSummaryStmt->fetch(
    PDO::FETCH_ASSOC
);


// ========================================
// WISHLIST COUNT
// ========================================

$wishlistStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM wishlist
     WHERE user_id = :user_id"
);

$wishlistStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$wishlistStmt->execute();

$wishlistCount =
    (int) $wishlistStmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>NexStep | My Account</title>

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
        <a href="/webapp/wishlist/wishlist.php">WISHLIST</a>
        <a href="/webapp/cart/cart.php">CART</a>

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
        href="/webapp/homepage/index.php"
        class="back-link"
    >
        ← BACK TO HOME
    </a>

    <p class="eyebrow">
        CUSTOMER ACCOUNT
    </p>

    <h1>
        MY ACCOUNT
    </h1>

    <p class="heading-copy">
        Manage your account, orders and saved products.
    </p>

</section>


<main class="account-page">


    <!-- ========================================
         ACCOUNT INFORMATION
    ======================================== -->

    <section class="profile-card">

        <div class="profile-top">

            <div class="profile-avatar">
                <?= htmlspecialchars(
                    strtoupper(
                        substr(
                            $user['username'],
                            0,
                            1
                        )
                    )
                ) ?>
            </div>

            <div>

                <p class="section-label">
                    ACCOUNT INFORMATION
                </p>

                <h2>
                    <?= htmlspecialchars(
                        $user['username']
                    ) ?>
                </h2>

                <span>
                    NexStep Customer
                </span>

            </div>

        </div>


        <div class="profile-details">

            <div>
                <span>CUSTOMER ID</span>

                <strong>
                    #<?= (int) $user['id'] ?>
                </strong>
            </div>

            <div>
                <span>USERNAME</span>

                <strong>
                    <?= htmlspecialchars(
                        $user['username']
                    ) ?>
                </strong>
            </div>

            <div>
                <span>EMAIL ADDRESS</span>

                <strong>
                    <?= htmlspecialchars(
                        $user['email']
                    ) ?>
                </strong>
            </div>

        </div>

    </section>


    <!-- ========================================
         ACCOUNT SUMMARY
    ======================================== -->

    <section class="summary-grid">

        <a
            href="/webapp/orders/orders.php"
            class="summary-card"
        >

            <p>
                TOTAL ORDERS
            </p>

            <h2>
                <?= (int) $orderSummary['total_orders'] ?>
            </h2>

            <span>
                VIEW ORDERS →
            </span>

        </a>


        <div class="summary-card">

            <p>
                TOTAL SPENT
            </p>

            <h2>
                ₱<?= number_format(
                    (float) $orderSummary['total_spent'],
                    2
                ) ?>
            </h2>

            <span>
                EXCLUDES CANCELLED ORDERS
            </span>

        </div>


        <a
            href="/webapp/wishlist/wishlist.php"
            class="summary-card"
        >

            <p>
                WISHLIST ITEMS
            </p>

            <h2>
                <?= $wishlistCount ?>
            </h2>

            <span>
                VIEW WISHLIST →
            </span>

        </a>

    </section>


    <!-- ========================================
         ACCOUNT OPTIONS
    ======================================== -->

    <section class="account-options">

        <div class="options-heading">

            <p class="section-label">
                ACCOUNT OPTIONS
            </p>

            <h2>
                Quick Links
            </h2>

        </div>


        <div class="options-list">

            <a href="/webapp/orders/orders.php">
                <div>
                    <strong>MY ORDERS</strong>
                    <span>View your order history and status.</span>
                </div>

                
            </a>


            <a href="/webapp/wishlist/wishlist.php">
                <div>
                    <strong>MY WISHLIST</strong>
                    <span>View the shoes you saved.</span>
                </div>

                
            </a>


            <a href="/webapp/products/products.php">
                <div>
                    <strong>CONTINUE SHOPPING</strong>
                    <span>Browse the NexStep collection.</span>
                </div>

                
            </a>


            <a href="/webapp/account/change_password.php">
                <div>
                    <strong>CHANGE PASSWORD</strong>
                    <span>Update your account password.</span>
                </div>

                
            </a>


            <a
                href="/webapp/logout/logout.php"
                class="logout-option"
            >
                <div>
                    <strong>LOGOUT</strong>
                    <span>Sign out of your NexStep account.</span>
                </div>

               
            </a>

        </div>

    </section>


</main>


</body>
</html>
