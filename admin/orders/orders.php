<?php

session_start();


// ========================================
// ADMIN PROTECTION
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
// LOAD ORDERS
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT
        orders.*,
        users.username

     FROM orders

     INNER JOIN users
        ON users.id = orders.user_id

     ORDER BY orders.created_at DESC"
);

$orderStmt->execute();

$orders = $orderStmt->fetchAll(
    PDO::FETCH_ASSOC
);

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
        NexStep Admin | Orders
    </title>

    <link
        rel="stylesheet"
        href="/webapp/admin/admin.css?v=5"
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
                src="../../homepage/images/nexstep-logo.png"
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

            <a
                href="orders.php"
                class="active"
            >
                Orders
            </a>

            <a href="../customers/customers.php">
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


        <!-- ========================================
             TOP BAR
        ======================================== -->

        <header class="topbar">

            <div>

                <p class="page-label">
                    ORDER MANAGEMENT
                </p>

                <h1>
                    Orders
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


        <!-- ========================================
             SUCCESS MESSAGE
        ======================================== -->

        <?php if (isset($_GET['success'])): ?>

            <div class="admin-message success-message">

                <?= htmlspecialchars(
                    $_GET['success']
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             ORDERS PANEL
        ======================================== -->

        <section class="panel">


            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        CUSTOMER ORDERS
                    </p>

                    <h2>
                        All Orders
                    </h2>

                </div>

            </div>


            <?php if (empty($orders)): ?>


                <p>
                    No orders available yet.
                </p>


            <?php else: ?>


                <div class="admin-table-wrapper">


                    <table class="admin-table">


                        <thead>

                            <tr>

                                <th>
                                    Order ID
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($orders as $order): ?>


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

                                        <br>

                                        <small>

                                            <?= htmlspecialchars(
                                                $order['username']
                                            ) ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            date(
                                                'M d, Y',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            )
                                        ) ?>

                                        <br>

                                        <small>

                                            <?= htmlspecialchars(
                                                date(
                                                    'g:i A',
                                                    strtotime(
                                                        $order['created_at']
                                                    )
                                                )
                                            ) ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $order['payment_method']
                                        ) ?>

                                    </td>


                                    <td>

                                        ₱<?= number_format(
                                            $order['total_amount'],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="order-status status-<?=
                                                strtolower(
                                                    htmlspecialchars(
                                                        $order['status']
                                                    )
                                                )
                                            ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $order['status']
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <a
                                            href="order_details.php?id=<?= (int) $order['id'] ?>"
                                            class="table-action-button"
                                        >
                                            VIEW
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