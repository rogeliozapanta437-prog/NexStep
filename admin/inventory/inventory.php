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
// LOAD PRODUCTS
// ========================================

$productStmt = $pdo->query(
    "SELECT
        id,
        name,
        brand,
        category,
        shoe_type,
        stock,
        image
     FROM products
     ORDER BY id DESC"
);

$products = $productStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// INVENTORY SUMMARY
// ========================================

$totalProducts =
    count($products);

$totalStock = 0;
$lowStock = 0;
$outOfStock = 0;

foreach ($products as $product) {

    $stock =
        (int) $product['stock'];

    $totalStock += $stock;

    if ($stock <= 0) {

        $outOfStock++;

    } elseif ($stock <= 5) {

        $lowStock++;
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

    <title>NexStep Admin | Inventory</title>

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

            <a
                href="inventory.php"
                class="active"
            >
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
                    STOCK MANAGEMENT
                </p>

                <h1>
                    Inventory
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

        <section class="admin-overview-grid">

            <div class="admin-overview-card">

                <span>
                    TOTAL PRODUCTS
                </span>

                <strong>
                    <?= $totalProducts ?>
                </strong>

                <small>
                    Products in catalog
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    TOTAL STOCK
                </span>

                <strong>
                    <?= $totalStock ?>
                </strong>

                <small>
                    Units currently available
                </small>

            </div>


            <div class="admin-overview-card warning-card">

                <span>
                    LOW STOCK
                </span>

                <strong>
                    <?= $lowStock ?>
                </strong>

                <small>
                    Products with 1–5 units
                </small>

            </div>


            <div class="admin-overview-card danger-card">

                <span>
                    OUT OF STOCK
                </span>

                <strong>
                    <?= $outOfStock ?>
                </strong>

                <small>
                    Products with no units left
                </small>

            </div>

        </section>


        <!-- INVENTORY LIST -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        PRODUCT STOCK
                    </p>

                    <h2>
                        Inventory List
                    </h2>

                </div>

            </div>


            <?php if (empty($products)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No products found.
                    </h3>

                    <p>
                        Products will appear here once added.
                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table">

                        <thead>

                            <tr>

                                <th>Product</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Total Stock</th>
                                <th>Status</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($products as $product): ?>

                                <?php
                                $stock =
                                    (int) $product['stock'];

                                if ($stock <= 0) {

                                    $status =
                                        'OUT OF STOCK';

                                    $statusClass =
                                        'inventory-status out';

                                } elseif ($stock <= 5) {

                                    $status =
                                        'LOW STOCK';

                                    $statusClass =
                                        'inventory-status low';

                                } else {

                                    $status =
                                        'IN STOCK';

                                    $statusClass =
                                        'inventory-status in';
                                }
                                ?>

                                <tr>

                                    <td>

                                        <div class="inventory-product">

                                            <div class="inventory-image">

                                                <?php if (!empty($product['image'])): ?>

                                                    <img
                                                        src="../../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                                    >

                                                <?php else: ?>

                                                    <span>
                                                        NO IMAGE
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $product['name']
                                                ) ?>
                                            </strong>

                                        </div>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $product['brand']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $product['category']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $product['shoe_type']
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= $stock ?>
                                        </strong>
                                    </td>

                                    <td>

                                        <span class="<?= $statusClass ?>">
                                            <?= $status ?>
                                        </span>

                                    </td>

                                    <td>

                                        <a
                                            class="admin-action-link"
                                            href="inventory_details.php?id=<?= (int) $product['id'] ?>"
                                        >
                                            VIEW STOCK
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
