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
// CHECK PRODUCT ID
// ========================================

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {
    header("Location: inventory.php");
    exit;
}

$productId = (int) $_GET['id'];


// ========================================
// LOAD PRODUCT
// ========================================

$productStmt = $pdo->prepare(
    "SELECT
        id,
        name,
        brand,
        category,
        shoe_type,
        stock,
        image
     FROM products
     WHERE id = :id"
);

$productStmt->bindValue(
    ':id',
    $productId,
    PDO::PARAM_INT
);

$productStmt->execute();

$product = $productStmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$product) {
    header("Location: inventory.php");
    exit;
}


// ========================================
// LOAD SIZE STOCK
// ========================================

$sizeStmt = $pdo->prepare(
    "SELECT
        size,
        stock
     FROM product_sizes
     WHERE product_id = :product_id
     ORDER BY id ASC"
);

$sizeStmt->bindValue(
    ':product_id',
    $productId,
    PDO::PARAM_INT
);

$sizeStmt->execute();

$productSizes = $sizeStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// INVENTORY SUMMARY
// ========================================

$totalStock = 0;
$availableSizes = 0;
$outOfStockSizes = 0;

foreach ($productSizes as $sizeRow) {

    $sizeStock =
        (int) $sizeRow['stock'];

    $totalStock += $sizeStock;

    if ($sizeStock > 0) {
        $availableSizes++;
    } else {
        $outOfStockSizes++;
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
        NexStep Admin | Inventory #<?= $productId ?>
    </title>

    <link
        rel="stylesheet"
        href="/webapp/admin/admin.css?v=8"
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
         MAIN CONTENT
    ======================================== -->

    <main class="main-content">

        <header class="topbar">

            <div>

                <p class="page-label">
                    INVENTORY DETAILS
                </p>

                <h1>
                    <?= htmlspecialchars(
                        $product['name']
                    ) ?>
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


        <div class="inventory-detail-back-row">

            <a
                href="inventory.php"
                class="inventory-detail-back-link"
            >
                Back to Inventory
            </a>

        </div>


        <!-- ========================================
             PRODUCT OVERVIEW
        ======================================== -->

        <section class="panel admin-page-panel inventory-detail-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        PRODUCT INFORMATION
                    </p>

                    <h2>
                        Stock Overview
                    </h2>

                </div>


                <a
                    href="../products/edit_product.php?id=<?= (int) $product['id'] ?>"
                    class="admin-action-link inventory-edit-button"
                >
                    EDIT PRODUCT / STOCK
                </a>

            </div>


            <div class="inventory-detail-product">

                <div class="inventory-detail-image">

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


                <div class="inventory-detail-info">

                    <div class="inventory-detail-name">

                        <p>
                            <?= htmlspecialchars(
                                $product['brand']
                            ) ?>
                        </p>

                        <h2>
                            <?= htmlspecialchars(
                                $product['name']
                            ) ?>
                        </h2>

                    </div>


                    <div class="inventory-info-grid">

                        <div class="inventory-info-box">

                            <span>
                                PRODUCT ID
                            </span>

                            <strong>
                                #<?= (int) $product['id'] ?>
                            </strong>

                        </div>


                        <div class="inventory-info-box">

                            <span>
                                BRAND
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $product['brand']
                                ) ?>
                            </strong>

                        </div>


                        <div class="inventory-info-box">

                            <span>
                                CATEGORY
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $product['category']
                                ) ?>
                            </strong>

                        </div>


                        <div class="inventory-info-box">

                            <span>
                                TYPE
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $product['shoe_type']
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ========================================
             STOCK SUMMARY
        ======================================== -->

        <section class="admin-overview-grid inventory-detail-stats">

            <div class="admin-overview-card">

                <span>
                    TOTAL STOCK
                </span>

                <strong>
                    <?= $totalStock ?>
                </strong>

                <small>
                    Total units across all sizes
                </small>

            </div>


            <div class="admin-overview-card">

                <span>
                    AVAILABLE SIZES
                </span>

                <strong>
                    <?= $availableSizes ?>
                </strong>

                <small>
                    Sizes with at least 1 unit
                </small>

            </div>


            <div class="admin-overview-card danger-card">

                <span>
                    OUT OF STOCK SIZES
                </span>

                <strong>
                    <?= $outOfStockSizes ?>
                </strong>

                <small>
                    Sizes currently unavailable
                </small>

            </div>

        </section>


        <!-- ========================================
             SIZE INVENTORY
        ======================================== -->

        <section class="panel admin-page-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        SIZE INVENTORY
                    </p>

                    <h2>
                        Stock by Size
                    </h2>

                </div>

            </div>


            <?php if (empty($productSizes)): ?>

                <div class="admin-empty-state">

                    <h3>
                        No size records found.
                    </h3>

                    <p>
                        Add size stock from the Edit Product page.
                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table class="admin-data-table inventory-size-table">

                        <thead>

                            <tr>

                                <th>Size</th>
                                <th>Stock</th>
                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($productSizes as $sizeRow): ?>

                                <?php
                                $sizeStock =
                                    (int) $sizeRow['stock'];

                                if ($sizeStock <= 0) {

                                    $status =
                                        'OUT OF STOCK';

                                    $statusClass =
                                        'inventory-status out';

                                } elseif ($sizeStock <= 2) {

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

                                        <span class="inventory-size-chip">
                                            <?= htmlspecialchars(
                                                $sizeRow['size']
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= $sizeStock ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <span class="<?= $statusClass ?>">
                                            <?= $status ?>
                                        </span>

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
