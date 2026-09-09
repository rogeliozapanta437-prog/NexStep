<?php

session_start();

// ========================================
// PROTECT ADMIN PAGE
// ========================================

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../../login/login.php");
    exit;
}


// ========================================
// DATABASE
// ========================================

require_once '../../database/config.php';

$pdo = getConnection();


// ========================================
// CHECK CUSTOMER ID
// ========================================

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {
    header("Location: customers.php");
    exit;
}

$customerId = (int) $_GET['id'];


// ========================================
// LOAD CUSTOMER
// ========================================

$customerStmt = $pdo->prepare(
    "SELECT
        id,
        username,
        email,
        role
     FROM users
     WHERE id = :id
     AND role = :role"
);

$customerStmt->bindValue(
    ':id',
    $customerId,
    PDO::PARAM_INT
);

$customerStmt->bindValue(
    ':role',
    'customer'
);

$customerStmt->execute();

$customer = $customerStmt->fetch(
    PDO::FETCH_ASSOC
);


// ========================================
// CUSTOMER NOT FOUND
// ========================================

if (!$customer) {
    header("Location: customers.php");
    exit;
}


// ========================================
// LOAD CUSTOMER ORDERS
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT
        id,
        full_name,
        total_amount,
        status,
        payment_method,
        created_at
     FROM orders
     WHERE user_id = :user_id
     ORDER BY created_at DESC"
);

$orderStmt->bindValue(
    ':user_id',
    $customerId,
    PDO::PARAM_INT
);

$orderStmt->execute();

$orders = $orderStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// CUSTOMER ORDER SUMMARY
// ========================================

$totalOrders = count($orders);

$totalSpent = 0;

foreach ($orders as $order) {

    if ($order['status'] !== 'Cancelled') {

        $totalSpent +=
            (float) $order['total_amount'];
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

    <title>
        NexStep Admin | Customer #<?= $customerId ?>
    </title>

    <link
        rel="stylesheet"
        href="/webapp/admin/admin.css?v=9"
    >

</head>

<body>

<div class="admin-layout">


    <!-- ========================================
         SIDEBAR
    ======================================== -->

    <aside class="sidebar">

        <div class="sidebar-logo">

            <img
                src="/webapp/homepage/images/nexstep-logo.png"
                alt="NexStep Logo"
            >

            <span>
                ADMIN
            </span>

        </div>


        <nav class="sidebar-menu">

            <a href="../dashboard.php">
                Dashboard
            </a>

            <a href="../products/products.php">
                Products
            </a>

            <a href="../orders/orders.php">
                Orders
            </a>

            <a
                href="customers.php"
                class="active"
            >
                Customers
            </a>

            <a href="../inventory/inventory.php">
                Inventory
            </a>

            <a href="../reports/reports.php">
                Reports
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../../homepage/index.php">
                View Store
            </a>

            <a href="../../logout/logout.php">
                Logout
            </a>

        </div>

    </aside>


    <!-- ========================================
         MAIN CONTENT
    ======================================== -->

    <main class="main-content">

        <header class="topbar">

            <div>

                <p class="page-label">
                    CUSTOMER DETAILS
                </p>

                <h1>
                    Customer #<?= $customerId ?>
                </h1>

            </div>


            <div class="admin-profile">

                <div class="profile-icon">
                    A
                </div>

                <div>

                    <strong>
                        Admin
                    </strong>

                    <span>
                        Administrator
                    </span>

                </div>

            </div>

        </header>


        <div class="customer-back-row">

            <a
                class="customer-back-link"
                href="customers.php"
            >
                Back to Customers
            </a>

        </div>


        <!-- ========================================
             CUSTOMER INFORMATION
        ======================================== -->

        <section class="panel admin-page-panel customer-info-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        ACCOUNT INFORMATION
                    </p>

                    <h2>
                        <?= htmlspecialchars(
                            $customer['username']
                        ) ?>
                    </h2>

                </div>

            </div>


            <div class="customer-info-grid">

                <div class="customer-info-item">

                    <span>
                        CUSTOMER ID
                    </span>

                    <strong>
                        #<?= (int) $customer['id'] ?>
                    </strong>

                </div>


                <div class="customer-info-item">

                    <span>
                        USERNAME
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $customer['username']
                        ) ?>
                    </strong>

                </div>


                <div class="customer-info-item">

                    <span>
                        EMAIL
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $customer['email']
                        ) ?>
                    </strong>

                </div>


                <div class="customer-info-item">

                    <span>
                        ACCOUNT TYPE
                    </span>

                    <strong>
                        Customer
                    </strong>

                </div>

            </div>

        </section>


        <!-- ========================================
             CUSTOMER SUMMARY
        ======================================== -->

        <section class="admin-overview-grid customer-summary-grid">

            <div class="admin-overview-card">

                <span>
                    TOTAL ORDERS
                </span>

                <strong>
                    <?= $totalOrders ?>
                </strong>

                <small>
                    Includes all customer orders
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    TOTAL SPENT
                </span>

                <strong>
                    ₱<?= number_format(
                        $totalSpent,
                        2
                    ) ?>
                </strong>

                <small>
                    Excludes cancelled orders
                </small>

            </div>

        </section>


        <!-- ========================================
             ORDER HISTORY
        ======================================== -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        PURCHASE HISTORY
                    </p>

                    <h2>
                        Customer Orders
                    </h2>

                </div>

            </div>


            <?php if (empty($orders)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No orders yet.
                    </h3>

                    <p>
                        This customer has not placed any orders.
                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table">

                        <thead>

                            <tr>

                                <th>Order ID</th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <?php
                                $statusClass =
                                    'report-status ' .
                                    strtolower(
                                        preg_replace(
                                            '/[^a-zA-Z]/',
                                            '',
                                            $order['status']
                                        )
                                    );
                                ?>

                                <tr>

                                    <td>
                                        #<?= (int) $order['id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $order['full_name']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'M d, Y h:i A',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $order['payment_method']
                                        ) ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format(
                                            (float) $order['total_amount'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="<?= htmlspecialchars($statusClass) ?>">
                                            <?= htmlspecialchars(
                                                $order['status']
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <a
                                            class="admin-action-link"
                                            href="../orders/order_details.php?id=<?= (int) $order['id'] ?>"
                                        >
                                            VIEW ORDER
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
