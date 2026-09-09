<?php

session_start();

require_once '../database/config.php';


// ========================================
// LOGIN PROTECTION
// ========================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login/login.php");
    exit;
}


$pdo = getConnection();

$userId = (int) $_SESSION['user_id'];

$error = '';


// ========================================
// REMOVE CART ITEM
// ========================================

if (
    isset($_POST['cart_action']) &&
    $_POST['cart_action'] === 'remove'
) {

    $cartId = (int) ($_POST['cart_id'] ?? 0);


    $deleteStmt = $pdo->prepare(
        "DELETE FROM cart
         WHERE id = :id
         AND user_id = :user_id"
    );

    $deleteStmt->bindValue(
        ':id',
        $cartId,
        PDO::PARAM_INT
    );

    $deleteStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $deleteStmt->execute();


    header(
        "Location: cart.php?success=" .
        urlencode("Item removed from cart.")
    );

    exit;
}


// ========================================
// UPDATE CART QUANTITY
// ========================================

if (
    isset($_POST['cart_action']) &&
    $_POST['cart_action'] === 'update'
) {

    $cartId =
        (int) ($_POST['cart_id'] ?? 0);

    $quantity =
        filter_var(
            $_POST['quantity'] ?? '',
            FILTER_VALIDATE_INT
        );


    if (
        $cartId <= 0 ||
        $quantity === false ||
        $quantity < 1
    ) {

        $error =
            'Please enter a valid quantity.';

    } else {


        // ========================================
        // GET CART ITEM + SIZE STOCK
        // ========================================

        $checkStmt = $pdo->prepare(
            "SELECT
                c.id,
                c.product_id,
                c.size,
                ps.stock
             FROM cart c

             INNER JOIN product_sizes ps
                ON ps.product_id = c.product_id
                AND ps.size = c.size

             WHERE c.id = :cart_id
             AND c.user_id = :user_id"
        );


        $checkStmt->bindValue(
            ':cart_id',
            $cartId,
            PDO::PARAM_INT
        );

        $checkStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $checkStmt->execute();


        $cartItem =
            $checkStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$cartItem) {

            $error =
                'Cart item was not found.';

        } else {

            $availableStock =
                (int) $cartItem['stock'];


            // ========================================
            // CHECK STOCK
            // ========================================

            if ($quantity > $availableStock) {

                $error =
                    'Only ' .
                    $availableStock .
                    ' available for size ' .
                    $cartItem['size'] .
                    '.';

            } else {


                // ========================================
                // UPDATE QUANTITY
                // ========================================

                $updateStmt = $pdo->prepare(
                    "UPDATE cart
                     SET quantity = :quantity
                     WHERE id = :id
                     AND user_id = :user_id"
                );


                $updateStmt->bindValue(
                    ':quantity',
                    $quantity,
                    PDO::PARAM_INT
                );

                $updateStmt->bindValue(
                    ':id',
                    $cartId,
                    PDO::PARAM_INT
                );

                $updateStmt->bindValue(
                    ':user_id',
                    $userId,
                    PDO::PARAM_INT
                );


                $updateStmt->execute();


                header(
                    "Location: cart.php?success=" .
                    urlencode("Cart updated successfully.")
                );

                exit;
            }
        }
    }
}


// ========================================
// LOAD CART ITEMS
// ========================================

$cartStmt = $pdo->prepare(
    "SELECT

        c.id AS cart_id,
        c.product_id,
        c.size,
        c.quantity,

        p.name,
        p.brand,
        p.category,
        p.shoe_type,
        p.price,
        p.image,

        ps.stock AS size_stock

     FROM cart c

     INNER JOIN products p
        ON p.id = c.product_id

     INNER JOIN product_sizes ps
        ON ps.product_id = c.product_id
        AND ps.size = c.size

     WHERE c.user_id = :user_id

     ORDER BY c.created_at DESC"
);


$cartStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$cartStmt->execute();


$cartItems = $cartStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// CART TOTALS
// ========================================

$subtotal = 0;
$totalItems = 0;


foreach ($cartItems as $item) {

    $itemTotal =
        (float) $item['price'] *
        (int) $item['quantity'];

    $subtotal += $itemTotal;

    $totalItems +=
        (int) $item['quantity'];
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
        My Cart - NexStep
    </title>


    <link
        rel="stylesheet"
        href="/webapp/cart/cart.css?v=3"
    >

</head>

<body>

<header class="shop-header">
    <a href="../homepage/index.php" class="shop-logo">
        <img src="../homepage/images/nexstep-logo.png" alt="NexStep">
    </a>
    <nav>
        <a href="../products/products.php?category=Men">MEN</a>
        <a href="../products/products.php?category=Women">WOMEN</a>
        <a href="../products/products.php?category=Kids">KIDS</a>
        <a href="../homepage/index.php#brands">BRANDS</a>
        <a href="../products/products.php?filter=new">NEW ARRIVALS</a>
        <a href="../products/products.php?filter=sale">SALE</a>
    </nav>
    <div class="header-actions">
        <a href="../wishlist/wishlist.php">WISHLIST</a>
        <a href="../homepage/index.php" class="back-home">HOME</a>
    </div>
</header>

<!-- ========================================
     CART PAGE
======================================== -->

<main class="cart-page">


    <!-- CART HEADER -->

    <div class="cart-header">

        <a href="../products/products.php">
            ← Continue Shopping
        </a>

        <h1>
            My Cart
        </h1>

        <p>
            <?= $totalItems ?>
            item<?= $totalItems !== 1 ? 's' : '' ?>
        </p>

    </div>


    <!-- ========================================
         SUCCESS MESSAGE
    ======================================== -->

    <?php if (isset($_GET['success'])): ?>

        <div class="cart-message success">

            <?= htmlspecialchars(
                $_GET['success']
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================
         ERROR MESSAGE
    ======================================== -->

    <?php if (!empty($error)): ?>

        <div class="cart-message error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <?php if (empty($cartItems)): ?>


        <!-- ========================================
             EMPTY CART
        ======================================== -->

        <div class="empty-cart">

            <h2>
                Your cart is empty.
            </h2>

            <p>
                Find a pair you like and add it to your cart.
            </p>

            <a href="../products/products.php">
                SHOP SHOES
            </a>

        </div>


    <?php else: ?>


        <div class="cart-layout">


            <!-- ========================================
                 CART ITEMS
            ======================================== -->

            <section class="cart-items">


                <?php foreach ($cartItems as $item): ?>

                    <?php

                    $itemTotal =
                        (float) $item['price'] *
                        (int) $item['quantity'];

                    ?>


                    <article class="cart-item">


                        <!-- PRODUCT IMAGE -->

                        <a
                            href="../products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                            class="cart-item-image"
                        >

                            <img
                                src="../uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                alt="<?= htmlspecialchars($item['name']) ?>"
                            >

                        </a>


                        <!-- PRODUCT INFORMATION -->

                        <div class="cart-item-info">


                            <p class="cart-brand">

                                <?= htmlspecialchars(
                                    $item['brand']
                                ) ?>

                            </p>


                            <h2>

                                <a
                                    href="../products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $item['name']
                                    ) ?>

                                </a>

                            </h2>


                            <p>

                                <?= htmlspecialchars(
                                    $item['category']
                                ) ?>

                                /

                                <?= htmlspecialchars(
                                    $item['shoe_type']
                                ) ?>

                            </p>


                            <p>

                                Size:

                                <strong>
                                    <?= htmlspecialchars(
                                        $item['size']
                                    ) ?>
                                </strong>

                            </p>


                            <p>

                                Available:

                                <?= (int) $item['size_stock'] ?>

                            </p>


                            <p class="cart-price">

                                ₱<?= number_format(
                                    $item['price'],
                                    2
                                ) ?>

                            </p>


                            <!-- ========================================
                                 UPDATE QUANTITY
                            ======================================== -->

                            <form
                                method="POST"
                                action="cart.php"
                                class="cart-update-form"
                            >

                                <input
                                    type="hidden"
                                    name="cart_id"
                                    value="<?= (int) $item['cart_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="cart_action"
                                    value="update"
                                >


                                <label>
                                    Quantity
                                </label>


                                <input
                                    type="number"
                                    name="quantity"
                                    value="<?= (int) $item['quantity'] ?>"
                                    min="1"
                                    max="<?= (int) $item['size_stock'] ?>"
                                    required
                                >


                                <button type="submit">
                                    UPDATE
                                </button>

                            </form>


                            <!-- ========================================
                                 REMOVE ITEM
                            ======================================== -->

                            <form
                                method="POST"
                                action="cart.php"
                                class="cart-remove-form"
                            >

                                <input
                                    type="hidden"
                                    name="cart_id"
                                    value="<?= (int) $item['cart_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="cart_action"
                                    value="remove"
                                >


                                <button type="submit">
                                    REMOVE
                                </button>

                            </form>


                        </div>


                        <!-- ITEM TOTAL -->

                        <div class="cart-item-total">

                            <strong>

                                ₱<?= number_format(
                                    $itemTotal,
                                    2
                                ) ?>

                            </strong>

                        </div>


                    </article>


                <?php endforeach; ?>


            </section>


            <!-- ========================================
                 ORDER SUMMARY
            ======================================== -->

            <aside class="cart-summary">


                <h2>
                    Order Summary
                </h2>


                <div class="summary-row">

                    <span>
                        Items
                    </span>

                    <span>
                        <?= $totalItems ?>
                    </span>

                </div>


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <span>

                        ₱<?= number_format(
                            $subtotal,
                            2
                        ) ?>

                    </span>

                </div>


                <hr>


                <div class="summary-total">

                    <strong>
                        Total
                    </strong>

                    <strong>

                        ₱<?= number_format(
                            $subtotal,
                            2
                        ) ?>

                    </strong>

                </div>


                    <a
                        href="../checkout/checkout.php"
                        class="checkout-button"
                    >
                        CHECKOUT
                    </a>


            </aside>


        </div>


    <?php endif; ?>


</main>


</body>

</html>