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
// LOAD CUSTOMERS
// ========================================

$stmt = $pdo->prepare(
    "SELECT
        users.id,
        users.username,
        users.email,
        users.role,
        COUNT(orders.id) AS total_orders,
        COALESCE(
            SUM(
                CASE
                    WHEN orders.status != 'Cancelled'
                    THEN orders.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS total_spent
     FROM users
     LEFT JOIN orders
        ON users.id = orders.user_id
     WHERE users.role = :role
     GROUP BY
        users.id,
        users.username,
        users.email,
        users.role
     ORDER BY users.id DESC"
);

$stmt->bindValue(
    ':role',
    'customer'
);

$stmt->execute();

$customers = $stmt->fetchAll(
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

    <title>NexStep Admin | Customers</title>

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
         MAIN
    ======================================== -->

    <main class="main-content">

        <header class="topbar">

            <div>

                <p class="page-label">
                    CUSTOMER MANAGEMENT
                </p>

                <h1>
                    Customers
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


        <!-- SUMMARY -->

        <section class="admin-overview-grid single-card">

            <div class="admin-overview-card">

                <span>
                    TOTAL CUSTOMERS
                </span>

                <strong>
                    <?= count($customers) ?>
                </strong>

                <small>
                    Registered customer accounts
                </small>

            </div>

        </section>


        <!-- CUSTOMER LIST -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        REGISTERED ACCOUNTS
                    </p>

                    <h2>
                        Customer List
                    </h2>

                </div>

            </div>


            <?php if (empty($customers)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No customers found.
                    </h3>

                    <p>
                        Registered customer accounts will appear here.
                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table">

                        <thead>

                            <tr>

                                <th>Customer ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($customers as $customer): ?>

                                <tr>

                                    <td>
                                        #<?= (int) $customer['id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $customer['username']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $customer['email']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $customer['total_orders'] ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format(
                                            (float) $customer['total_spent'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>

                                        <a
                                            class="admin-action-link"
                                            href="customer_details.php?id=<?= (int) $customer['id'] ?>"
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
