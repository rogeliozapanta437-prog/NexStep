<?php

session_start();


// ========================================
// LOGIN PROTECTION
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

$orderId = (int) ($_GET['id'] ?? 0);


// ========================================
// VALIDATE ORDER ID
// ========================================

if ($orderId <= 0) {
    header("Location: orders.php");
    exit;
}


// ========================================
// LOAD CUSTOMER ORDER
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT *
     FROM orders
     WHERE id = :order_id
     AND user_id = :user_id
     LIMIT 1"
);

$orderStmt->bindValue(
    ':order_id',
    $orderId,
    PDO::PARAM_INT
);

$orderStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$orderStmt->execute();

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);


// ========================================
// ORDER MUST BELONG TO CUSTOMER
// ========================================

if (!$order) {
    header("Location: orders.php");
    exit;
}


// ========================================
// LOAD ORDER ITEMS
// ========================================

$itemStmt = $pdo->prepare(
    "SELECT
        oi.product_id,
        oi.size,
        oi.quantity,
        oi.price,
        p.name
     FROM order_items oi
     INNER JOIN products p
        ON p.id = oi.product_id
     WHERE oi.order_id = :order_id
     ORDER BY oi.id ASC"
);

$itemStmt->bindValue(
    ':order_id',
    $orderId,
    PDO::PARAM_INT
);

$itemStmt->execute();

$orderItems = $itemStmt->fetchAll(
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

    <title>
        Receipt #<?= (int) $order['id'] ?> | NexStep
    </title>

    <link
        rel="stylesheet"
        href="/webapp/orders/receipt.css?v=1"
    >

</head>

<body>


<div class="receipt-actions">

    <a href="orders.php">
        BACK TO ORDERS
    </a>

    <button
        type="button"
        onclick="window.print()"
    >
        PRINT RECEIPT
    </button>

</div>


<main class="receipt">


    <header class="receipt-header">

        <img
            src="/webapp/homepage/images/nexstep-logo.png"
            alt="NexStep"
        >

        <p>ORDER RECEIPT</p>

    </header>


    <section class="receipt-order-info">

        <div>
            <span>ORDER NUMBER</span>

            <strong>
                #<?= (int) $order['id'] ?>
            </strong>
        </div>


        <div>
            <span>ORDER DATE</span>

            <strong>
                <?= htmlspecialchars(
                    date(
                        'F j, Y',
                        strtotime($order['created_at'])
                    )
                ) ?>
            </strong>
        </div>


        <div>
            <span>STATUS</span>

            <strong>
                <?= htmlspecialchars(
                    strtoupper($order['status'])
                ) ?>
            </strong>
        </div>

    </section>


    <section class="receipt-section">

        <h2>SHIPPING INFORMATION</h2>

        <strong>
            <?= htmlspecialchars($order['full_name']) ?>
        </strong>

        <p>
            <?= htmlspecialchars($order['phone']) ?>
        </p>

        <p>
            <?= htmlspecialchars($order['street_address']) ?>,
            <?= htmlspecialchars($order['barangay']) ?>,
            <?= htmlspecialchars($order['city']) ?>,
            <?= htmlspecialchars($order['province']) ?>
            <?= htmlspecialchars($order['postal_code']) ?>
        </p>

    </section>


    <section class="receipt-section">

        <h2>ORDER ITEMS</h2>


        <div class="receipt-table">

            <div class="receipt-row receipt-table-header">
                <span>PRODUCT</span>
                <span>SIZE</span>
                <span>QTY</span>
                <span>PRICE</span>
                <span>SUBTOTAL</span>
            </div>


            <?php foreach ($orderItems as $item): ?>

                <?php

                $subtotal =
                    (float) $item['price'] *
                    (int) $item['quantity'];

                ?>

                <div class="receipt-row">

                    <span>
                        <?= htmlspecialchars($item['name']) ?>
                    </span>

                    <span>
                        <?= htmlspecialchars($item['size']) ?>
                    </span>

                    <span>
                        <?= (int) $item['quantity'] ?>
                    </span>

                    <span>
                        ₱<?= number_format(
                            (float) $item['price'],
                            2
                        ) ?>
                    </span>

                    <strong>
                        ₱<?= number_format(
                            $subtotal,
                            2
                        ) ?>
                    </strong>

                </div>

            <?php endforeach; ?>

        </div>

    </section>


    <section class="receipt-summary">

        <div>
            <span>PAYMENT METHOD</span>

            <strong>
                <?= htmlspecialchars(
                    $order['payment_method']
                ) ?>
            </strong>
        </div>


        <div class="receipt-grand-total">

            <span>TOTAL</span>

            <strong>
                ₱<?= number_format(
                    (float) $order['total_amount'],
                    2
                ) ?>
            </strong>

        </div>

    </section>


    <footer class="receipt-footer">

        <strong>THANK YOU FOR SHOPPING WITH NEXSTEP.</strong>

        <p>
            This receipt is associated with
            Order #<?= (int) $order['id'] ?>.
        </p>

    </footer>


</main>


</body>
</html>