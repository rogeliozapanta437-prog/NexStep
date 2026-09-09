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

$pdo = getConnection();


// ========================================
// CHECK ORDER ID
// ========================================

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header(
        "Location: orders.php?error=" .
        urlencode("Invalid order.")
    );

    exit;
}


$orderId = (int) $_GET['id'];

$error = '';


// ========================================
// LOAD CURRENT ORDER
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT
        orders.*,
        users.username

     FROM orders

     INNER JOIN users
        ON users.id = orders.user_id

     WHERE orders.id = :id"
);

$orderStmt->bindValue(
    ':id',
    $orderId,
    PDO::PARAM_INT
);

$orderStmt->execute();

$order =
    $orderStmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$order) {

    header(
        "Location: orders.php?error=" .
        urlencode("Order not found.")
    );

    exit;
}


// ========================================
// UPDATE ORDER STATUS
// ========================================

if (isset($_POST['update_status'])) {

    $newStatus =
        trim($_POST['status'] ?? '');


    $allowedStatuses = [
        'Pending',
        'Processing',
        'Shipped',
        'Delivered',
        'Cancelled'
    ];


    if (!in_array($newStatus, $allowedStatuses, true)) {

        $error =
            'Invalid order status.';

    } else {

        try {

            // ========================================
            // START TRANSACTION
            // ========================================

            $pdo->beginTransaction();


            // ========================================
            // LOCK ORDER
            // ========================================

            $lockOrderStmt = $pdo->prepare(
                "SELECT status
                 FROM orders
                 WHERE id = :id
                 FOR UPDATE"
            );

            $lockOrderStmt->bindValue(
                ':id',
                $orderId,
                PDO::PARAM_INT
            );

            $lockOrderStmt->execute();

            $lockedOrder =
                $lockOrderStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$lockedOrder) {

                throw new Exception(
                    'Order not found.'
                );
            }


            $currentStatus =
                $lockedOrder['status'];


            // ========================================
            // PREVENT REOPENING CANCELLED ORDER
            // ========================================

            if (
                $currentStatus === 'Cancelled' &&
                $newStatus !== 'Cancelled'
            ) {

                throw new Exception(
                    'Cancelled orders cannot be reopened because the stock has already been restored.'
                );
            }


            // ========================================
            // RESTORE STOCK IF NEWLY CANCELLED
            // ========================================

            if (
                $newStatus === 'Cancelled' &&
                $currentStatus !== 'Cancelled'
            ) {

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
                    $orderId,
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

                         SET stock =
                             stock + :quantity

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
            }


            // ========================================
            // UPDATE ORDER STATUS
            // ========================================

            $updateStmt = $pdo->prepare(
                "UPDATE orders

                 SET status = :status

                 WHERE id = :id"
            );

            $updateStmt->bindValue(
                ':status',
                $newStatus
            );

            $updateStmt->bindValue(
                ':id',
                $orderId,
                PDO::PARAM_INT
            );

            $updateStmt->execute();


            // ========================================
            // COMMIT
            // ========================================

            $pdo->commit();


            // ========================================
            // SUCCESS MESSAGE
            // ========================================

            if (
                $newStatus === 'Cancelled' &&
                $currentStatus !== 'Cancelled'
            ) {

                $successMessage =
                    'Order cancelled successfully. Product stock has been restored.';

            } else {

                $successMessage =
                    'Order status updated successfully.';
            }


            header(
                "Location: order_details.php?id=" .
                $orderId .
                "&success=" .
                urlencode($successMessage)
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
// RELOAD ORDER AFTER STATUS PROCESSING
// ========================================

$orderStmt = $pdo->prepare(
    "SELECT
        orders.*,
        users.username

     FROM orders

     INNER JOIN users
        ON users.id = orders.user_id

     WHERE orders.id = :id"
);

$orderStmt->bindValue(
    ':id',
    $orderId,
    PDO::PARAM_INT
);

$orderStmt->execute();

$order =
    $orderStmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$order) {

    header(
        "Location: orders.php?error=" .
        urlencode("Order not found.")
    );

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

        p.name,
        p.image,
        p.brand,
        p.category,
        p.shoe_type

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

$orderItems =
    $itemStmt->fetchAll(
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
        NexStep Admin | Order Details
    </title>

    <link
        rel="stylesheet"
        href="/webapp/admin/admin.css?v=7"
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

            <a
                href="orders.php"
                class="active"
            >
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

        <header class="topbar">

            <div>

                <p class="page-label">
                    ORDER MANAGEMENT
                </p>

                <h1>
                    Order #<?= $orderId ?>
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


        <div class="order-detail-back-row">

            <a
                href="orders.php"
                class="order-detail-back-link"
            >
                Back to Orders
            </a>

        </div>


        <?php if (isset($_GET['success'])): ?>

            <div class="admin-message success-message">

                <?= htmlspecialchars(
                    $_GET['success']
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="admin-message error-message">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- ========================================
             ORDER INFORMATION
        ======================================== -->

        <section class="panel admin-page-panel order-detail-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        ORDER INFORMATION
                    </p>

                    <h2>
                        Customer Details
                    </h2>

                </div>

                <span class="order-id-chip">
                    ORDER #<?= $orderId ?>
                </span>

            </div>


            <div class="order-customer-grid">

                <div class="order-info-item">

                    <span>
                        CUSTOMER
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $order['full_name']
                        ) ?>
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        USERNAME
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $order['username']
                        ) ?>
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        EMAIL
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $order['email']
                        ) ?>
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        PHONE
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $order['phone']
                        ) ?>
                    </strong>

                </div>


                <div class="order-info-item order-info-wide">

                    <span>
                        SHIPPING ADDRESS
                    </span>

                    <strong>
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
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        PAYMENT
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $order['payment_method']
                        ) ?>
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        ORDER DATE
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            date(
                                'F j, Y g:i A',
                                strtotime(
                                    $order['created_at']
                                )
                            )
                        ) ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- ========================================
             STATUS
        ======================================== -->

        <section class="panel admin-page-panel order-detail-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        ORDER STATUS
                    </p>

                    <h2>
                        Update Status
                    </h2>

                </div>

            </div>


            <div class="order-status-body">

                <?php if ($order['status'] === 'Cancelled'): ?>

                    <div class="cancelled-order-notice">

                        <div>

                            <span class="report-status cancelled">
                                CANCELLED
                            </span>

                            <h3>
                                Order has been cancelled
                            </h3>

                            <p>
                                Product stock has already been restored
                                to inventory and this order cannot be reopened.
                            </p>

                        </div>

                    </div>


                <?php else: ?>

                    <form
                        method="POST"
                        action="order_details.php?id=<?= $orderId ?>"
                        class="order-status-form"
                    >

                        <div class="order-status-control">

                            <label for="status">
                                CURRENT STATUS
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >

                                <?php

                                $statuses = [
                                    'Pending',
                                    'Processing',
                                    'Shipped',
                                    'Delivered',
                                    'Cancelled'
                                ];

                                ?>

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= htmlspecialchars($status) ?>"
                                        <?= $order['status'] === $status
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= htmlspecialchars($status) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <button
                            type="submit"
                            name="update_status"
                            class="save-product-button"
                        >
                            UPDATE STATUS
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>


        <!-- ========================================
             ORDER ITEMS
        ======================================== -->

        <section class="panel admin-page-panel order-detail-panel">

            <div class="panel-header">

                <div>

                    <p class="panel-label">
                        ORDER ITEMS
                    </p>

                    <h2>
                        Purchased Products
                    </h2>

                </div>

                <span class="order-item-count">
                    <?= count($orderItems) ?>
                    ITEM<?= count($orderItems) === 1 ? '' : 'S' ?>
                </span>

            </div>


            <div class="order-items-list">

                <?php foreach ($orderItems as $item): ?>

                    <div class="order-admin-item enhanced-order-item">

                        <div class="order-item-image">

                            <img
                                src="../../uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                alt="<?= htmlspecialchars($item['name']) ?>"
                            >

                        </div>


                        <div class="order-item-main">

                            <div class="order-item-heading">

                                <div>

                                    <p class="order-item-brand">
                                        <?= htmlspecialchars(
                                            $item['brand']
                                        ) ?>
                                    </p>

                                    <h3>
                                        <?= htmlspecialchars(
                                            $item['name']
                                        ) ?>
                                    </h3>

                                    <p class="order-item-category">
                                        <?= htmlspecialchars(
                                            $item['category']
                                        ) ?>
                                        /
                                        <?= htmlspecialchars(
                                            $item['shoe_type']
                                        ) ?>
                                    </p>

                                </div>


                                <div class="order-item-price">

                                    ₱<?= number_format(
                                        (float) $item['price'],
                                        2
                                    ) ?>

                                </div>

                            </div>


                            <div class="order-item-meta">

                                <div>

                                    <span>
                                        SIZE
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $item['size']
                                        ) ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        QUANTITY
                                    </span>

                                    <strong>
                                        <?= (int) $item['quantity'] ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        ITEM TOTAL
                                    </span>

                                    <strong>
                                        ₱<?= number_format(
                                            (float) $item['price'] *
                                            (int) $item['quantity'],
                                            2
                                        ) ?>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- ========================================
             TOTAL
        ======================================== -->

        <section class="panel admin-page-panel order-total-panel">

            <div>

                <p class="panel-label">
                    ORDER SUMMARY
                </p>

                <h2>
                    Order Total
                </h2>

            </div>

            <strong class="order-grand-total">
                ₱<?= number_format(
                    (float) $order['total_amount'],
                    2
                ) ?>
            </strong>

        </section>

    </main>

</div>

</body>
</html>
