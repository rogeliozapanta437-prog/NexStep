<?php

session_start();

// ========================================
// PROTECT PAGE
// ========================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// DATABASE
// ========================================

require_once '../database/config.php';

$pdo = getConnection();

$userId = (int) $_SESSION['user_id'];


// ========================================
// REMOVE FROM WISHLIST
// ========================================

if (isset($_POST['remove_wishlist'])) {

    $productId =
        (int) ($_POST['product_id'] ?? 0);

    if ($productId > 0) {

        $deleteStmt = $pdo->prepare(
            "DELETE FROM wishlist
             WHERE user_id = :user_id
             AND product_id = :product_id"
        );

        $deleteStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $deleteStmt->bindValue(
            ':product_id',
            $productId,
            PDO::PARAM_INT
        );

        $deleteStmt->execute();
    }

    header("Location: wishlist.php");
    exit;
}


// ========================================
// LOAD WISHLIST PRODUCTS
// ========================================

$stmt = $pdo->prepare(
    "SELECT
        products.id,
        products.name,
        products.brand,
        products.category,
        products.shoe_type,
        products.price,
        products.stock,
        products.image,
        products.is_new,
        products.is_sale,
        wishlist.created_at AS wishlist_date
     FROM wishlist
     INNER JOIN products
        ON wishlist.product_id = products.id
     WHERE wishlist.user_id = :user_id
     ORDER BY wishlist.created_at DESC"
);

$stmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$stmt->execute();

$products = $stmt->fetchAll(
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

    <title>NexStep | My Wishlist</title>

    <link
        rel="stylesheet"
        href="wishlist.css"
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

        <a href="../products/products.php?category=Men">
            MEN
        </a>

        <a href="../products/products.php?category=Women">
            WOMEN
        </a>

        <a href="../products/products.php?category=Kids">
            KIDS
        </a>

        <a href="../homepage/index.php#brands">
            BRANDS
        </a>

        <a href="../products/products.php?filter=new">
            NEW ARRIVALS
        </a>

        <a href="../products/products.php?filter=sale">
            SALE
        </a>

    </nav>


    <div class="header-actions">

        <a href="../cart/cart.php">
            CART
        </a>

        <a
            href="../homepage/index.php"
            class="back-home"
        >
            HOME
        </a>

    </div>

</header>


<!-- ========================================
     PAGE HEADING
======================================== -->

<section class="wishlist-heading">

    <p>
        YOUR FAVORITES
    </p>

    <h1>
        MY WISHLIST
    </h1>

    <span>
        <?= count($products) ?>

        <?= count($products) === 1
            ? 'ITEM'
            : 'ITEMS'
        ?>
    </span>

</section>


<main class="wishlist-page">


    <?php if (empty($products)): ?>


        <!-- ========================================
             EMPTY WISHLIST
        ======================================== -->

        <section class="empty-wishlist">

            <div class="empty-heart">
                ♡
            </div>

            <h2>
                Your wishlist is empty.
            </h2>

            <p>
                Save your favorite shoes and
                find them here later.
            </p>

            <a href="../products/products.php">
                SHOP SHOES
            </a>

        </section>


    <?php else: ?>


        <!-- ========================================
             WISHLIST GRID
        ======================================== -->

        <section class="wishlist-grid">


            <?php foreach ($products as $product): ?>


                <article class="wishlist-card">


                    <div class="wishlist-image">


                        <?php if ($product['is_sale'] == 1): ?>

                            <span class="product-tag sale">
                                SALE
                            </span>

                        <?php elseif ($product['is_new'] == 1): ?>

                            <span class="product-tag">
                                NEW
                            </span>

                        <?php endif; ?>


                        <form
                            method="POST"
                            class="remove-form"
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['id'] ?>"
                            >

                            <button
                                type="submit"
                                name="remove_wishlist"
                                class="remove-heart"
                                title="Remove from Wishlist"
                                aria-label="Remove from Wishlist"
                            >
                                ♥
                            </button>

                        </form>


                        <a
                            href="../products/product_details.php?id=<?= (int) $product['id'] ?>"
                            class="image-link"
                        >

                            <img
                                src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                            >

                        </a>

                    </div>


                    <a
                        href="../products/product_details.php?id=<?= (int) $product['id'] ?>"
                        class="wishlist-info-link"
                    >

                        <div class="wishlist-info">

                            <h2>
                                <?= htmlspecialchars($product['name']) ?>
                            </h2>

                            <p class="wishlist-category">
                                <?= htmlspecialchars($product['category']) ?>
                                ·
                                <?= htmlspecialchars($product['shoe_type']) ?>
                            </p>

                            <p class="wishlist-brand">
                                <?= htmlspecialchars($product['brand']) ?>
                            </p>

                            <strong class="wishlist-price">
                                ₱<?= number_format(
                                    (float) $product['price'],
                                    2
                                ) ?>
                            </strong>

                        </div>

                    </a>


                    <div class="wishlist-card-footer">

                        <span class="stock-state <?= (int) $product['stock'] > 0 ? 'in-stock' : 'out-stock' ?>">
                            <?= (int) $product['stock'] > 0
                                ? 'IN STOCK'
                                : 'OUT OF STOCK'
                            ?>
                        </span>

                        <a
                            href="../products/product_details.php?id=<?= (int) $product['id'] ?>"
                            class="view-product"
                        >
                            VIEW PRODUCT
                        </a>

                    </div>


                </article>


            <?php endforeach; ?>


        </section>


    <?php endif; ?>


</main>


</body>

</html>
