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


// ========================================
// CHECK PRODUCT ID
// ========================================

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header(
        "Location: products.php?error=" .
        urlencode("Invalid product.")
    );

    exit;
}


$productId = (int) $_GET['id'];


// ========================================
// LOAD PRODUCT
// ========================================

try {

    $pdo = getConnection();

    $stmt = $pdo->prepare(
        "SELECT *
         FROM products
         WHERE id = ?"
    );

    $stmt->execute([
        $productId
    ]);

    $product = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$product) {

        header(
            "Location: products.php?error=" .
            urlencode("Product not found.")
        );

        exit;
    }


    // ========================================
    // LOAD PRODUCT SIZE STOCK
    // ========================================

    $sizeStmt = $pdo->prepare(
        "SELECT size, stock
         FROM product_sizes
         WHERE product_id = ?"
    );

    $sizeStmt->execute([
        $productId
    ]);

    $productSizeRows = $sizeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $productSizeStocks = [];

    foreach ($productSizeRows as $row) {
        $productSizeStocks[$row['size']] = (int) $row['stock'];
    }


} catch (PDOException $e) {

    die(
        "Failed to load product: " .
        $e->getMessage()
    );
}


// ========================================
// UPDATE PRODUCT
// ========================================

if (isset($_POST['update_product'])) {

    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $shoeType = trim($_POST['shoe_type'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $sizes = $_POST['sizes'] ?? [];

    $isNew = isset($_POST['is_new']) ? 1 : 0;
    $isSale = isset($_POST['is_sale']) ? 1 : 0;


    // ========================================
    // BASIC VALIDATION
    // ========================================

    if (
        empty($name) ||
        empty($brand) ||
        empty($category) ||
        empty($shoeType) ||
        $price === ''
    ) {

        header(
            "Location: edit_product.php?id=$productId&error=" .
            urlencode("Please complete all required fields.")
        );

        exit;
    }


    // ========================================
    // PRICE VALIDATION
    // ========================================

    if (!is_numeric($price) || $price < 0) {

        header(
            "Location: edit_product.php?id=$productId&error=" .
            urlencode("Price must be a valid number.")
        );

        exit;
    }


    // ========================================
    // VALID SIZES BY CATEGORY
    // ========================================

    $allowedSizes = [];

    if ($category === 'Men') {

        $allowedSizes = [
            '6', '6.5', '7', '7.5',
            '8', '8.5', '9', '9.5',
            '10', '10.5', '11', '11.5',
            '12', '12.5', '13'
        ];

    } elseif ($category === 'Women') {

        $allowedSizes = [
            '5', '5.5', '6', '6.5',
            '7', '7.5', '8', '8.5',
            '9', '9.5', '10', '10.5',
            '11', '11.5', '12'
        ];

    } elseif ($category === 'Kids') {

        $allowedSizes = [
            '10C', '11C', '12C', '13C',
            '1Y', '2Y', '3Y', '4Y',
            '5Y', '6Y', '7Y'
        ];

    } else {

        header(
            "Location: edit_product.php?id=$productId&error=" .
            urlencode("Please select a valid category.")
        );

        exit;
    }


    // ========================================
    // SIZE STOCK VALIDATION
    // ========================================

    $sizeStocks = [];
    $totalStock = 0;

    foreach ($allowedSizes as $size) {

        $sizeStock = $sizes[$size] ?? 0;

        if (
            filter_var(
                $sizeStock,
                FILTER_VALIDATE_INT
            ) === false ||
            (int) $sizeStock < 0
        ) {

            header(
                "Location: edit_product.php?id=$productId&error=" .
                urlencode(
                    "Stock for size " .
                    $size .
                    " must be a whole number."
                )
            );

            exit;
        }

        $sizeStocks[$size] = (int) $sizeStock;
        $totalStock += (int) $sizeStock;
    }


    // ========================================
    // CURRENT IMAGE
    // ========================================

    $imageName = $product['image'];
    $newUploadPath = null;
    $oldImagePath = null;


    // ========================================
    // OPTIONAL NEW IMAGE
    // ========================================

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];


        $imageType = mime_content_type(
            $_FILES['image']['tmp_name']
        );


        if (!in_array($imageType, $allowedTypes)) {

            header(
                "Location: edit_product.php?id=$productId&error=" .
                urlencode("Only JPG, PNG, and WEBP images are allowed.")
            );

            exit;
        }


        $uploadDirectory =
            '../../uploads/products/';


        if (!is_dir($uploadDirectory)) {

            mkdir(
                $uploadDirectory,
                0777,
                true
            );
        }


        $extension = pathinfo(
            $_FILES['image']['name'],
            PATHINFO_EXTENSION
        );


        $newImageName =
            time() .
            '_' .
            uniqid() .
            '.' .
            strtolower($extension);


        $newUploadPath =
            $uploadDirectory .
            $newImageName;


        if (
            !move_uploaded_file(
                $_FILES['image']['tmp_name'],
                $newUploadPath
            )
        ) {

            header(
                "Location: edit_product.php?id=$productId&error=" .
                urlencode("Failed to upload new product image.")
            );

            exit;
        }


        if (!empty($product['image'])) {

            $oldImagePath =
                '../../uploads/products/' .
                $product['image'];
        }


        $imageName = $newImageName;
    }


    // ========================================
    // UPDATE DATABASE
    // ========================================

    try {

        $pdo->beginTransaction();


        // ========================================
        // UPDATE PRODUCT
        // ========================================

        $updateStmt = $pdo->prepare(
            "UPDATE products
             SET
                name = :name,
                brand = :brand,
                category = :category,
                shoe_type = :shoe_type,
                price = :price,
                stock = :stock,
                image = :image,
                description = :description,
                is_new = :is_new,
                is_sale = :is_sale
             WHERE id = :id"
        );


        $updateStmt->bindValue(
            ':name',
            $name
        );

        $updateStmt->bindValue(
            ':brand',
            $brand
        );

        $updateStmt->bindValue(
            ':category',
            $category
        );

        $updateStmt->bindValue(
            ':shoe_type',
            $shoeType
        );

        $updateStmt->bindValue(
            ':price',
            $price
        );

        $updateStmt->bindValue(
            ':stock',
            $totalStock,
            PDO::PARAM_INT
        );

        $updateStmt->bindValue(
            ':image',
            $imageName
        );

        $updateStmt->bindValue(
            ':description',
            $description
        );

        $updateStmt->bindValue(
            ':is_new',
            $isNew,
            PDO::PARAM_INT
        );

        $updateStmt->bindValue(
            ':is_sale',
            $isSale,
            PDO::PARAM_INT
        );

        $updateStmt->bindValue(
            ':id',
            $productId,
            PDO::PARAM_INT
        );

        $updateStmt->execute();


        // ========================================
        // REMOVE OLD SIZE ROWS
        // ========================================

        $deleteSizeStmt = $pdo->prepare(
            "DELETE FROM product_sizes
             WHERE product_id = :product_id"
        );

        $deleteSizeStmt->bindValue(
            ':product_id',
            $productId,
            PDO::PARAM_INT
        );

        $deleteSizeStmt->execute();


        // ========================================
        // INSERT UPDATED SIZE ROWS
        // ========================================

        $insertSizeStmt = $pdo->prepare(
            "INSERT INTO product_sizes
            (
                product_id,
                size,
                stock
            )
            VALUES
            (
                :product_id,
                :size,
                :stock
            )"
        );


        foreach ($sizeStocks as $size => $sizeStock) {

            $insertSizeStmt->bindValue(
                ':product_id',
                $productId,
                PDO::PARAM_INT
            );

            $insertSizeStmt->bindValue(
                ':size',
                $size
            );

            $insertSizeStmt->bindValue(
                ':stock',
                $sizeStock,
                PDO::PARAM_INT
            );

            $insertSizeStmt->execute();
        }


        // ========================================
        // FINISH TRANSACTION
        // ========================================

        $pdo->commit();


        // Delete old image only after database update succeeds.
        if (
            $oldImagePath !== null &&
            file_exists($oldImagePath)
        ) {
            unlink($oldImagePath);
        }


        header(
            "Location: products.php?success=" .
            urlencode("Product updated successfully.")
        );

        exit;


    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        // Remove newly uploaded image if database update fails.
        if (
            $newUploadPath !== null &&
            file_exists($newUploadPath)
        ) {
            unlink($newUploadPath);
        }


        die(
            "Failed to update product: " .
            $e->getMessage()
        );
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

    <title>NexStep Admin | Edit Product</title>

    <link
        rel="stylesheet"
        href="../admin.css?v=2"
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
                    PRODUCT MANAGEMENT
                </p>

                <h1>
                    Edit Product
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
             BACK BUTTON
        ======================================== -->

        <div class="product-form-top">

            <a
                href="products.php"
                class="back-product-button"
            >
                ← Back to Products
            </a>

        </div>


        <!-- ========================================
             ERROR MESSAGE
        ======================================== -->

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
             FORM
        ======================================== -->

        <section class="panel product-form-panel">


            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        EDIT SHOE
                    </p>

                    <h2>
                        Product Information
                    </h2>

                </div>

            </div>


            <form
                action="edit_product.php?id=<?php echo $productId; ?>"
                method="POST"
                enctype="multipart/form-data"
                class="product-form"
            >


                <!-- PRODUCT NAME -->

                <div class="form-group full-width">

                    <label for="name">
                        Product Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php
                        echo htmlspecialchars(
                            $product['name']
                        );
                        ?>"
                        required
                    >

                </div>


                <!-- BRAND -->

                <div class="form-group">

                    <label for="brand">
                        Brand
                    </label>

                    <select
                        id="brand"
                        name="brand"
                        required
                    >

                        <?php

                        $brands = [
                            'Nike',
                            'Adidas',
                            'Puma',
                            'Umbro',
                            'New Balance',
                            'ASICS',
                            'Under Armour',
                            'Other'
                        ];

                        ?>

                        <?php foreach ($brands as $brandOption): ?>

                            <option
                                value="<?php echo $brandOption; ?>"
                                <?php
                                echo $product['brand'] === $brandOption
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo $brandOption; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category">
                        Category
                    </label>

                    <select
                        id="category"
                        name="category"
                        required
                    >

                        <?php

                        $categories = [
                            'Men',
                            'Women',
                            'Kids'
                        ];

                        ?>

                        <?php foreach ($categories as $categoryOption): ?>

                            <option
                                value="<?php echo $categoryOption; ?>"
                                <?php
                                echo $product['category'] === $categoryOption
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo $categoryOption; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SHOE TYPE -->

                <div class="form-group">

                    <label for="shoe_type">
                        Shoe Type
                    </label>

                    <select
                        id="shoe_type"
                        name="shoe_type"
                        required
                    >

                        <?php

                        $shoeTypes = [
                            'Sneakers',
                            'Casual',
                            'Sports',
                            'Sandals',
                            'Formal',
                            'Boots'
                        ];

                        ?>

                        <?php foreach ($shoeTypes as $typeOption): ?>

                            <option
                                value="<?php echo $typeOption; ?>"
                                <?php
                                echo $product['shoe_type'] === $typeOption
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo $typeOption; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PRICE -->

                <div class="form-group">

                    <label for="price">
                        Price
                    </label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0"
                        step="0.01"
                        value="<?php
                        echo htmlspecialchars(
                            $product['price']
                        );
                        ?>"
                        required
                    >

                </div>


                <!-- ========================================
                     SIZES AND STOCK
                ======================================== -->

                <div class="form-group full-width">

                    <label>
                        US Shoe Sizes & Stock
                    </label>

                    <small>
                        Change the stock for each available US shoe size.
                    </small>


                    <!-- MEN SIZES -->

                    <div
                        id="menSizes"
                        class="shoe-size-group"
                        hidden
                    >

                        <h3>US Men's Shoe Sizes & Stock</h3>

                        <div class="size-stock-grid">

                            <?php
                            $menSizes = [
                                '6', '6.5', '7', '7.5',
                                '8', '8.5', '9', '9.5',
                                '10', '10.5', '11', '11.5',
                                '12', '12.5', '13'
                            ];
                            ?>

                            <?php foreach ($menSizes as $size): ?>

                                <div class="size-stock-item">

                                    <label>
                                        <?= htmlspecialchars($size) ?>
                                    </label>

                                    <input
                                        type="number"
                                        name="sizes[<?= htmlspecialchars($size) ?>]"
                                        min="0"
                                        step="1"
                                        value="<?=
                                            $product['category'] === 'Men'
                                                ? (int) ($productSizeStocks[$size] ?? 0)
                                                : 0
                                        ?>"
                                        disabled
                                    >

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>


                    <!-- WOMEN SIZES -->

                    <div
                        id="womenSizes"
                        class="shoe-size-group"
                        hidden
                    >

                        <h3>US Women's Shoe Sizes & Stock</h3>

                        <div class="size-stock-grid">

                            <?php
                            $womenSizes = [
                                '5', '5.5', '6', '6.5',
                                '7', '7.5', '8', '8.5',
                                '9', '9.5', '10', '10.5',
                                '11', '11.5', '12'
                            ];
                            ?>

                            <?php foreach ($womenSizes as $size): ?>

                                <div class="size-stock-item">

                                    <label>
                                        <?= htmlspecialchars($size) ?>
                                    </label>

                                    <input
                                        type="number"
                                        name="sizes[<?= htmlspecialchars($size) ?>]"
                                        min="0"
                                        step="1"
                                        value="<?=
                                            $product['category'] === 'Women'
                                                ? (int) ($productSizeStocks[$size] ?? 0)
                                                : 0
                                        ?>"
                                        disabled
                                    >

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>


                    <!-- KIDS SIZES -->

                    <div
                        id="kidsSizes"
                        class="shoe-size-group"
                        hidden
                    >

                        <h3>US Kids' Shoe Sizes & Stock</h3>

                        <div class="size-stock-grid">

                            <?php
                            $kidsSizes = [
                                '10C', '11C', '12C', '13C',
                                '1Y', '2Y', '3Y', '4Y',
                                '5Y', '6Y', '7Y'
                            ];
                            ?>

                            <?php foreach ($kidsSizes as $size): ?>

                                <div class="size-stock-item">

                                    <label>
                                        <?= htmlspecialchars($size) ?>
                                    </label>

                                    <input
                                        type="number"
                                        name="sizes[<?= htmlspecialchars($size) ?>]"
                                        min="0"
                                        step="1"
                                        value="<?=
                                            $product['category'] === 'Kids'
                                                ? (int) ($productSizeStocks[$size] ?? 0)
                                                : 0
                                        ?>"
                                        disabled
                                    >

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>


                <!-- CURRENT IMAGE -->

                <div class="form-group">

                    <label>
                        Current Image
                    </label>

                    <div class="edit-current-image">

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

                    </div>

                </div>


                <!-- NEW IMAGE -->

                <div class="form-group">

                    <label for="image">
                        Replace Image
                    </label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        Leave empty to keep the current image.
                    </small>

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group full-width">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                    ><?php
                    echo htmlspecialchars(
                        $product['description']
                    );
                    ?></textarea>

                </div>


                <!-- STATUS -->

                <div class="form-group full-width">

                    <label>
                        Product Status
                    </label>


                    <div class="product-checkboxes">


                        <label class="checkbox-option">

                            <input
                                type="checkbox"
                                name="is_new"
                                value="1"
                                <?php
                                echo $product['is_new'] == 1
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <span>
                                New Arrival
                            </span>

                        </label>


                        <label class="checkbox-option">

                            <input
                                type="checkbox"
                                name="is_sale"
                                value="1"
                                <?php
                                echo $product['is_sale'] == 1
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <span>
                                On Sale
                            </span>

                        </label>


                    </div>

                </div>


                <!-- BUTTONS -->

                <div class="product-form-actions full-width">

                    <a
                        href="products.php"
                        class="cancel-product-button"
                    >
                        CANCEL
                    </a>


                    <button
                        type="submit"
                        name="update_product"
                        class="save-product-button"
                    >
                        UPDATE PRODUCT
                    </button>

                </div>


            </form>

        </section>


    </main>

</div>


<script>

const categorySelect = document.getElementById('category');

const menSizes = document.getElementById('menSizes');
const womenSizes = document.getElementById('womenSizes');
const kidsSizes = document.getElementById('kidsSizes');


function disableGroup(group) {

    group.hidden = true;

    const inputs = group.querySelectorAll('input');

    inputs.forEach(function (input) {
        input.disabled = true;
    });
}


function enableGroup(group) {

    group.hidden = false;

    const inputs = group.querySelectorAll('input');

    inputs.forEach(function (input) {
        input.disabled = false;
    });
}


function updateSizeFields() {

    disableGroup(menSizes);
    disableGroup(womenSizes);
    disableGroup(kidsSizes);


    if (categorySelect.value === 'Men') {

        enableGroup(menSizes);

    } else if (categorySelect.value === 'Women') {

        enableGroup(womenSizes);

    } else if (categorySelect.value === 'Kids') {

        enableGroup(kidsSizes);
    }
}


categorySelect.addEventListener(
    'change',
    updateSizeFields
);


updateSizeFields();

</script>


</body>

</html>
