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
// PROCESS FORM
// ========================================

if (isset($_POST['add_product'])) {

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
            "Location: add_product.php?error=" .
            urlencode("Please complete all required fields.")
        );
        exit;
    }


    // ========================================
    // PRICE VALIDATION
    // ========================================

    if (!is_numeric($price) || $price < 0) {
        header(
            "Location: add_product.php?error=" .
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
            "Location: add_product.php?error=" .
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
                "Location: add_product.php?error=" .
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
    // IMAGE UPLOAD
    // ========================================

    if (
        !isset($_FILES['image']) ||
        $_FILES['image']['error'] !== UPLOAD_ERR_OK
    ) {
        header(
            "Location: add_product.php?error=" .
            urlencode("Please choose a product image.")
        );
        exit;
    }


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
            "Location: add_product.php?error=" .
            urlencode(
                "Only JPG, PNG, and WEBP images are allowed."
            )
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


    $imageName =
        time() .
        '_' .
        uniqid() .
        '.' .
        strtolower($extension);


    $uploadPath =
        $uploadDirectory .
        $imageName;


    if (
        !move_uploaded_file(
            $_FILES['image']['tmp_name'],
            $uploadPath
        )
    ) {
        header(
            "Location: add_product.php?error=" .
            urlencode("Failed to upload product image.")
        );
        exit;
    }


    // ========================================
    // SAVE PRODUCT
    // ========================================

    try {

        $pdo = getConnection();

        $pdo->beginTransaction();


        // ========================================
        // INSERT PRODUCT
        // ========================================

        $stmt = $pdo->prepare(
            "INSERT INTO products
            (
                name,
                brand,
                category,
                shoe_type,
                price,
                stock,
                image,
                description,
                is_new,
                is_sale
            )
            VALUES
            (
                :name,
                :brand,
                :category,
                :shoe_type,
                :price,
                :stock,
                :image,
                :description,
                :is_new,
                :is_sale
            )"
        );


        $stmt->bindValue(
            ':name',
            $name
        );


        $stmt->bindValue(
            ':brand',
            $brand
        );


        $stmt->bindValue(
            ':category',
            $category
        );


        $stmt->bindValue(
            ':shoe_type',
            $shoeType
        );


        $stmt->bindValue(
            ':price',
            $price
        );


        $stmt->bindValue(
            ':stock',
            $totalStock,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            ':image',
            $imageName
        );


        $stmt->bindValue(
            ':description',
            $description
        );


        $stmt->bindValue(
            ':is_new',
            $isNew,
            PDO::PARAM_INT
        );


        $stmt->bindValue(
            ':is_sale',
            $isSale,
            PDO::PARAM_INT
        );


        $stmt->execute();


        // ========================================
        // GET PRODUCT ID
        // ========================================

        $productId =
            (int) $pdo->lastInsertId();


        // ========================================
        // INSERT PRODUCT SIZES
        // ========================================

        $sizeStmt = $pdo->prepare(
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

            $sizeStmt->bindValue(
                ':product_id',
                $productId,
                PDO::PARAM_INT
            );

            $sizeStmt->bindValue(
                ':size',
                $size
            );

            $sizeStmt->bindValue(
                ':stock',
                $sizeStock,
                PDO::PARAM_INT
            );

            $sizeStmt->execute();
        }


        // ========================================
        // FINISH TRANSACTION
        // ========================================

        $pdo->commit();


        header(
            "Location: products.php?success=" .
            urlencode("Product added successfully.")
        );

        exit;


    } catch (PDOException $e) {

        if (
            isset($pdo) &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }


        if (
            isset($uploadPath) &&
            file_exists($uploadPath)
        ) {
            unlink($uploadPath);
        }


        die(
            "Failed to add product: " .
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

    <title>NexStep Admin | Add Product</title>

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

            <a href="#">
                Orders
            </a>

            <a href="#">
                Customers
            </a>

            <a href="#">
                Inventory
            </a>

            <a href="#">
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
                    Add Product
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
             MESSAGE
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
                        NEW SHOE
                    </p>

                    <h2>
                        Product Information
                    </h2>

                </div>

            </div>


            <form
                action="add_product.php"
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
                        placeholder="Example: Nike Air Force 1 '07"
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

                        <option value="">
                            Select Brand
                        </option>

                        <option value="Nike">
                            Nike
                        </option>

                        <option value="Adidas">
                            Adidas
                        </option>

                        <option value="Puma">
                            Puma
                        </option>

                        <option value="Umbro">
                            Umbro
                        </option>

                        <option value="New Balance">
                            New Balance
                        </option>

                        <option value="ASICS">
                            ASICS
                        </option>

                        <option value="Under Armour">
                            Under Armour
                        </option>

                        <option value="Other">
                            Other
                        </option>

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

                        <option value="">
                            Select Category
                        </option>

                        <option value="Men">
                            Men
                        </option>

                        <option value="Women">
                            Women
                        </option>

                        <option value="Kids">
                            Kids
                        </option>

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

                        <option value="">
                            Select Shoe Type
                        </option>

                        <option value="Sneakers">
                            Sneakers
                        </option>

                        <option value="Casual">
                            Casual
                        </option>

                        <option value="Sports">
                            Sports
                        </option>

                        <option value="Sandals">
                            Sandals
                        </option>

                        <option value="Formal">
                            Formal
                        </option>

                        <option value="Boots">
                            Boots
                        </option>

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
                        placeholder="6500.00"
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
                            Select a category first. The correct US shoe sizes
                            will appear automatically.
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
                                            value="0"
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
                                            value="0"
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
                                            value="0"
                                            disabled
                                        >

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>


                <!-- IMAGE -->

                <div class="form-group">

                    <label for="image">
                        Product Image
                    </label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp"
                        required
                    >

                    <small>
                        JPG, PNG or WEBP
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
                        placeholder="Write a short description about the product..."
                    ></textarea>

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
                        name="add_product"
                        class="save-product-button"
                    >
                        ADD PRODUCT
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