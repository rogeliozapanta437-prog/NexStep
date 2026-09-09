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
// LOAD CART ITEMS
// ========================================

$cartStmt = $pdo->prepare(
    "SELECT
        c.id AS cart_id,
        c.product_id,
        c.size,
        c.quantity,

        p.name,
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
// EMPTY CART PROTECTION
// ========================================

$isOrderSuccess =
    isset($_GET['success']) &&
    $_GET['success'] == 1 &&
    isset($_GET['order_id']);


if (
    empty($cartItems) &&
    !$isOrderSuccess
) {

    header("Location: ../cart/cart.php");
    exit;
}

// ========================================
// TOTAL
// ========================================

$totalAmount = 0;

foreach ($cartItems as $item) {

    $totalAmount +=
        (float) $item['price'] *
        (int) $item['quantity'];
}


// ========================================
// PLACE ORDER
// ========================================

if (isset($_POST['place_order'])) {

    $fullName =
        trim($_POST['full_name'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $streetAddress =
        trim($_POST['street_address'] ?? '');

    $barangay =
        trim($_POST['barangay'] ?? '');

    $city =
        trim($_POST['city'] ?? '');

    $province =
        trim($_POST['province'] ?? '');

    $postalCode =
        trim($_POST['postal_code'] ?? '');

    $paymentMethod =
        'Cash on Delivery';


    // ========================================
    // VALIDATION
    // ========================================

    if (
        empty($fullName) ||
        empty($email) ||
        empty($phone) ||
        empty($streetAddress) ||
        empty($barangay) ||
        empty($city) ||
        empty($province) ||
        empty($postalCode)
    ) {

        $error =
            'Please complete all required fields.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } else {

        try {

            $pdo->beginTransaction();


            // ========================================
            // RECHECK STOCK
            // ========================================

            foreach ($cartItems as $item) {

                $stockStmt = $pdo->prepare(
                    "SELECT stock
                     FROM product_sizes
                     WHERE product_id = :product_id
                     AND size = :size
                     FOR UPDATE"
                );

                $stockStmt->bindValue(
                    ':product_id',
                    (int) $item['product_id'],
                    PDO::PARAM_INT
                );

                $stockStmt->bindValue(
                    ':size',
                    $item['size']
                );

                $stockStmt->execute();

                $sizeRow =
                    $stockStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$sizeRow ||
                    (int) $sizeRow['stock']
                    < (int) $item['quantity']
                ) {

                    throw new Exception(
                        'Not enough stock for ' .
                        $item['name'] .
                        ' size ' .
                        $item['size'] .
                        '.'
                    );
                }
            }


            // ========================================
            // CREATE ORDER
            // ========================================

            $orderStmt = $pdo->prepare(
                "INSERT INTO orders
                (
                    user_id,
                    full_name,
                    email,
                    phone,
                    street_address,
                    barangay,
                    city,
                    province,
                    postal_code,
                    payment_method,
                    total_amount,
                    status
                )
                VALUES
                (
                    :user_id,
                    :full_name,
                    :email,
                    :phone,
                    :street_address,
                    :barangay,
                    :city,
                    :province,
                    :postal_code,
                    :payment_method,
                    :total_amount,
                    'Pending'
                )"
            );

            $orderStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $orderStmt->bindValue(
                ':full_name',
                $fullName
            );

            $orderStmt->bindValue(
                ':email',
                $email
            );

            $orderStmt->bindValue(
                ':phone',
                $phone
            );

            $orderStmt->bindValue(
                ':street_address',
                $streetAddress
            );

            $orderStmt->bindValue(
                ':barangay',
                $barangay
            );

            $orderStmt->bindValue(
                ':city',
                $city
            );

            $orderStmt->bindValue(
                ':province',
                $province
            );

            $orderStmt->bindValue(
                ':postal_code',
                $postalCode
            );

            $orderStmt->bindValue(
                ':payment_method',
                $paymentMethod
            );

            $orderStmt->bindValue(
                ':total_amount',
                $totalAmount
            );

            $orderStmt->execute();


            $orderId =
                (int) $pdo->lastInsertId();


            // ========================================
            // ORDER ITEMS
            // ========================================

            $orderItemStmt = $pdo->prepare(
                "INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    size,
                    quantity,
                    price
                )
                VALUES
                (
                    :order_id,
                    :product_id,
                    :size,
                    :quantity,
                    :price
                )"
            );


            foreach ($cartItems as $item) {

                $orderItemStmt->bindValue(
                    ':order_id',
                    $orderId,
                    PDO::PARAM_INT
                );

                $orderItemStmt->bindValue(
                    ':product_id',
                    (int) $item['product_id'],
                    PDO::PARAM_INT
                );

                $orderItemStmt->bindValue(
                    ':size',
                    $item['size']
                );

                $orderItemStmt->bindValue(
                    ':quantity',
                    (int) $item['quantity'],
                    PDO::PARAM_INT
                );

                $orderItemStmt->bindValue(
                    ':price',
                    $item['price']
                );

                $orderItemStmt->execute();


                // ========================================
                // DEDUCT SIZE STOCK
                // ========================================

                $deductStmt = $pdo->prepare(
                    "UPDATE product_sizes
                     SET stock = stock - :quantity
                     WHERE product_id = :product_id
                     AND size = :size"
                );

                $deductStmt->bindValue(
                    ':quantity',
                    (int) $item['quantity'],
                    PDO::PARAM_INT
                );

                $deductStmt->bindValue(
                    ':product_id',
                    (int) $item['product_id'],
                    PDO::PARAM_INT
                );

                $deductStmt->bindValue(
                    ':size',
                    $item['size']
                );

                $deductStmt->execute();


                // ========================================
                // RECALCULATE PRODUCT TOTAL STOCK
                // ========================================

                $totalStockStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock = (
                        SELECT COALESCE(
                            SUM(stock),
                            0
                        )
                        FROM product_sizes
                        WHERE product_id = :product_id
                     )
                     WHERE id = :product_id"
                );

                $totalStockStmt->bindValue(
                    ':product_id',
                    (int) $item['product_id'],
                    PDO::PARAM_INT
                );

                $totalStockStmt->execute();
            }


            // ========================================
            // CLEAR CART
            // ========================================

            $clearCartStmt = $pdo->prepare(
                "DELETE FROM cart
                 WHERE user_id = :user_id"
            );

            $clearCartStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $clearCartStmt->execute();


            $pdo->commit();


            header(
                "Location: checkout.php?success=1&order_id=" .
                $orderId
            );

            exit;


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }

            $error =
                $e->getMessage();
        }
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

    <title>Checkout - NexStep</title>

        <link
            rel="stylesheet"
            href="/webapp/checkout/checkout.css?v=2"
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
        <a href="../products/products.php?category=Men">MEN</a>
        <a href="../products/products.php?category=Women">WOMEN</a>
        <a href="../products/products.php?category=Kids">KIDS</a>
        <a href="../homepage/index.php#brands">BRANDS</a>
        <a href="../products/products.php?filter=new">NEW ARRIVALS</a>
        <a href="../products/products.php?filter=sale">SALE</a>
    </nav>

    <div class="header-actions">
        <a href="../wishlist/wishlist.php">WISHLIST</a>

        <a
            href="../homepage/index.php"
            class="back-home"
        >
            HOME
        </a>
    </div>

</header>


<?php if (
    isset($_GET['success']) &&
    $_GET['success'] == 1
): ?>

    <!-- ========================================
         ORDER SUCCESS
    ======================================== -->

    <main class="success-page">

        <section class="success-card">

            <div class="success-icon">
                ✓
            </div>

            <p class="eyebrow">
                ORDER CONFIRMED
            </p>

            <h1>
                ORDER PLACED SUCCESSFULLY
            </h1>

            <p class="success-copy">
                Thank you for shopping with NexStep.
                Your order has been received.
            </p>

            <div class="success-details">

                <div>
                    <span>ORDER NUMBER</span>

                    <strong>
                        #<?= (int) ($_GET['order_id'] ?? 0) ?>
                    </strong>
                </div>

                <div>
                    <span>PAYMENT METHOD</span>

                    <strong>
                        Cash on Delivery
                    </strong>
                </div>

            </div>

            <div class="success-actions">

                <a
                    href="../orders/orders.php"
                    class="secondary-button"
                >
                    VIEW MY ORDERS
                </a>

                <a
                    href="../homepage/index.php"
                    class="primary-button"
                >
                    RETURN TO HOME
                </a>

            </div>

        </section>

    </main>


<?php else: ?>


    <!-- ========================================
         CHECKOUT PAGE
    ======================================== -->

    <main class="checkout-page">

        <header class="checkout-heading">

            <a
                href="../cart/cart.php"
                class="back-cart"
            >
                ← BACK TO CART
            </a>

            <p class="eyebrow">
                SECURE CHECKOUT
            </p>

            <h1>
                CHECKOUT
            </h1>

            <p class="heading-copy">
                Complete your information below
                to place your order.
            </p>

        </header>


        <?php if (!empty($error)): ?>

            <div class="checkout-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="checkout-layout">


            <!-- ========================================
                 CUSTOMER / SHIPPING FORM
            ======================================== -->

            <section class="checkout-form-panel">

                <form
                    method="POST"
                    action="checkout.php"
                    class="checkout-form"
                >

                    <section class="form-section">

                        <div class="section-heading">

                            <span>01</span>

                            <div>
                                <h2>
                                    CONTACT INFORMATION
                                </h2>

                                <p>
                                    How we can contact you
                                    about your order.
                                </p>
                            </div>

                        </div>


                        <div class="form-grid">

                            <div class="form-group full">

                                <label for="full_name">
                                    Full Name
                                </label>

                                <input
                                    id="full_name"
                                    type="text"
                                    name="full_name"
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                    autocomplete="name"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="email">
                                    Email
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    autocomplete="email"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="phone">
                                    Phone Number
                                </label>

                                <input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                    autocomplete="tel"
                                    required
                                >

                            </div>

                        </div>

                    </section>


                    <section class="form-section">

                        <div class="section-heading">

                            <span>02</span>

                            <div>
                                <h2>
                                    SHIPPING ADDRESS
                                </h2>

                                <p>
                                    Where your NexStep order
                                    will be delivered.
                                </p>
                            </div>

                        </div>


                        <div class="form-grid">

                            <div class="form-group full">

                                <label for="street_address">
                                    House / Street
                                </label>

                                <input
                                    id="street_address"
                                    type="text"
                                    name="street_address"
                                    value="<?= htmlspecialchars($_POST['street_address'] ?? '') ?>"
                                    autocomplete="street-address"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="barangay">
                                    Barangay
                                </label>

                                <input
                                    id="barangay"
                                    type="text"
                                    name="barangay"
                                    value="<?= htmlspecialchars($_POST['barangay'] ?? '') ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="city">
                                    City / Municipality
                                </label>

                                <input
                                    id="city"
                                    type="text"
                                    name="city"
                                    value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                    autocomplete="address-level2"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="province">
                                    Province
                                </label>

                                <input
                                    id="province"
                                    type="text"
                                    name="province"
                                    value="<?= htmlspecialchars($_POST['province'] ?? '') ?>"
                                    autocomplete="address-level1"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="postal_code">
                                    Postal Code
                                </label>

                                <input
                                    id="postal_code"
                                    type="text"
                                    name="postal_code"
                                    value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>"
                                    autocomplete="postal-code"
                                    required
                                >

                            </div>

                        </div>

                    </section>


                    <section class="form-section payment-section">

                        <div class="section-heading">

                            <span>03</span>

                            <div>
                                <h2>
                                    PAYMENT METHOD
                                </h2>

                                <p>
                                    Payment is collected
                                    when your order arrives.
                                </p>
                            </div>

                        </div>


                        <div class="payment-option">

                            <div class="payment-radio">
                                <span></span>
                            </div>

                            <div>
                                <strong>
                                    CASH ON DELIVERY
                                </strong>

                                <p>
                                    Pay in cash when your
                                    order is delivered.
                                </p>
                            </div>

                        </div>

                    </section>


                    <button
                        type="submit"
                        name="place_order"
                        class="place-order-button"
                    >
                        PLACE ORDER
                    </button>


                    <p class="checkout-note">
                        By placing your order, you confirm
                        that the shipping information above
                        is correct.
                    </p>

                </form>

            </section>


            <!-- ========================================
                 ORDER SUMMARY
            ======================================== -->

            <aside class="order-summary">

                <div class="summary-heading">

                    <h2>
                        ORDER SUMMARY
                    </h2>

                    <span>
                        <?= count($cartItems) ?>
                        <?= count($cartItems) === 1
                            ? 'ITEM'
                            : 'ITEMS'
                        ?>
                    </span>

                </div>


                <div class="summary-products">

                    <?php foreach ($cartItems as $item): ?>

                        <article class="summary-product">

                            <a
                                href="../products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                                class="summary-image"
                            >
                                <img
                                    src="../uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                    alt="<?= htmlspecialchars($item['name']) ?>"
                                >
                            </a>


                            <div class="summary-product-info">

                                <a
                                    href="../products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                                >
                                    <h3>
                                        <?= htmlspecialchars($item['name']) ?>
                                    </h3>
                                </a>

                                <p>
                                    SIZE:
                                    <?= htmlspecialchars($item['size']) ?>
                                </p>

                                <p>
                                    QUANTITY:
                                    <?= (int) $item['quantity'] ?>
                                </p>

                                <strong>
                                    ₱<?= number_format(
                                        (float) $item['price'] *
                                        (int) $item['quantity'],
                                        2
                                    ) ?>
                                </strong>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>


                <div class="summary-calculation">

                    <div>
                        <span>
                            Subtotal
                        </span>

                        <span>
                            ₱<?= number_format(
                                $totalAmount,
                                2
                            ) ?>
                        </span>
                    </div>

                    <div>
                        <span>
                            Shipping
                        </span>

                        <span>
                            FREE
                        </span>
                    </div>

                </div>


                <div class="summary-total">

                    <span>
                        TOTAL
                    </span>

                    <strong>
                        ₱<?= number_format(
                            $totalAmount,
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="summary-benefits">

                    <p>
                        ✓ Secure checkout
                    </p>

                    <p>
                        ✓ Cash on Delivery
                    </p>

                    <p>
                        ✓ Easy order tracking
                    </p>

                </div>

            </aside>


        </div>

    </main>


<?php endif; ?>


</body>
</html>
