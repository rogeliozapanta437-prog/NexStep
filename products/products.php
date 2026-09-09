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
// WISHLIST TOGGLE
// ========================================

if (isset($_POST['toggle_wishlist'])) {

    $wishlistProductId =
        (int) ($_POST['product_id'] ?? 0);


    if ($wishlistProductId > 0) {

        // Check if already in wishlist
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
            $wishlistProductId,
            PDO::PARAM_INT
        );

        $checkWishlistStmt->execute();

        $wishlistItem =
            $checkWishlistStmt->fetch(
                PDO::FETCH_ASSOC
            );


        // ========================================
        // REMOVE FROM WISHLIST
        // ========================================

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
                $wishlistProductId,
                PDO::PARAM_INT
            );

            $deleteWishlistStmt->execute();


        // ========================================
        // ADD TO WISHLIST
        // ========================================

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
                $wishlistProductId,
                PDO::PARAM_INT
            );

            $insertWishlistStmt->execute();
        }
    }


    // ========================================
    // RETURN TO CURRENT FILTER / SEARCH
    // ========================================

    $queryParts = [];

    if (!empty($_GET['brand'])) {
        $queryParts['brand'] = $_GET['brand'];
    }

    if (!empty($_GET['category'])) {
        $queryParts['category'] = $_GET['category'];
    }

    if (!empty($_GET['type'])) {
        $queryParts['type'] = $_GET['type'];
    }

    if (!empty($_GET['filter'])) {
        $queryParts['filter'] = $_GET['filter'];
    }

    if (!empty($_GET['search'])) {
        $queryParts['search'] = $_GET['search'];
    }


    $redirectUrl = 'products.php';

    if (!empty($queryParts)) {

        $redirectUrl .=
            '?' .
            http_build_query($queryParts);
    }


    header(
        "Location: " .
        $redirectUrl
    );

    exit;
}


// ========================================
// LOAD CUSTOMER WISHLIST
// ========================================

$wishlistStmt = $pdo->prepare(
    "SELECT product_id
     FROM wishlist
     WHERE user_id = :user_id"
);

$wishlistStmt->bindValue(
    ':user_id',
    $userId,
    PDO::PARAM_INT
);

$wishlistStmt->execute();

$wishlistRows =
    $wishlistStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


$wishlistProductIds = [];

foreach ($wishlistRows as $wishlistRow) {

    $wishlistProductIds[
        (int) $wishlistRow['product_id']
    ] = true;
}


// ========================================
// STORE BRANDS
// ========================================

$allBrands = [
    'Nike',
    'Adidas',
    'Puma',
    'Umbro',
    'New Balance',
    'ASICS',
    'Under Armour'
];


// ========================================
// GET FILTERS
// ========================================

$brand =
    trim($_GET['brand'] ?? '');

$category =
    trim($_GET['category'] ?? '');

$shoeType =
    trim($_GET['type'] ?? '');

$filter =
    trim($_GET['filter'] ?? '');

$search =
    trim($_GET['search'] ?? '');


// ========================================
// BUILD PRODUCT QUERY
// ========================================

$sql = "SELECT * FROM products";

$conditions = [];
$params = [];


// ========================================
// BRAND
// ========================================

if ($brand !== '') {

    $conditions[] =
        "brand = :brand";

    $params[':brand'] =
        $brand;
}


// ========================================
// CATEGORY
// ========================================

if ($category !== '') {

    $conditions[] =
        "category = :category";

    $params[':category'] =
        $category;
}


// ========================================
// SHOE TYPE
// ========================================

if ($shoeType !== '') {

    $conditions[] =
        "shoe_type = :shoe_type";

    $params[':shoe_type'] =
        $shoeType;
}


// ========================================
// NEW ARRIVALS
// ========================================

if ($filter === 'new') {

    $conditions[] =
        "is_new = 1";
}


// ========================================
// SALE
// ========================================

if ($filter === 'sale') {

    $conditions[] =
        "is_sale = 1";
}


// ========================================
// SEARCH
// ========================================

if ($search !== '') {

    $conditions[] = "
        (
            name LIKE :search
            OR brand LIKE :search
            OR category LIKE :search
            OR shoe_type LIKE :search
            OR description LIKE :search
        )
    ";

    $params[':search'] =
        '%' . $search . '%';
}


// ========================================
// ADD WHERE
// ========================================

if (!empty($conditions)) {

    $sql .=
        " WHERE " .
        implode(
            " AND ",
            $conditions
        );
}


// ========================================
// NEWEST FIRST
// ========================================

$sql .= " ORDER BY created_at DESC";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$products =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


// ========================================
// PAGE TITLE
// ========================================

$pageTitle = 'ALL SHOES';

$pageLabel = 'SHOP NEXSTEP';


if ($search !== '') {

    $pageTitle =
        'SEARCH RESULTS';

    $pageLabel =
        'RESULTS FOR "' .
        $search .
        '"';

} elseif ($brand !== '') {

    $pageTitle =
        strtoupper($brand) .
        ' SHOES';

    $pageLabel =
        'SHOP BY BRAND';

} elseif ($category !== '') {

    $pageTitle =
        strtoupper($category) .
        ' SHOES';

    $pageLabel =
        'SHOP BY CATEGORY';

} elseif ($shoeType !== '') {

    $pageTitle =
        strtoupper($shoeType);

    $pageLabel =
        'SHOP BY STYLE';

} elseif ($filter === 'new') {

    $pageTitle =
        'NEW ARRIVALS';

    $pageLabel =
        'JUST LANDED';

} elseif ($filter === 'sale') {

    $pageTitle =
        'SALE';

    $pageLabel =
        'SPECIAL OFFERS';
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
        NexStep |
        <?= htmlspecialchars($pageTitle) ?>
    </title>

    <link
        rel="stylesheet"
        href="/webapp/products/products.css?v=100"
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


<!-- ========================================
     SEARCH
======================================== -->

<section class="product-search">

    <form
        action="products.php"
        method="GET"
    >

        <input
            type="text"
            name="search"
            placeholder="Search shoes, brands, styles..."
            value="<?= htmlspecialchars($search) ?>"
        >

        <button type="submit">
            SEARCH
        </button>

    </form>

</section>


<!-- ========================================
     PAGE HEADING
======================================== -->

<section class="shop-heading">

    <p>
        <?= htmlspecialchars($pageLabel) ?>
    </p>

    <h1>
        <?= htmlspecialchars($pageTitle) ?>
    </h1>

    <span>

        <?= count($products) ?>

        <?= count($products) === 1
            ? 'PRODUCT'
            : 'PRODUCTS'
        ?>

    </span>

</section>


<!-- ========================================
     BRAND FILTER
======================================== -->

<section class="brand-filter">

    <div class="brand-filter-title">

        <p>
            SHOP BY BRAND
        </p>

        <h2>
            ALL BRANDS
        </h2>

    </div>


    <div class="brand-filter-links">

        <a
            href="products.php"
            class="<?= $brand === '' ? 'active' : '' ?>"
        >
            ALL
        </a>


        <?php foreach ($allBrands as $brandName): ?>

            <a
                href="products.php?brand=<?= urlencode($brandName) ?>"
                class="<?= $brand === $brandName ? 'active' : '' ?>"
            >

                <?= htmlspecialchars(
                    strtoupper($brandName)
                ) ?>

            </a>

        <?php endforeach; ?>

    </div>

</section>


<!-- ========================================
     PRODUCTS
======================================== -->

<main class="shop-content">


    <?php if (empty($products)): ?>


        <!-- ========================================
             NO PRODUCTS
        ======================================== -->

        <div class="no-products">

            <h2>
                No products found.
            </h2>

            <p>

                <?php if ($search !== ''): ?>

                    No products matched your search for

                    <strong>
                        "<?= htmlspecialchars($search) ?>"
                    </strong>.

                <?php else: ?>

                    There are currently no shoes
                    available in this section.

                <?php endif; ?>

            </p>


            <a href="products.php">
                VIEW ALL SHOES
            </a>

        </div>


    <?php else: ?>


        <div class="products-grid">


            <?php foreach ($products as $product): ?>


                <article class="product-card">


                    <!-- ========================================
                         PRODUCT IMAGE
                    ======================================== -->

                    <div class="product-image">


                        <!-- PRODUCT TAG -->

                        <?php if ($product['is_sale'] == 1): ?>

                            <span class="product-tag sale">
                                SALE
                            </span>

                        <?php elseif ($product['is_new'] == 1): ?>

                            <span class="product-tag">
                                NEW
                            </span>

                        <?php endif; ?>


                        <!-- ========================================
                             WISHLIST
                        ======================================== -->

                        <?php

                        $isWishlisted =
                            isset(
                                $wishlistProductIds[
                                    (int) $product['id']
                                ]
                            );

                        ?>


                        <form
                            method="POST"
                            class="wishlist-form"
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int) $product['id'] ?>"
                            >

                            <button
                                type="submit"
                                name="toggle_wishlist"
                                class="wishlist-button <?= $isWishlisted ? 'active' : '' ?>"
                                title="<?= $isWishlisted
                                    ? 'Remove from Wishlist'
                                    : 'Add to Wishlist'
                                ?>"
                            >

                                <?= $isWishlisted
                                    ? '♥'
                                    : '♡'
                                ?>

                            </button>

                        </form>


                        <!-- ========================================
                             PRODUCT IMAGE LINK
                        ======================================== -->

                        <a
                            href="product_details.php?id=<?= (int) $product['id'] ?>"
                            class="product-image-link"
                        >

                            <img
                                src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                            >

                        </a>

                    </div>


                    <!-- ========================================
                         PRODUCT INFORMATION
                    ======================================== -->

                    <a
                        href="product_details.php?id=<?= (int) $product['id'] ?>"
                        class="product-info-link"
                    >

                        <div class="product-info">


                            <h2>

                                <?= htmlspecialchars(
                                    $product['name']
                                ) ?>

                            </h2>


                            <p class="product-category">

                                <?= htmlspecialchars(
                                    $product['category']
                                ) ?>

                                ·

                                <?= htmlspecialchars(
                                    $product['shoe_type']
                                ) ?>

                            </p>


                            <p class="product-brand">

                                <?= htmlspecialchars(
                                    $product['brand']
                                ) ?>

                            </p>


                            <strong class="product-price">

                                ₱<?= number_format(
                                    (float) $product['price'],
                                    2
                                ) ?>

                            </strong>


                        </div>

                    </a>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


</body>

</html>