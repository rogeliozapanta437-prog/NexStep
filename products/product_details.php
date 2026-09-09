<?php

session_start();

require_once '../database/config.php';

$pdo = getConnection();

$product = null;
$productSizes = [];

$error = '';


// ========================================
// CHECK PRODUCT
// ========================================

if (isset($_GET['id']) && !empty($_GET['id'])) {

    $productId = (int) $_GET['id'];


    // ========================================
    // LOAD PRODUCT
    // ========================================

    $stmt = $pdo->prepare(
        "SELECT *
         FROM products
         WHERE id = :id"
    );

    $stmt->bindValue(
        ':id',
        $productId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $product = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$product) {

        $error = 'Product not found.';

    } else {


        // ========================================
        // LOAD PRODUCT SIZES
        // ========================================

        $sizeStmt = $pdo->prepare(
            "SELECT size, stock
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
    }

} else {

    $error = 'No product selected.';
}


// ========================================
// ADD TO CART
// ========================================

$cartMessage = '';
$cartError = '';

if (
    isset($_POST['add_to_cart']) &&
    $product
) {

    // User must be logged in
    if (!isset($_SESSION['user_id'])) {

        $cartError = 'Please log in before adding items to your cart.';

    } else {

        $userId = (int) $_SESSION['user_id'];

        $selectedSize =
            trim($_POST['selected_size'] ?? '');

        $quantity =
            filter_var(
                $_POST['quantity'] ?? '',
                FILTER_VALIDATE_INT
            );


        // ========================================
        // VALIDATE SIZE AND QUANTITY
        // ========================================

        if (
            $selectedSize === '' ||
            $quantity === false ||
            $quantity < 1
        ) {

            $cartError =
                'Please select a valid size and quantity.';

        } else {

            try {

                // ========================================
                // CHECK ACTUAL SIZE STOCK
                // ========================================

                $stockStmt = $pdo->prepare(
                    "SELECT stock
                     FROM product_sizes
                     WHERE product_id = :product_id
                     AND size = :size"
                );

                $stockStmt->bindValue(
                    ':product_id',
                    $productId,
                    PDO::PARAM_INT
                );

                $stockStmt->bindValue(
                    ':size',
                    $selectedSize
                );

                $stockStmt->execute();

                $sizeRow = $stockStmt->fetch(
                    PDO::FETCH_ASSOC
                );


                if (!$sizeRow) {

                    $cartError =
                        'The selected size is not available.';

                } elseif ((int) $sizeRow['stock'] <= 0) {

                    $cartError =
                        'The selected size is out of stock.';

                } else {

                    $availableStock =
                        (int) $sizeRow['stock'];


                    // ========================================
                    // CHECK EXISTING CART ITEM
                    // ========================================

                    $cartStmt = $pdo->prepare(
                        "SELECT id, quantity
                         FROM cart
                         WHERE user_id = :user_id
                         AND product_id = :product_id
                         AND size = :size"
                    );

                    $cartStmt->bindValue(
                        ':user_id',
                        $userId,
                        PDO::PARAM_INT
                    );

                    $cartStmt->bindValue(
                        ':product_id',
                        $productId,
                        PDO::PARAM_INT
                    );

                    $cartStmt->bindValue(
                        ':size',
                        $selectedSize
                    );

                    $cartStmt->execute();

                    $existingCartItem =
                        $cartStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    $newQuantity = $quantity;

                    if ($existingCartItem) {

                        $newQuantity =
                            (int) $existingCartItem['quantity']
                            + $quantity;
                    }


                    // ========================================
                    // CHECK AVAILABLE STOCK
                    // ========================================

                    if ($newQuantity > $availableStock) {

                        $cartError =
                            'You cannot add more than the available stock for size ' .
                            $selectedSize .
                            '.';

                    } else {


                        // ========================================
                        // UPDATE EXISTING CART ITEM
                        // ========================================

                        if ($existingCartItem) {

                            $updateCartStmt = $pdo->prepare(
                                "UPDATE cart
                                 SET quantity = :quantity
                                 WHERE id = :id"
                            );

                            $updateCartStmt->bindValue(
                                ':quantity',
                                $newQuantity,
                                PDO::PARAM_INT
                            );

                            $updateCartStmt->bindValue(
                                ':id',
                                (int) $existingCartItem['id'],
                                PDO::PARAM_INT
                            );

                            $updateCartStmt->execute();


                        // ========================================
                        // INSERT NEW CART ITEM
                        // ========================================

                        } else {

                            $insertCartStmt = $pdo->prepare(
                                "INSERT INTO cart
                                (
                                    user_id,
                                    product_id,
                                    size,
                                    quantity
                                )
                                VALUES
                                (
                                    :user_id,
                                    :product_id,
                                    :size,
                                    :quantity
                                )"
                            );

                            $insertCartStmt->bindValue(
                                ':user_id',
                                $userId,
                                PDO::PARAM_INT
                            );

                            $insertCartStmt->bindValue(
                                ':product_id',
                                $productId,
                                PDO::PARAM_INT
                            );

                            $insertCartStmt->bindValue(
                                ':size',
                                $selectedSize
                            );

                            $insertCartStmt->bindValue(
                                ':quantity',
                                $quantity,
                                PDO::PARAM_INT
                            );

                            $insertCartStmt->execute();
                        }


                        $cartMessage =
                            'Product added to cart successfully.';
                    }
                }


            } catch (PDOException $e) {

                $cartError =
                    'Failed to add product to cart.';
            }
        }
    }
}


// ========================================
// WISHLIST
// ========================================

$isWishlisted = false;

if (
    $product &&
    isset($_SESSION['user_id'])
) {

    $userId = (int) $_SESSION['user_id'];

    if (isset($_POST['toggle_wishlist'])) {

        $checkWishlistStmt = $pdo->prepare(
            "SELECT id
             FROM wishlist
             WHERE user_id = :user_id
             AND product_id = :product_id"
        );

        $checkWishlistStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $checkWishlistStmt->bindValue(
            ':product_id',
            $productId,
            PDO::PARAM_INT
        );

        $checkWishlistStmt->execute();

        $wishlistItem =
            $checkWishlistStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($wishlistItem) {

            $deleteWishlistStmt = $pdo->prepare(
                "DELETE FROM wishlist
                 WHERE user_id = :user_id
                 AND product_id = :product_id"
            );

            $deleteWishlistStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $deleteWishlistStmt->bindValue(
                ':product_id',
                $productId,
                PDO::PARAM_INT
            );

            $deleteWishlistStmt->execute();

        } else {

            $insertWishlistStmt = $pdo->prepare(
                "INSERT INTO wishlist
                (
                    user_id,
                    product_id
                )
                VALUES
                (
                    :user_id,
                    :product_id
                )"
            );

            $insertWishlistStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $insertWishlistStmt->bindValue(
                ':product_id',
                $productId,
                PDO::PARAM_INT
            );

            $insertWishlistStmt->execute();
        }

        header(
            "Location: product_details.php?id=" .
            $productId
        );

        exit;
    }

    $wishlistStatusStmt = $pdo->prepare(
        "SELECT id
         FROM wishlist
         WHERE user_id = :user_id
         AND product_id = :product_id"
    );

    $wishlistStatusStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $wishlistStatusStmt->bindValue(
        ':product_id',
        $productId,
        PDO::PARAM_INT
    );

    $wishlistStatusStmt->execute();

    $isWishlisted =
        (bool) $wishlistStatusStmt->fetch(
            PDO::FETCH_ASSOC
        );
}


// ========================================
// SIZE LABEL
// ========================================

$sizeLabel = 'US Shoe Size';

if ($product) {

    if ($product['category'] === 'Men') {

        $sizeLabel = "US Men's Size";

    } elseif ($product['category'] === 'Women') {

        $sizeLabel = "US Women's Size";

    } elseif ($product['category'] === 'Kids') {

        $sizeLabel = "US Kids' Size";
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
        <?php if ($product): ?>
            <?= htmlspecialchars($product['name']) ?> - NexStep
        <?php else: ?>
            Product Details - NexStep
        <?php endif; ?>
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/webapp/products/product_details.css?v=100"
    >
</head>

<body>

<!-- ========================================
     HEADER
======================================== -->

<header class="shop-header">

    <a
        href="../homepage/index.php"
        class="shop-logo"
    >

        <img
            src="../homepage/images/nexstep-logo.png"
            alt="NexStep"
        >

    </a>


    <nav>

        <a href="products.php?category=Men">
            MEN
        </a>

        <a href="products.php?category=Women">
            WOMEN
        </a>

        <a href="products.php?category=Kids">
            KIDS
        </a>

        <a href="../homepage/index.php#brands">
            BRANDS
        </a>

        <a href="products.php?filter=new">
            NEW ARRIVALS
        </a>

        <a href="products.php?filter=sale">
            SALE
        </a>

    </nav>


    <a
        href="../homepage/index.php"
        class="back-home"
    >
        HOME
    </a>

</header>

<?php if (!empty($error)): ?>

    <main class="details-error">

        <p class="details-kicker">
            PRODUCT
        </p>

        <h1>
            <?= htmlspecialchars($error) ?>
        </h1>

        <a
            href="products.php"
            class="primary-link"
        >
            BACK TO PRODUCTS
        </a>

    </main>

<?php else: ?>

    <main class="product-page">

                <div class="breadcrumb">

                    <a href="../homepage/index.php">
                        HOME
                    </a>

                    <span>/</span>

                    <a href="products.php">
                        SHOP
                    </a>

                    <span>/</span>

                    <a href="products.php?brand=<?= urlencode($product['brand']) ?>">
                        <?= htmlspecialchars(strtoupper($product['brand'])) ?>
                    </a>

                    <span>/</span>

                    <strong>
                        <?= htmlspecialchars(
                            strtoupper($product['name'])
                        ) ?>
                    </strong>

                </div>


        <section class="product-layout">

            <div class="product-gallery">

                <div class="product-main-image">

                    <?php if ((int) $product['is_new'] === 1): ?>
                        <span class="detail-badge">
                            NEW
                        </span>
                    <?php elseif ((int) $product['is_sale'] === 1): ?>
                        <span class="detail-badge sale">
                            SALE
                        </span>
                    <?php endif; ?>

                    <img
                        src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                    >

                </div>

                <div class="image-note">
                    <span>PRODUCT IMAGE</span>
                    <span>100% AUTHENTIC</span>
                </div>

            </div>


            <div class="product-information">

                <p class="brand-name">
                    <?= htmlspecialchars(strtoupper($product['brand'])) ?>
                </p>

                <h1>
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <p class="product-meta">
                    <?= htmlspecialchars($product['category']) ?>
                    <span>·</span>
                    <?= htmlspecialchars($product['shoe_type']) ?>
                </p>

                <div class="price-row">

                    <h2>
                        ₱<?= number_format((float) $product['price'], 2) ?>
                    </h2>

                    <span class="stock-summary <?= (int) $product['stock'] > 0 ? 'in-stock' : 'out-stock' ?>">
                        <?= (int) $product['stock'] > 0
                            ? 'IN STOCK'
                            : 'OUT OF STOCK'
                        ?>
                    </span>

                </div>


                <?php if (!empty($cartMessage)): ?>
                    <div class="notice success">
                        <?= htmlspecialchars($cartMessage) ?>
                    </div>
                <?php endif; ?>


                <?php if (!empty($cartError)): ?>
                    <div class="notice error">
                        <?= htmlspecialchars($cartError) ?>
                    </div>
                <?php endif; ?>


                <div class="product-size-section">

                    <div class="section-row">

                        <div>
                            <p class="section-label">
                                SELECT SIZE
                            </p>

                            <h3>
                                <?= htmlspecialchars($sizeLabel) ?>
                            </h3>
                        </div>

                        <span class="size-guide">
                            SIZE GUIDE
                        </span>

                    </div>


                    <?php if (!empty($productSizes)): ?>

                        <div class="product-size-options">

                            <?php foreach ($productSizes as $size): ?>

                                <?php
                                $sizeStock = (int) $size['stock'];
                                ?>

                                <button
                                    type="button"
                                    class="size-button <?= $sizeStock <= 0 ? 'sold-out' : '' ?>"
                                    data-size="<?= htmlspecialchars($size['size']) ?>"
                                    data-stock="<?= $sizeStock ?>"
                                    <?= $sizeStock <= 0 ? 'disabled' : '' ?>
                                >
                                    <?= htmlspecialchars($size['size']) ?>
                                </button>

                            <?php endforeach; ?>

                        </div>


                        <p
                            id="sizeMessage"
                            class="size-message"
                        >
                            Select an available size.
                        </p>

                    <?php else: ?>

                        <p class="size-message">
                            No size information available.
                        </p>

                    <?php endif; ?>

                </div>


                <div class="quantity-section">

                    <div>
                        <p class="section-label">
                            QUANTITY
                        </p>

                        <h3>
                            Choose quantity
                        </h3>
                    </div>


                    <div class="quantity-control">

                        <button
                            type="button"
                            id="decreaseQuantity"
                            aria-label="Decrease quantity"
                        >
                            −
                        </button>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            readonly
                        >

                        <button
                            type="button"
                            id="increaseQuantity"
                            aria-label="Increase quantity"
                        >
                            +
                        </button>

                    </div>

                </div>


                <form
                    method="POST"
                    action="product_details.php?id=<?= (int) $product['id'] ?>"
                    id="addToCartForm"
                    class="purchase-form"
                >

                    <input
                        type="hidden"
                        name="selected_size"
                        id="selectedSizeInput"
                        value=""
                    >

                    <input
                        type="hidden"
                        name="quantity"
                        id="cartQuantityInput"
                        value="1"
                    >

                    <button
                        type="submit"
                        name="add_to_cart"
                        id="addToCartButton"
                        class="add-cart-button"
                        disabled
                    >
                        ADD TO CART
                    </button>

                </form>


                <?php if (isset($_SESSION['user_id'])): ?>

                    <form
                        method="POST"
                        action="product_details.php?id=<?= (int) $product['id'] ?>"
                        class="details-wishlist-form"
                    >

                        <button
                            type="submit"
                            name="toggle_wishlist"
                            class="product-wishlist-button <?= $isWishlisted ? 'active' : '' ?>"
                        >
                            <span>
                                <?= $isWishlisted ? '♥' : '♡' ?>
                            </span>

                            <?= $isWishlisted
                                ? 'REMOVE FROM WISHLIST'
                                : 'ADD TO WISHLIST'
                            ?>
                        </button>

                    </form>

                <?php endif; ?>


                <div class="service-grid">

                    <div>
                        <strong>100% AUTHENTIC</strong>
                        <span>Original products only</span>
                    </div>

                    <div>
                        <strong>SECURE CHECKOUT</strong>
                        <span>Protected ordering</span>
                    </div>

                    <div>
                        <strong>EASY RETURNS</strong>
                        <span>Simple return process</span>
                    </div>

                </div>

            </div>

        </section>


        <section class="product-description">

            <div class="description-heading">

                <p class="section-label">
                    PRODUCT INFORMATION
                </p>

                <h2>
                    DETAILS
                </h2>

            </div>

            <div class="description-content">

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $product['description']
                        )
                    ) ?>
                </p>

                <div class="description-facts">

                    <div>
                        <span>BRAND</span>
                        <strong>
                            <?= htmlspecialchars($product['brand']) ?>
                        </strong>
                    </div>

                    <div>
                        <span>CATEGORY</span>
                        <strong>
                            <?= htmlspecialchars($product['category']) ?>
                        </strong>
                    </div>

                    <div>
                        <span>STYLE</span>
                        <strong>
                            <?= htmlspecialchars($product['shoe_type']) ?>
                        </strong>
                    </div>

                    <div>
                        <span>TOTAL STOCK</span>
                        <strong>
                            <?= (int) $product['stock'] ?>
                        </strong>
                    </div>

                </div>

            </div>

        </section>

    </main>

<?php endif; ?>


<?php if ($product): ?>

<script>

const sizeButtons =
    document.querySelectorAll(
        '.size-button:not(:disabled)'
    );

const quantityInput =
    document.getElementById('quantity');

const decreaseButton =
    document.getElementById('decreaseQuantity');

const increaseButton =
    document.getElementById('increaseQuantity');

const addToCartButton =
    document.getElementById('addToCartButton');

const sizeMessage =
    document.getElementById('sizeMessage');

const selectedSizeInput =
    document.getElementById('selectedSizeInput');

const cartQuantityInput =
    document.getElementById('cartQuantityInput');


let selectedSize = null;
let selectedSizeStock = 0;


sizeButtons.forEach(function(button) {

    button.addEventListener(
        'click',
        function() {

            // If the selected size is clicked again,
            // deselect/cancel it.
            if (button.classList.contains('selected')) {

                button.classList.remove('selected');

                selectedSize = null;
                selectedSizeStock = 0;

                selectedSizeInput.value = '';

                quantityInput.value = 1;
                cartQuantityInput.value = 1;

                addToCartButton.disabled = true;

                sizeMessage.textContent =
                    'Select an available size.';

                return;
            }


            // Remove selection from other sizes.
            sizeButtons.forEach(
                function(item) {

                    item.classList.remove(
                        'selected'
                    );
                }
            );


            // Select clicked size.
            button.classList.add(
                'selected'
            );

            selectedSize =
                button.dataset.size;

            selectedSizeStock =
                parseInt(
                    button.dataset.stock
                );

            selectedSizeInput.value =
                selectedSize;

            quantityInput.value = 1;
            cartQuantityInput.value = 1;

            addToCartButton.disabled = false;

            sizeMessage.textContent =
                selectedSizeStock +
                ' available for size ' +
                selectedSize;
        }
    );
});

decreaseButton.addEventListener(
    'click',
    function() {

        let quantity =
            parseInt(
                quantityInput.value
            );

        if (quantity > 1) {

            quantity--;

            quantityInput.value =
                quantity;

            cartQuantityInput.value =
                quantity;
        }
    }
);


increaseButton.addEventListener(
    'click',
    function() {

        if (selectedSize === null) {

            sizeMessage.textContent =
                'Please select a size first.';

            return;
        }

        let quantity =
            parseInt(
                quantityInput.value
            );

        if (quantity < selectedSizeStock) {

            quantity++;

            quantityInput.value =
                quantity;

            cartQuantityInput.value =
                quantity;

        } else {

            sizeMessage.textContent =
                'Only ' +
                selectedSizeStock +
                ' available for size ' +
                selectedSize +
                '.';
        }
    }
);

</script>

<?php endif; ?>

</body>

</html>
