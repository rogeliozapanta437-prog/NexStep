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
$success = '';


// ========================================
// CUSTOMER CANCEL ORDER
// ========================================

if (isset($_POST['cancel_order'])) {

    $cancelOrderId =
        (int) ($_POST['order_id'] ?? 0);

    if ($cancelOrderId <= 0) {

        $error =
            'Invalid order.';

    } else {

        try {

            // ========================================
            // START TRANSACTION
            // ========================================

            $pdo->beginTransaction();


            // ========================================
            // LOCK CUSTOMER ORDER
            // ========================================

            $lockOrderStmt = $pdo->prepare(
                "SELECT
                    id,
                    status
                 FROM orders
                 WHERE id = :order_id
                 AND user_id = :user_id
                 FOR UPDATE"
            );

            $lockOrderStmt->bindValue(
                ':order_id',
                $cancelOrderId,
                PDO::PARAM_INT
            );

            $lockOrderStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $lockOrderStmt->execute();

            $lockedOrder =
                $lockOrderStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            // ========================================
            // VALIDATE ORDER
            // ========================================

            if (!$lockedOrder) {

                throw new Exception(
                    'Order not found.'
                );
            }


            if ($lockedOrder['status'] !== 'Pending') {

                throw new Exception(
                    'Only pending orders can be cancelled.'
                );
            }


            // ========================================
            // LOAD ORDER ITEMS
            // ========================================

            $restoreItemsStmt = $pdo->prepare(
                "SELECT
                    product_id,
                    size,
                    quantity
                 FROM order_items
                 WHERE order_id = :order_id"
            );

            $restoreItemsStmt->bindValue(
                ':order_id',
                $cancelOrderId,
                PDO::PARAM_INT
            );

            $restoreItemsStmt->execute();

            $restoreItems =
                $restoreItemsStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            $affectedProductIds = [];


            // ========================================
            // RESTORE EACH SIZE STOCK
            // ========================================

            foreach ($restoreItems as $restoreItem) {

                $productId =
                    (int) $restoreItem['product_id'];

                $size =
                    $restoreItem['size'];

                $quantity =
                    (int) $restoreItem['quantity'];


                $restoreStockStmt = $pdo->prepare(
                    "UPDATE product_sizes
                     SET stock = stock + :quantity
                     WHERE product_id = :product_id
                     AND size = :size"
                );

                $restoreStockStmt->bindValue(
                    ':quantity',
                    $quantity,
                    PDO::PARAM_INT
                );

                $restoreStockStmt->bindValue(
                    ':product_id',
                    $productId,
                    PDO::PARAM_INT
                );

                $restoreStockStmt->bindValue(
                    ':size',
                    $size
                );

                $restoreStockStmt->execute();


                if ($restoreStockStmt->rowCount() === 0) {

                    throw new Exception(
                        'Unable to restore stock for product ID ' .
                        $productId .
                        ', size ' .
                        $size .
                        '.'
                    );
                }


                $affectedProductIds[
                    $productId
                ] = true;
            }


            // ========================================
            // RECALCULATE PRODUCT TOTAL STOCK
            // ========================================

            foreach (
                array_keys($affectedProductIds)
                as $productId
            ) {

                $totalStockStmt = $pdo->prepare(
                    "SELECT
                        COALESCE(
                            SUM(stock),
                            0
                        )
                     FROM product_sizes
                     WHERE product_id = :product_id"
                );

                $totalStockStmt->bindValue(
                    ':product_id',
                    $productId,
                    PDO::PARAM_INT
                );

                $totalStockStmt->execute();

                $totalStock =
                    (int) $totalStockStmt->fetchColumn();


                $updateProductStockStmt =
                    $pdo->prepare(
                        "UPDATE products
                         SET stock = :stock
                         WHERE id = :product_id"
                    );

                $updateProductStockStmt->bindValue(
                    ':stock',
                    $totalStock,
                    PDO::PARAM_INT
                );

                $updateProductStockStmt->bindValue(
                    ':product_id',
                    $productId,
                    PDO::PARAM_INT
                );

                $updateProductStockStmt->execute();
            }


            // ========================================
            // CANCEL ORDER
            // ========================================

            $cancelStmt = $pdo->prepare(
                "UPDATE orders
                 SET status = 'Cancelled'
                 WHERE id = :order_id
                 AND user_id = :user_id"
            );

            $cancelStmt->bindValue(
                ':order_id',
                $cancelOrderId,
                PDO::PARAM_INT
            );

            $cancelStmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $cancelStmt->execute();


            // ========================================
            // COMMIT
            // ========================================

            $pdo->commit();


            header(
                "Location: orders.php?success=" .
                urlencode(
                    "Order #" .
                    $cancelOrderId .
                    " cancelled successfully."
                )
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


// ========================================
// LOAD CUSTOMER ORDERS
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT *
     FROM orders
     WHERE user_id = :user_id
     ORDER BY created_at DESC"
);

$orderStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$orderStmt->execute();

$orders = $orderStmt->fetchAll(
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

    <title>My Orders - NexStep</title>

    <link
        rel="stylesheet"
        href="orders.css"
    >

</head>

<body>


<!-- ========================================
     HEADER
======================================== -->

<header class="shop-header">

    <a
        href="/webapp/homepage/index.php"
        class="shop-logo"
    >
        <img
            src="/webapp/homepage/images/nexstep-logo.png"
            alt="NexStep"
            width="200"
            style="width:200px; max-width:200px; height:auto; display:block;"
        >
    </a>

    <nav>
        <a href="/webapp/products/products.php?category=Men">MEN</a>
        <a href="/webapp/products/products.php?category=Women">WOMEN</a>
        <a href="/webapp/products/products.php?category=Kids">KIDS</a>
        <a href="/webapp/homepage/index.php#brands">BRANDS</a>
        <a href="/webapp/products/products.php?filter=new">NEW ARRIVALS</a>
        <a href="/webapp/products/products.php?filter=sale">SALE</a>
    </nav>

    <div class="header-actions">
        <a href="/webapp/wishlist/wishlist.php">WISHLIST</a>
        <a href="/webapp/cart/cart.php">CART</a>

        <a
            href="/webapp/homepage/index.php"
            class="back-home"
        >
            HOME
        </a>
    </div>

</header>


<!-- ========================================
     PAGE HEADING
======================================== -->

<section class="orders-heading">

    <a
        href="/webapp/homepage/index.php"
        class="back-link"
    >
        ← BACK TO HOME
    </a>

    <p class="eyebrow">
        ORDER HISTORY
    </p>

    <h1>
        MY ORDERS
    </h1>

    <span>
        <?= count($orders) ?>

        <?= count($orders) === 1
            ? 'ORDER'
            : 'ORDERS'
        ?>
    </span>

</section>


<main class="orders-page">


    <?php if (isset($_GET['success'])): ?>

        <div class="orders-message success">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="orders-message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if (empty($orders)): ?>


        <!-- ========================================
             EMPTY ORDERS
        ======================================== -->

        <section class="empty-orders">

            <div class="empty-icon">
                □
            </div>

            <h2>
                You have no orders yet.
            </h2>

            <p>
                Your completed purchases will appear here.
            </p>

            <a
                href="/webapp/products/products.php"
                class="shop-button"
            >
                SHOP SHOES
            </a>

        </section>


    <?php else: ?>


        <div class="orders-list">


            <?php foreach ($orders as $order): ?>


                <?php

                $itemStmt = $pdo->prepare(
                    "SELECT
                        oi.product_id,
                        oi.size,
                        oi.quantity,
                        oi.price,
                        p.name,
                        p.image
                     FROM order_items oi
                     INNER JOIN products p
                        ON p.id = oi.product_id
                     WHERE oi.order_id = :order_id
                     ORDER BY oi.id ASC"
                );

                $itemStmt->bindValue(
                    ':order_id',
                    (int) $order['id'],
                    PDO::PARAM_INT
                );

                $itemStmt->execute();

                $orderItems = $itemStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

                $statusClass =
                    strtolower(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $order['status']
                        )
                    );

                ?>


                <section class="order-card">


                    <!-- ========================================
                         ORDER HEADER
                    ======================================== -->

                    <div class="order-header">

                        <div class="order-number">

                            <p>
                                ORDER NUMBER
                            </p>

                            <strong>
                                #<?= (int) $order['id'] ?>
                            </strong>

                        </div>


                        <div class="order-date">

                            <p>
                                ORDER DATE
                            </p>

                            <strong>
                                <?= htmlspecialchars(
                                    date(
                                        'F j, Y',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    )
                                ) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars(
                                    date(
                                        'g:i A',
                                        strtotime(
                                            $order['created_at']
                                        )
                                    )
                                ) ?>
                            </span>

                        </div>


                        <div class="order-status-wrap">

                            <p>
                                STATUS
                            </p>

                            <span class="order-status <?= htmlspecialchars($statusClass) ?>">
                                <?= htmlspecialchars(
                                    strtoupper($order['status'])
                                ) ?>
                            </span>


                            <?php if ($order['status'] === 'Pending'): ?>

                                <form
                                    method="POST"
                                    action="orders.php"
                                    class="cancel-order-form"
                                    onsubmit="return confirm('Are you sure you want to cancel this order?');"
                                >

                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int) $order['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="cancel_order"
                                        class="cancel-order-button"
                                    >
                                        CANCEL ORDER
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- ========================================
                         ORDER ITEMS
                    ======================================== -->

                    <div class="order-items">


                        <?php foreach ($orderItems as $item): ?>


                            <article class="order-item">

                                <a
                                    href="/webapp/products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                                    class="order-item-image"
                                >
                                    <img
                                        src="/webapp/uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                    >
                                </a>


                                <div class="order-item-info">

                                    <a
                                        href="/webapp/products/product_details.php?id=<?= (int) $item['product_id'] ?>"
                                    >
                                        <h3>
                                            <?= htmlspecialchars(
                                                $item['name']
                                            ) ?>
                                        </h3>
                                    </a>

                                    <p>
                                        SIZE:
                                        <strong>
                                            <?= htmlspecialchars(
                                                $item['size']
                                            ) ?>
                                        </strong>
                                    </p>

                                    <p>
                                        QUANTITY:
                                        <strong>
                                            <?= (int) $item['quantity'] ?>
                                        </strong>
                                    </p>

                                </div>


                                <div class="order-item-price">

                                    <span>
                                        ITEM PRICE
                                    </span>

                                    <strong>
                                        ₱<?= number_format(
                                            $item['price'],
                                            2
                                        ) ?>
                                    </strong>

                                </div>

                            </article>


                        <?php endforeach; ?>


                    </div>


                    <!-- ========================================
                         ORDER DETAILS
                    ======================================== -->

                    <div class="order-bottom">


                        <div class="order-shipping">

                            <p class="detail-label">
                                SHIPPING INFORMATION
                            </p>

                            <strong>
                                <?= htmlspecialchars(
                                    $order['full_name']
                                ) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars(
                                    $order['phone']
                                ) ?>
                            </span>

                            <p>
                                <?= htmlspecialchars(
                                    $order['street_address']
                                ) ?>,
                                <?= htmlspecialchars(
                                    $order['barangay']
                                ) ?>,
                                <?= htmlspecialchars(
                                    $order['city']
                                ) ?>,
                                <?= htmlspecialchars(
                                    $order['province']
                                ) ?>
                                <?= htmlspecialchars(
                                    $order['postal_code']
                                ) ?>
                            </p>

                        </div>


                        <div class="order-payment">

                            <p class="detail-label">
                                PAYMENT
                            </p>

                            <strong>
                                <?= htmlspecialchars(
                                    $order['payment_method']
                                ) ?>
                            </strong>

                        </div>


                        <div class="order-total">

                            <span>
                                ORDER TOTAL
                            </span>

                            <strong>
                                ₱<?= number_format(
                                    $order['total_amount'],
                                    2
                                ) ?>
                            </strong>

                        </div>


                    </div>


                </section>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


</body>
</html>
