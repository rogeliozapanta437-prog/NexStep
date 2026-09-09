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

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare(
        "SELECT *
         FROM products
         ORDER BY created_at DESC"
    );

    $stmt->execute();

    $products = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die(
        "Failed to load products: " .
        $e->getMessage()
    );
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

    <title>NexStep Admin | Products</title>

    <link
        rel="stylesheet"
        href="../admin.css"
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

            <a
                href="products.php"
                class="active"
            >
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
                    ADMIN PANEL
                </p>

                <h1>
                    Products
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
             PRODUCT SUMMARY
        ======================================== -->

        <section class="products-summary">

            <div>

                <p class="welcome-small">
                    PRODUCT MANAGEMENT
                </p>

                <h2>
                    Manage your shoes.
                </h2>

                <p>
                    Add, edit and manage all products
                    available in your NexStep store.
                </p>

            </div>


            <a
                href="add_product.php"
                class="add-product-button"
            >
                + ADD PRODUCT
            </a>

        </section>


        <!-- ========================================
             MESSAGE
        ======================================== -->

        <?php if (isset($_GET['success'])): ?>

            <div class="admin-message success-message">

                <?php
                echo htmlspecialchars(
                    $_GET['success']
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if (isset($_GET['error'])): ?>

            <div class="admin-message error-message">

                <?php
                echo htmlspecialchars(
                    $_GET['error']
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             PRODUCT LIST
        ======================================== -->

        <section class="panel products-panel">


            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        STORE PRODUCTS
                    </p>

                    <h2>
                        Product List
                    </h2>

                </div>


                <span class="product-count">

                    <?php echo count($products); ?>

                    <?php
                    echo count($products) === 1
                        ? 'Product'
                        : 'Products';
                    ?>

                </span>

            </div>


            <div class="table-wrapper">

                <table class="products-table">


                    <thead>

                        <tr>

                            <th>
                                IMAGE
                            </th>

                            <th>
                                PRODUCT
                            </th>

                            <th>
                                BRAND
                            </th>

                            <th>
                                CATEGORY
                            </th>

                            <th>
                                TYPE
                            </th>

                            <th>
                                PRICE
                            </th>

                            <th>
                                STOCK
                            </th>

                            <th>
                                STATUS
                            </th>

                            <th>
                                ACTION
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($products)): ?>


                        <tr>

                            <td
                                colspan="9"
                                class="empty-table"
                            >

                                No products available yet.

                                <br><br>

                                Click
                                <strong>
                                    + ADD PRODUCT
                                </strong>
                                to add your first shoe.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($products as $product): ?>


                            <tr>


                                <!-- IMAGE -->

                                <td>

                                    <div class="admin-product-image">

                                        <?php if (!empty($product['image'])): ?>

                                            <img
                                                src="../../uploads/products/<?php
                                                echo htmlspecialchars(
                                                    $product['image']
                                                );
                                                ?>"
                                                alt="<?php
                                                echo htmlspecialchars(
                                                    $product['name']
                                                );
                                                ?>"
                                            >

                                        <?php else: ?>

                                            <span>
                                                No Image
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- PRODUCT NAME -->

                                <td>

                                    <strong class="product-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $product['name']
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- BRAND -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $product['brand']
                                    );
                                    ?>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $product['category']
                                    );
                                    ?>

                                </td>


                                <!-- SHOE TYPE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $product['shoe_type']
                                    );
                                    ?>

                                </td>


                                <!-- PRICE -->

                                <td>

                                    <strong>

                                        ₱<?php
                                        echo number_format(
                                            (float) $product['price'],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <?php if ($product['stock'] > 0): ?>

                                        <span class="stock-available">

                                            <?php
                                            echo (int) $product['stock'];
                                            ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="stock-empty">
                                            Out of Stock
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <div class="product-status-list">


                                        <?php if ($product['is_new'] == 1): ?>

                                            <span class="status-badge status-new">
                                                NEW
                                            </span>

                                        <?php endif; ?>


                                        <?php if ($product['is_sale'] == 1): ?>

                                            <span class="status-badge status-sale">
                                                SALE
                                            </span>

                                        <?php endif; ?>


                                        <?php if (
                                            $product['is_new'] == 0 &&
                                            $product['is_sale'] == 0
                                        ): ?>

                                            <span class="status-badge status-regular">
                                                REGULAR
                                            </span>

                                        <?php endif; ?>


                                    </div>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <div class="product-actions">

                                        <a
                                            href="edit_product.php?id=<?php
                                            echo (int) $product['id'];
                                            ?>"
                                            class="edit-product"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="delete_product.php?id=<?php
                                            echo (int) $product['id'];
                                            ?>"
                                            class="delete-product"
                                            onclick="return confirm('Are you sure you want to delete this product?');"
                                        >
                                            Delete
                                        </a>

                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>


                </table>

            </div>

        </section>

    </main>

</div>

</body>

</html>