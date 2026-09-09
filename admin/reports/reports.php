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
// SALES SUMMARY
// ========================================

$totalSales = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM orders
     WHERE status != 'Cancelled'"
)->fetchColumn();

$totalOrders = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM orders"
)->fetchColumn();

$totalCustomers = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM users
     WHERE role = 'customer'"
)->fetchColumn();

$totalProductsSold = (int) $pdo->query(
    "SELECT COALESCE(SUM(order_items.quantity), 0)
     FROM order_items
     INNER JOIN orders
        ON orders.id = order_items.order_id
     WHERE orders.status != 'Cancelled'"
)->fetchColumn();


// ========================================
// ORDER STATUS COUNTS
// ========================================

$statusCounts = [
    'Pending' => 0,
    'Processing' => 0,
    'Shipped' => 0,
    'Delivered' => 0,
    'Cancelled' => 0
];

$statusStmt = $pdo->query(
    "SELECT
        status,
        COUNT(*) AS total
     FROM orders
     GROUP BY status"
);

$statusRows = $statusStmt->fetchAll(
    PDO::FETCH_ASSOC
);

foreach ($statusRows as $row) {

    if (
        isset(
            $statusCounts[
                $row['status']
            ]
        )
    ) {

        $statusCounts[
            $row['status']
        ] = (int) $row['total'];
    }
}


// ========================================
// TOP SELLING PRODUCTS
// ========================================

$topProductStmt = $pdo->query(
    "SELECT
        products.id,
        products.name,
        products.brand,
        SUM(
            order_items.quantity
        ) AS quantity_sold,
        SUM(
            order_items.quantity *
            order_items.price
        ) AS total_sales
     FROM order_items
     INNER JOIN products
        ON order_items.product_id = products.id
     INNER JOIN orders
        ON orders.id = order_items.order_id
     WHERE orders.status != 'Cancelled'
     GROUP BY
        products.id,
        products.name,
        products.brand
     ORDER BY quantity_sold DESC
     LIMIT 5"
);

$topProducts = $topProductStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// RECENT ORDERS
// ========================================

$recentStmt = $pdo->query(
    "SELECT
        orders.id,
        orders.full_name,
        orders.total_amount,
        orders.status,
        orders.created_at,
        users.username
     FROM orders
     INNER JOIN users
        ON orders.user_id = users.id
     ORDER BY orders.created_at DESC
     LIMIT 10"
);

$recentOrders = $recentStmt->fetchAll(
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

    <title>NexStep Admin | Reports</title>

    <link
        rel="stylesheet"
        href="/webapp/admin/admin.css?v=3"
    >

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

            <a href="../customers/customers.php">
                Customers
            </a>

            <a href="../inventory/inventory.php">
                Inventory
            </a>

            <a
                href="reports.php"
                class="active"
            >
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
         MAIN
    ======================================== -->

    <main class="main-content">

        <header class="topbar">

            <div>

                <p class="page-label">
                    BUSINESS OVERVIEW
                </p>

                <h1>
                    Reports
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


        <!-- SALES SUMMARY -->

        <section class="admin-overview-grid">

            <div class="admin-overview-card">

                <span>
                    TOTAL SALES
                </span>

                <strong>
                    ₱<?= number_format(
                        $totalSales,
                        2
                    ) ?>
                </strong>

                <small>
                    Excludes cancelled orders
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    TOTAL ORDERS
                </span>

                <strong>
                    <?= $totalOrders ?>
                </strong>

                <small>
                    All customer orders
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    TOTAL CUSTOMERS
                </span>

                <strong>
                    <?= $totalCustomers ?>
                </strong>

                <small>
                    Registered customers
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    PRODUCTS SOLD
                </span>

                <strong>
                    <?= $totalProductsSold ?>
                </strong>

                <small>
                    Excludes cancelled orders
                </small>

            </div>

        </section>


        <!-- ORDER STATUS -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        ORDER BREAKDOWN
                    </p>

                    <h2>
                        Order Status
                    </h2>

                </div>

            </div>


            <div class="status-overview-grid">

                <?php foreach ($statusCounts as $status => $count): ?>

                    <div class="status-overview-card">

                        <span>
                            <?= strtoupper(
                                htmlspecialchars($status)
                            ) ?>
                        </span>

                        <strong>
                            <?= $count ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- TOP SELLING PRODUCTS -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        PRODUCT PERFORMANCE
                    </p>

                    <h2>
                        Top Selling Products
                    </h2>

                </div>

            </div>


            <?php if (empty($topProducts)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No sales data yet.
                    </h3>

                    <p>
                        Product sales will appear after
                        non-cancelled orders are recorded.
                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table">

                        <thead>

                            <tr>

                                <th>Product</th>
                                <th>Brand</th>
                                <th>Quantity Sold</th>
                                <th>Sales</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($topProducts as $product): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $product['name']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $product['brand']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $product['quantity_sold'] ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format(
                                            (float) $product['total_sales'],
                                            2
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- RECENT ORDERS -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        RECENT ACTIVITY
                    </p>

                    <h2>
                        Recent Orders
                    </h2>

                </div>

            </div>


            <?php if (empty($recentOrders)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No orders found.
                    </h3>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table">

                        <thead>

                            <tr>

                                <th>Order</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($recentOrders as $order): ?>

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

                                        <small class="table-subtext">
                                            <?= htmlspecialchars(
                                                $order['username']
                                            ) ?>
                                        </small>

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
