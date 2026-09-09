<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

require_once '../database/config.php';

$pdo = getConnection();

$userId = (int) $_SESSION['user_id'];

$tab = $_GET['tab'] ?? 'my';

if ($tab !== 'my' && $tab !== 'all') {
    $tab = 'my';
}

$message = '';
$error = '';


// ========================================
// SUBMIT REVIEW
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $productId = (int) ($_POST['product_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (
        $orderId <= 0 ||
        $productId <= 0 ||
        $rating < 1 ||
        $rating > 5 ||
        $comment === ''
    ) {

        $error = 'Please complete all review fields.';

    } else {

        // Verify that the logged-in customer really
        // purchased this product and received the order.

        $verifyStmt = $pdo->prepare(
            "SELECT oi.id
             FROM order_items oi
             INNER JOIN orders o
                ON o.id = oi.order_id
             WHERE oi.order_id = :order_id
             AND oi.product_id = :product_id
             AND o.user_id = :user_id
             AND o.status = 'Delivered'
             LIMIT 1"
        );

        $verifyStmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':user_id' => $userId
        ]);

        $verified = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$verified) {

            $error = 'This product is not eligible for review.';

        } else {

            // Check if already reviewed.

            $checkStmt = $pdo->prepare(
                "SELECT id
                 FROM reviews
                 WHERE user_id = :user_id
                 AND product_id = :product_id
                 AND order_id = :order_id
                 LIMIT 1"
            );

            $checkStmt->execute([
                ':user_id' => $userId,
                ':product_id' => $productId,
                ':order_id' => $orderId
            ]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {

                $error = 'You already reviewed this product.';

            } else {

                $insertStmt = $pdo->prepare(
                    "INSERT INTO reviews
                    (
                        user_id,
                        product_id,
                        order_id,
                        rating,
                        comment
                    )
                    VALUES
                    (
                        :user_id,
                        :product_id,
                        :order_id,
                        :rating,
                        :comment
                    )"
                );

                $insertStmt->execute([
                    ':user_id' => $userId,
                    ':product_id' => $productId,
                    ':order_id' => $orderId,
                    ':rating' => $rating,
                    ':comment' => $comment
                ]);

                header("Location: reviews.php?tab=my&success=1");
                exit;
            }
        }
    }
}


if (isset($_GET['success'])) {
    $message = 'Your review was submitted successfully.';
}


// ========================================
// PRODUCTS READY TO REVIEW
// ========================================

$availableStmt = $pdo->prepare(
    "SELECT
        o.id AS order_id,
        oi.product_id,
        oi.size,
        p.name,
        p.brand,
        p.category,
        p.shoe_type,
        p.image
     FROM orders o
     INNER JOIN order_items oi
        ON oi.order_id = o.id
     INNER JOIN products p
        ON p.id = oi.product_id
     WHERE o.user_id = :user_id
     AND o.status = 'Delivered'
     AND NOT EXISTS (
         SELECT 1
         FROM reviews r
         WHERE r.user_id = o.user_id
         AND r.order_id = o.id
         AND r.product_id = oi.product_id
     )
     ORDER BY o.created_at DESC"
);

$availableStmt->execute([
    ':user_id' => $userId
]);

$availableProducts = $availableStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// ========================================
// MY REVIEWS
// ========================================

$myStmt = $pdo->prepare(
    "SELECT
        r.id,
        r.order_id,
        r.rating,
        r.comment,
        r.created_at,
        p.id AS product_id,
        p.name,
        p.brand,
        p.category,
        p.shoe_type,
        p.image
     FROM reviews r
     INNER JOIN products p
        ON p.id = r.product_id
     WHERE r.user_id = :user_id
     ORDER BY r.created_at DESC"
);

$myStmt->execute([
    ':user_id' => $userId
]);

$myReviews = $myStmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// ALL CUSTOMER REVIEWS
// ========================================

$ratingFilter = isset($_GET['rating'])
    ? (int) $_GET['rating']
    : 0;

$categoryFilter = $_GET['category'] ?? '';

$sort = $_GET['sort'] ?? 'recent';

$orderBy = "r.created_at DESC";

if ($sort === 'highest') {
    $orderBy = "r.rating DESC, r.created_at DESC";
}

if ($sort === 'lowest') {
    $orderBy = "r.rating ASC, r.created_at DESC";
}


$sql = "
    SELECT
        r.id,
        r.rating,
        r.comment,
        r.created_at,
        u.username,
        p.id AS product_id,
        p.name,
        p.brand,
        p.category,
        p.shoe_type,
        p.image
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.user_id
    INNER JOIN products p
        ON p.id = r.product_id
    WHERE 1 = 1
";

$params = [];


if ($ratingFilter >= 1 && $ratingFilter <= 5) {

    $sql .= " AND r.rating = :rating";

    $params[':rating'] = $ratingFilter;
}


$allowedCategories = [
    'Men',
    'Women',
    'Kids'
];

if (in_array($categoryFilter, $allowedCategories, true)) {

    $sql .= " AND p.category = :category";

    $params[':category'] = $categoryFilter;
}


$sql .= " ORDER BY " . $orderBy;


$allStmt = $pdo->prepare($sql);
$allStmt->execute($params);

$allReviews = $allStmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// OVERALL REVIEW STATISTICS
// ========================================

$statsStmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_reviews,
        COALESCE(AVG(rating), 0) AS average_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS one_star
     FROM reviews"
);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalReviews = (int) $stats['total_reviews'];
$averageRating = (float) $stats['average_rating'];


// ========================================
// HELPER
// ========================================

function reviewStars(int $rating): string
{
    return str_repeat('★', $rating)
        . str_repeat('☆', 5 - $rating);
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

    <title>Customer Reviews | NexStep</title>

    <link
        rel="stylesheet"
        href="/webapp/account/account.css?v=10"
    >

</head>

<body class="customer-reviews-body">


<!-- ========================================
     HEADER
======================================== -->

<header class="reviews-main-header">

    <a
        href="../homepage/index.php"
        class="reviews-logo"
    >
        <img
            src="/webapp/homepage/images/nexstep-logo.png"
            alt="NexStep"
        >
    </a>


    <nav class="reviews-nav">

        <a href="../products/products.php?category=Men">
            MEN
        </a>

        <a href="../products/products.php?category=Women">
            WOMEN
        </a>

        <a href="../products/products.php?category=Kids">
            KIDS
        </a>

        <a href="../products/products.php">
            BRANDS
        </a>

        <a href="../products/products.php?filter=new">
            NEW ARRIVALS
        </a>

        <a href="../products/products.php?filter=sale">
            SALE
        </a>

    </nav>


    <a
        href="account.php"
        class="reviews-account-button"
    >
        MY ACCOUNT
    </a>

</header>


<!-- ========================================
     HERO
======================================== -->

<section class="reviews-hero">

    <div>

        <p>HOME / REVIEWS</p>

        <h1>Customer Reviews</h1>

        <span>
            Real feedback from real customers.
            Discover what people love about NexStep.
        </span>

    </div>

</section>


<!-- ========================================
     TABS
======================================== -->

<div class="reviews-tabs">

    <a
        href="reviews.php?tab=my"
        class="<?= $tab === 'my' ? 'active' : '' ?>"
    >
        My Reviews
    </a>

    <a
        href="reviews.php?tab=all"
        class="<?= $tab === 'all' ? 'active' : '' ?>"
    >
        All Reviews
    </a>

</div>


<?php if ($message !== ''): ?>

    <div class="reviews-alert success">
        <?= htmlspecialchars($message) ?>
    </div>

<?php endif; ?>


<?php if ($error !== ''): ?>

    <div class="reviews-alert error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<!-- ========================================
     MY REVIEWS
======================================== -->

<?php if ($tab === 'my'): ?>

<main class="my-reviews-container">


    <section class="my-review-section">

        <div class="review-section-heading">

            <p>DELIVERED PRODUCTS</p>

            <h2>Ready to Review</h2>

        </div>


        <?php if (empty($availableProducts)): ?>

            <div class="review-empty-state">

                <h3>No products to review</h3>

                <p>
                    Products from your delivered orders
                    will appear here.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($availableProducts as $product): ?>

                <article class="write-review-card">


                    <div class="write-review-product">

                        <img
                            src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                        >

                        <div>

                            <span>
                                ORDER #<?= (int) $product['order_id'] ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars($product['category']) ?>
                                ·
                                <?= htmlspecialchars($product['shoe_type']) ?>
                            </p>

                            <p>
                                Size:
                                <?= htmlspecialchars($product['size']) ?>
                            </p>

                        </div>

                    </div>


                    <form
                        method="POST"
                        class="write-review-form"
                    >

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?= (int) $product['order_id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= (int) $product['product_id'] ?>"
                        >


                        <label>
                            Your Rating
                        </label>

                        <select
                            name="rating"
                            required
                        >

                            <option value="">
                                Select Rating
                            </option>

                            <option value="5">
                                ★★★★★ Excellent
                            </option>

                            <option value="4">
                                ★★★★☆ Very Good
                            </option>

                            <option value="3">
                                ★★★☆☆ Good
                            </option>

                            <option value="2">
                                ★★☆☆☆ Fair
                            </option>

                            <option value="1">
                                ★☆☆☆☆ Poor
                            </option>

                        </select>


                        <label>
                            Your Review
                        </label>

                        <textarea
                            name="comment"
                            maxlength="1000"
                            placeholder="Tell us what you think about this product..."
                            required
                        ></textarea>


                        <button type="submit">
                            SUBMIT REVIEW
                        </button>

                    </form>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <section class="my-review-section">

        <div class="review-section-heading">

            <p>YOUR FEEDBACK</p>

            <h2>Your Reviews</h2>

        </div>


        <?php if (empty($myReviews)): ?>

            <div class="review-empty-state">

                <h3>No reviews yet</h3>

                <p>
                    Reviews you submit will appear here.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($myReviews as $review): ?>

                <article class="my-submitted-review">

                    <img
                        src="../uploads/products/<?= htmlspecialchars($review['image']) ?>"
                        alt="<?= htmlspecialchars($review['name']) ?>"
                    >

                    <div>

                        <span class="review-small">
                            ORDER #<?= (int) $review['order_id'] ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($review['name']) ?>
                        </h3>

                        <div class="review-yellow-stars">
                            <?= reviewStars((int) $review['rating']) ?>
                        </div>

                        <p>
                            <?= nl2br(
                                htmlspecialchars($review['comment'])
                            ) ?>
                        </p>

                        <span class="review-date">
                            <?= date(
                                'F j, Y',
                                strtotime($review['created_at'])
                            ) ?>
                        </span>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>


<!-- ========================================
     ALL REVIEWS
======================================== -->

<?php else: ?>

<main class="all-reviews-layout">


    <!-- FILTERS -->

    <aside class="review-filter-panel">

        <h2>Filter Reviews</h2>


        <h3>Rating</h3>

        <?php for ($i = 5; $i >= 1; $i--): ?>

            <a
                href="reviews.php?tab=all&rating=<?= $i ?>"
                class="<?= $ratingFilter === $i ? 'selected' : '' ?>"
            >
                <span class="filter-stars">
                    <?= reviewStars($i) ?>
                </span>

                <?= $i ?>
                <?= $i === 1 ? 'star' : 'stars' ?>
            </a>

        <?php endfor; ?>


        <div class="filter-divider"></div>


        <h3>Category</h3>

        <?php foreach ($allowedCategories as $category): ?>

            <a
                href="reviews.php?tab=all&category=<?= urlencode($category) ?>"
                class="<?= $categoryFilter === $category ? 'selected' : '' ?>"
            >
                <?= htmlspecialchars($category) ?>
            </a>

        <?php endforeach; ?>


        <a
            href="reviews.php?tab=all"
            class="clear-review-filters"
        >
            CLEAR FILTERS
        </a>

    </aside>


    <!-- REVIEWS -->

    <section class="all-review-list">


        <div class="all-review-toolbar">

            <strong>
                <?= count($allReviews) ?>
                <?= count($allReviews) === 1 ? 'review' : 'reviews' ?>
                found
            </strong>


            <form method="GET">

                <input
                    type="hidden"
                    name="tab"
                    value="all"
                >

                <?php if ($ratingFilter): ?>

                    <input
                        type="hidden"
                        name="rating"
                        value="<?= $ratingFilter ?>"
                    >

                <?php endif; ?>


                <?php if ($categoryFilter !== ''): ?>

                    <input
                        type="hidden"
                        name="category"
                        value="<?= htmlspecialchars($categoryFilter) ?>"
                    >

                <?php endif; ?>


                <label>
                    Sort by
                </label>

                <select
                    name="sort"
                    onchange="this.form.submit()"
                >

                    <option
                        value="recent"
                        <?= $sort === 'recent' ? 'selected' : '' ?>
                    >
                        Most Recent
                    </option>

                    <option
                        value="highest"
                        <?= $sort === 'highest' ? 'selected' : '' ?>
                    >
                        Highest Rating
                    </option>

                    <option
                        value="lowest"
                        <?= $sort === 'lowest' ? 'selected' : '' ?>
                    >
                        Lowest Rating
                    </option>

                </select>

            </form>

        </div>


        <?php if (empty($allReviews)): ?>

            <div class="review-empty-state">

                <h3>No customer reviews found</h3>

                <p>
                    Customer reviews will appear here
                    after delivered purchases are reviewed.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($allReviews as $review): ?>

                <article class="customer-review-card">


                    <div class="customer-review-product">

                        <img
                            src="../uploads/products/<?= htmlspecialchars($review['image']) ?>"
                            alt="<?= htmlspecialchars($review['name']) ?>"
                        >

                        <strong>
                            <?= htmlspecialchars($review['name']) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars($review['category']) ?>
                            ·
                            <?= htmlspecialchars($review['shoe_type']) ?>
                        </span>

                    </div>


                    <div class="customer-review-content">

                        <div class="review-customer">

                            <div class="review-avatar">

                                <?= strtoupper(
                                    substr(
                                        $review['username'],
                                        0,
                                        1
                                    )
                                ) ?>

                            </div>


                            <div>

                                <strong>
                                    <?= htmlspecialchars($review['username']) ?>
                                </strong>

                                <span class="verified-review">
                                    VERIFIED PURCHASE
                                </span>

                            </div>

                        </div>


                        <div class="review-yellow-stars">
                            <?= reviewStars((int) $review['rating']) ?>
                        </div>


                        <p class="customer-review-comment">
                            <?= nl2br(
                                htmlspecialchars($review['comment'])
                            ) ?>
                        </p>


                        <span class="review-date">

                            <?= date(
                                'F j, Y',
                                strtotime($review['created_at'])
                            ) ?>

                        </span>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <!-- OVERALL RATING -->

    <aside class="review-summary-column">

        <div class="overall-rating-card">

            <h2>Overall Rating</h2>

            <strong class="overall-rating-number">
                <?= number_format($averageRating, 1) ?>
            </strong>

            <div class="overall-stars">
                <?= reviewStars(
                    (int) round($averageRating)
                ) ?>
            </div>

            <p>
                Based on
                <?= $totalReviews ?>
                <?= $totalReviews === 1 ? 'review' : 'reviews' ?>
            </p>


            <?php

            $ratingRows = [
                5 => (int) $stats['five_star'],
                4 => (int) $stats['four_star'],
                3 => (int) $stats['three_star'],
                2 => (int) $stats['two_star'],
                1 => (int) $stats['one_star']
            ];

            ?>


            <div class="rating-breakdown">

                <?php foreach ($ratingRows as $star => $count): ?>

                    <?php

                    $percentage = $totalReviews > 0
                        ? ($count / $totalReviews) * 100
                        : 0;

                    ?>

                    <div class="rating-row">

                        <span>
                            <?= $star ?>
                            <?= $star === 1 ? 'star' : 'stars' ?>
                        </span>

                        <div class="rating-bar">

                            <div
                                style="width: <?= $percentage ?>%;"
                            ></div>

                        </div>

                        <strong>
                            <?= $count ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>


        <div class="review-trust-card">

            <div class="trust-icon">
                ✓
            </div>

            <div>

                <h3>
                    Verified customer reviews
                </h3>

                <p>
                    Reviews can only be submitted
                    after a delivered NexStep purchase.
                </p>

            </div>

        </div>

    </aside>

</main>

<?php endif; ?>


</body>
</html>