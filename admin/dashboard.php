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

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// DATABASE
// ========================================

require_once '../database/config.php';

$pdo = getConnection();


// ========================================
// TOTAL SALES
// ========================================

$salesStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM orders
     WHERE status != :cancelled"
);

$salesStmt->bindValue(
    ':cancelled',
    'Cancelled'
);


$salesStmt->execute();

$totalSales = (float) $salesStmt->fetchColumn();


// ========================================
// TOTAL ORDERS
// ========================================

$orderCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM orders"
);

$orderCountStmt->execute();

$totalOrders =
    (int) $orderCountStmt->fetchColumn();


// ========================================
// TOTAL PRODUCTS
// ========================================

$productCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM products"
);

$productCountStmt->execute();

$totalProducts =
    (int) $productCountStmt->fetchColumn();


// ========================================
// TOTAL CUSTOMERS
// ========================================

$customerCountStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM users
     WHERE role = :role"
);

$customerCountStmt->bindValue(
    ':role',
    'customer'
);

$customerCountStmt->execute();

$totalCustomers =
    (int) $customerCountStmt->fetchColumn();


// ========================================
// RECENT ORDERS
// ========================================

$recentOrderStmt = $pdo->prepare(
    "SELECT
        id,
        full_name,
        total_amount,
        status,
        created_at

     FROM orders

     ORDER BY created_at DESC

     LIMIT 5"
);

$recentOrderStmt->execute();

$recentOrders =
    $recentOrderStmt->fetchAll(
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
        NexStep Admin | Dashboard
    </title>

    <link
        rel="stylesheet"
        href="admin.css"
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
                src="../homepage/images/nexstep-logo.png"
                alt="NexStep Logo"
            >

            <span>
                ADMIN
            </span>

        </div>


        <nav class="sidebar-menu">

            <a
                href="dashboard.php"
                class="active"
            >
                Dashboard
            </a>


            <a href="products/products.php">
                Products
            </a>


            <a href="orders/orders.php">
                Orders
            </a>


            <a href="customers/customers.php">
                Customers
            </a>


            <a href="inventory/inventory.php">
                Inventory
            </a>


            <a href="reports/reports.php">
                Reports
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../homepage/index.php">
                View Store
            </a>

            <a href="../logout/logout.php">
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
                    ADMIN PANEL
                </p>

                <h1>
                    Dashboard
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
             WELCOME
        ======================================== -->

        <section class="welcome-box">


            <div>

                <p class="welcome-small">
                    WELCOME BACK
                </p>

                <h2>
                    Manage your NexStep store.
                </h2>

                <p>
                    Track orders, products, customers,
                    stock and sales from one place.
                </p>

            </div>


            <div class="welcome-badge">
                NEXSTEP
            </div>


        </section>


        <!-- ========================================
             SUMMARY CARDS
        ======================================== -->

        <section class="stats-grid">


            <!-- TOTAL SALES -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        TOTAL SALES
                    </span>

                    <div class="stat-icon">
                        ₱
                    </div>

                </div>


                <h3>

                    ₱<?= number_format(
                        $totalSales,
                        2
                    ) ?>

                </h3>


                <p>

                    <?php if ($totalSales > 0): ?>

                        Total order value

                    <?php else: ?>

                        No sales recorded yet

                    <?php endif; ?>

                </p>

            </div>


            <!-- TOTAL ORDERS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        TOTAL ORDERS
                    </span>

                    <div class="stat-icon">
                        O
                    </div>

                </div>


                <h3>
                    <?= $totalOrders ?>
                </h3>


                <p>
                    Customer orders
                </p>

            </div>


            <!-- PRODUCTS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        PRODUCTS
                    </span>

                    <div class="stat-icon">
                        P
                    </div>

                </div>


                <h3>
                    <?= $totalProducts ?>
                </h3>


                <p>
                    Products in store
                </p>

            </div>


            <!-- CUSTOMERS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        CUSTOMERS
                    </span>

                    <div class="stat-icon">
                        C
                    </div>

                </div>


                <h3>
                    <?= $totalCustomers ?>
                </h3>


                <p>
                    Registered customers
                </p>

            </div>


        </section>


        <!-- ========================================
             CONTENT GRID
        ======================================== -->

        <section class="dashboard-grid">


            <!-- ========================================
                 RECENT ORDERS
            ======================================== -->

            <div class="panel recent-orders">


                <div class="panel-header">

                    <div>

                        <p class="panel-label">
                            ORDERS
                        </p>

                        <h2>
                            Recent Orders
                        </h2>

                    </div>


                    <a href="orders/orders.php">
                        View All
                    </a>

                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Order ID
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (empty($recentOrders)): ?>


                            <tr>

                                <td
                                    colspan="4"
                                    class="empty-table"
                                >
                                    No orders available yet.
                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($recentOrders as $order): ?>


                                <tr>


                                    <td>

                                        <a
                                            href="orders/order_details.php?id=<?= (int) $order['id'] ?>"
                                        >

                                            #<?= (int) $order['id'] ?>

                                        </a>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $order['full_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        ₱<?= number_format(
                                            $order['total_amount'],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $order['status']
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


            <!-- ========================================
                 QUICK ACTIONS
            ======================================== -->

            <div class="panel quick-actions">


                <div class="panel-header">

                    <div>

                        <p class="panel-label">
                            SHORTCUTS
                        </p>

                        <h2>
                            Quick Actions
                        </h2>

                    </div>

                </div>


                <div class="action-list">


                    <a href="products/add_product.php">

                        <strong>
                            Add Product
                        </strong>

                        <span>
                            Add a new shoe to the store
                        </span>

                    </a>


                    <a href="orders/orders.php">

                        <strong>
                            Manage Orders
                        </strong>

                        <span>
                            Check customer orders
                        </span>

                    </a>


                    <a href="inventory/inventory.php">

                        <strong>
                            View Inventory
                        </strong>

                        <span>
                            Monitor product stock
                        </span>

                    </a>


                    <a href="reports/reports.php">

                        <strong>
                            View Reports
                        </strong>

                        <span>
                            Review store performance
                        </span>

                    </a>


                </div>


            </div>


        </section>


    </main>


</div>


</body>

</html>