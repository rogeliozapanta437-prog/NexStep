<?php

session_start();


// ========================================
// PREVENT CACHED PROTECTED PAGE
// ========================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


// ========================================
// PROTECT HOMEPAGE
// ========================================

if (!isset($_SESSION['user_id'])) {

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// LOGGED-IN USER
// ========================================

$isLoggedIn = true;

$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';


// Pages not created yet
$protectedLink = '#';

// ========================================
// DATABASE
// ========================================

require_once '../database/config.php';

$pdo = getConnection();


// ========================================
// HOMEPAGE WISHLIST TOGGLE
// ========================================

$userId = (int) $_SESSION['user_id'];

if (isset($_POST['toggle_wishlist'])) {

    $wishlistProductId =
        (int) ($_POST['product_id'] ?? 0);

    if ($wishlistProductId > 0) {

        // Check if product is already wishlisted
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

        // REMOVE FROM WISHLIST
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

        // ADD TO WISHLIST
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

    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
    ) {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'wishlisted' => !$wishlistItem
        ]);

        exit;
    }

    header("Location: index.php");
    exit;
}


// ========================================
// LOAD HOMEPAGE WISHLIST
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
// CART ITEM COUNT
// ========================================

$cartCount = 0;

if (
    isset($_SESSION['user_id']) &&
    $role !== 'admin'
) {

    $cartCountStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity), 0)
         FROM cart
         WHERE user_id = :user_id"
    );

    $cartCountStmt->bindValue(
        ':user_id',
        (int) $_SESSION['user_id'],
        PDO::PARAM_INT
    );

    $cartCountStmt->execute();

    $cartCount =
        (int) $cartCountStmt->fetchColumn();
}



// ========================================
// NEW ARRIVAL PRODUCTS
// ========================================

$stmt = $pdo->prepare(
    "SELECT *
     FROM products
     WHERE is_new = 1
     ORDER BY created_at DESC
     LIMIT 12"
);

$stmt->execute();

$products = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);




// ========================================
// NEXSTEP HOMEPAGE
// ========================================


// Navigation
$navigation = [
    'MEN',
    'WOMEN',
    'KIDS',
    'BRANDS',
    'NEW ARRIVALS',
    'SALE'
];


// Benefits
$benefits = [
    [
        'image' => 'authentic.png',
        'alt' => 'Authentic',
        'title' => '100% AUTHENTIC',
        'description' => 'Original & genuine products'
    ],
    [
        'image' => 'shipping.png',
        'alt' => 'Free Shipping',
        'title' => 'FREE SHIPPING',
        'description' => 'On selected orders'
    ],
    [
        'image' => 'returns.png',
        'alt' => 'Easy Returns',
        'title' => 'EASY RETURNS',
        'description' => 'Simple & hassle-free'
    ],
    [
        'image' => 'payment.png',
        'alt' => 'Secure Payment',
        'title' => 'SECURE PAYMENT',
        'description' => 'Safe & protected checkout'
    ]
];


// Categories
$categories = [
    [
        'class' => 'men',
        'title' => 'MEN',
        'link' => 'Shop now',
        'image' => 'new18.jpg',
        'alt' => 'Men shoes'
    ],
    [
        'class' => 'kids',
        'title' => 'KIDS',
        'link' => 'Shop now',
        'image' => 'new19.jpg',
        'alt' => 'Kids shoes'
    ],
    [
        'class' => 'women',
        'title' => 'WOMEN',
        'link' => 'Shop now',
        'image' => 'new17.jpg',
        'alt' => 'Women shoes'
    ]
];


// Shoe types
$shoeTypes = [
    ['image' => 'sneakers.png', 'name' => 'SNEAKERS'],
    ['image' => 'casual.png', 'name' => 'CASUAL'],
    ['image' => 'sports.png', 'name' => 'SPORTS'],
    ['image' => 'sandals.png', 'name' => 'SANDALS'],
    ['image' => 'formal.png', 'name' => 'FORMAL'],
    ['image' => 'boots.png', 'name' => 'BOOTS'],
    ['image' => 'kids.png', 'name' => 'KIDS']
];


// Brands
$brands = [
    ['image' => 'adidas.png', 'name' => 'Adidas'],
    ['image' => 'puma.png', 'name' => 'Puma'],
    ['image' => 'nike.png', 'name' => 'Nike'],
    ['image' => 'umbro.png', 'name' => 'Umbro']
];



// Features
$features = [
    [
        'image' => 'trending.png',
        'title' => 'TRENDING STYLES',
        'description' => 'The looks everyone is talking about.'
    ],
    [
        'image' => 'quality.png',
        'title' => 'PREMIUM QUALITY',
        'description' => 'Built to last. Made to impress.'
    ],
    [
        'image' => 'everyone.png',
        'title' => 'FOR EVERYONE',
        'description' => 'Men, women, and kids.'
    ],
    [
        'image' => 'prices.png',
        'title' => 'BETTER PRICES',
        'description' => 'Great brands. Fair value.'
    ]
];


// Footer
$shopLinks = [
    'Men' => '../products/products.php?category=Men',
    'Women' => '../products/products.php?category=Women',
    'Kids' => '../products/products.php?category=Kids',
    'New Arrivals' => '../products/products.php?filter=new',
    'Sale' => '../products/products.php?filter=sale'
];

$customerLinks = [
    'Help Center' => 'help.php',
    'Shipping & Delivery' => 'shipping.php',
    'Returns & Refunds' => 'returns.php',
    'Size Guide' => 'size_guide.php',
    'Track Order' => '../orders/orders.php'
];

$aboutLinks = [
    'About NexStep' => 'about.php',
    'Our Shops' => 'shops.php',
    'Careers' => 'careers.php',
    'Contact Us' => 'contact.php'
];

$policyLinks = [
    'Terms & Conditions' => 'terms.php',
    'Privacy Policy' => 'privacy.php',
    'Return Policy' => 'return_policy.php'
];


?>

        <!DOCTYPE html>
        <html lang="en">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>NexStep</title>

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
                href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@700&display=swap"
                rel="stylesheet"
            >

            <link
                rel="stylesheet"
                href="style.css?v=100"
            >

        </head>

        <body>


<!-- ========================================
     HEADER
======================================== -->

            <header class="header">

                <div class="logo">

                    <a href="index.php">

                        <img
                            src="images/nexstep-logo.png"
                            alt="NexStep"
                        >

                    </a>

            </div>


            <nav class="navigation">

                <?php foreach ($navigation as $item): ?>

                    <?php

                    $navLink = '#';

                    if ($item === 'MEN') {
                        $navLink = '../products/products.php?category=Men';
                    }

                    elseif ($item === 'WOMEN') {
                        $navLink = '../products/products.php?category=Women';
                    }

                    elseif ($item === 'KIDS') {
                        $navLink = '../products/products.php?category=Kids';
                    }

                    elseif ($item === 'BRANDS') {
                        $navLink = '#brands';
                    }

                    elseif ($item === 'NEW ARRIVALS') {
                        $navLink = '../products/products.php?filter=new';
                    }

                    elseif ($item === 'SALE') {
                        $navLink = '../products/products.php?filter=sale';
                    }

                    ?>

                    <a href="<?= htmlspecialchars($navLink) ?>">

                        <?= htmlspecialchars($item) ?>

                    </a>

                <?php endforeach; ?>

            </nav>


    <div class="header-icons">


            <!-- SEARCH -->

            <form
                class="search-box"
                action="../products/products.php"
                method="GET"
            >
                <img
                    src="images/search.png"
                    alt="Search"
                >

                <input
                    type="text"
                    name="search"
                    placeholder="SEARCH"
                    aria-label="Search products"
                    required
                >
            </form>

        <!-- ========================================
             ACCOUNT
        ======================================== -->

        <div class="account-wrapper">


            <button
                type="button"
                class="account-button"
                id="accountButton"
                title="Account"
            >

                <img
                    src="images/person.png"
                    alt="Account"
                >

            </button>



            <!-- ========================================
                 ACCOUNT DROPDOWN
            ======================================== -->

            <div
                class="account-dropdown"
                id="accountDropdown"
            >


                <!-- USER -->

                <div class="account-dropdown-header">


                    <div class="account-avatar">

                        <img
                            src="images/person.png"
                            alt="Account"
                        >

                    </div>


                    <div>

                        <small>

                            <?= $role === 'admin'
                                ? 'ADMIN ACCOUNT'
                                : 'WELCOME'
                            ?>

                        </small>


                        <strong>

                            Hi, <?= htmlspecialchars($username) ?>

                        </strong>

                    </div>


                </div>



                <?php if ($role === 'admin'): ?>


                    <!-- ADMIN -->

                    <a
                        href="../admin/dashboard.php"
                        class="account-menu-item"
                    >

                        <span class="menu-icon">
                            ▣
                        </span>

                        <span>
                            Admin Dashboard
                        </span>

                    </a>


                    <a
                        href="index.php"
                        class="account-menu-item"
                    >

                        <span class="menu-icon">
                            ◈
                        </span>

                        <span>
                            View Store
                        </span>

                    </a>


                                    <?php else: ?>


                        <!-- ========================================
                            CUSTOMER MENU
                        ======================================== -->


                        <!-- MY ACCOUNT -->

                        <a
                            href="../account/account.php"
                            class="account-menu-item"
                        >

                            <span class="menu-icon">

                                <img
                                    src="images/person.png"
                                    alt="My Account"
                                >

                            </span>

                            <span>
                                My Account
                            </span>

                        </a>



                        <!-- WISHLIST -->

                        <a
                            href="../wishlist/wishlist.php"
                            class="account-menu-item"
                        >

                            <span class="menu-icon">

                                <img
                                    src="images/heart.png"
                                    alt="Wishlist"
                                >

                            </span>

                            <span>
                                Wishlist
                            </span>

                        </a>



                        <!-- ORDERS -->

                        <a
                            href="../orders/orders.php"
                            class="account-menu-item"
                        >

                            <span class="menu-icon">

                                <img
                                    src="images/cart.png"
                                    alt="Orders"
                                >

                            </span>

                            <span>
                                Orders
                            </span>

                        </a>



                        <!-- REVIEWS -->

                        <a
                            href="../account/reviews.php?tab=my"
                            class="account-menu-item"
                        >

                            <span class="menu-icon">

                                <img
                                    src="images/reviews.png"
                                    alt="Reviews"
                                >

                            </span>

                            <span>
                                Reviews
                            </span>

                        </a>



                        <!-- HELP / FAQ -->

                        <a
                            href="help.php"
                            class="account-menu-item"
                        >

                            <span class="menu-icon">

                                <img
                                    src="images/help.png"
                                    alt="Help"
                                >

                            </span>

                            <span>
                                Help / FAQ
                            </span>

                        </a>


                    <?php endif; ?>



                <div class="account-menu-divider"></div>


                <!-- LOGOUT -->

                <a
                    href="../logout/logout.php"
                    class="account-menu-item logout-menu-item"
                >

                    <span class="menu-icon">
                        ↪
                    </span>

                    <span>
                        Logout
                    </span>

                </a>


            </div>


        </div>



        <!-- WISHLIST -->

        <a
            href="../wishlist/wishlist.php"
            class="header-icon"
            title="Wishlist"
        >

            <img
                src="images/heart.png"
                alt="Wishlist"
            >

        </a>



            <!-- CART -->

            <a
                href="../cart/cart.php"
                class="header-icon cart-header-icon"
                title="Cart"
            >

                <img
                    src="images/cart.png"
                    alt="Cart"
                >

                <?php if ($cartCount > 0): ?>

                    <span class="cart-count">

                        <?= $cartCount ?>

                    </span>

                <?php endif; ?>

            </a>


    </div>

</header>



<!-- ========================================
     HERO
======================================== -->

<section class="hero">

    <div class="hero-content">


        <p class="eyebrow">

            STEP INTO STYLE

        </p>


        <h1>

            ANY BRAND.<br>

            ANY STYLE.<br>

            <span>
                YOUR NEXT STEP.
            </span>

        </h1>


        <p class="hero-description">

            Discover and shop any brand of shoes for
            men, women, and kids. Sleek, casual,
            formal, sport-fit — the perfect pair
            for every step you take.

        </p>


        <div class="hero-buttons">

            <a href="../products/products.php">

                SHOP NOW

            </a>


            <a
                href="#shoe-types"
                class="outline-button"
            >

                EXPLORE MORE

            </a>

        </div>

    </div>


    <div class="hero-photo">

        <img
            src="images/nike.jpg"
            alt="Featured shoes"
        >

    </div>

</section>



<!-- ========================================
     BENEFITS
======================================== -->

<section class="benefits">

    <?php foreach ($benefits as $benefit): ?>

        <div class="benefit">


            <div class="benefit-icon">

                <img
                    src="images/<?= htmlspecialchars($benefit['image']) ?>"
                    alt="<?= htmlspecialchars($benefit['alt']) ?>"
                >

            </div>


            <div>

                <strong>

                    <?= htmlspecialchars($benefit['title']) ?>

                </strong>


                <small>

                    <?= htmlspecialchars($benefit['description']) ?>

                </small>

            </div>


        </div>

    <?php endforeach; ?>

</section>



<!-- ========================================
     MAIN
======================================== -->

<main>



<!-- ========================================
     CATEGORIES
======================================== -->

<section class="categories">

<?php foreach ($categories as $category): ?>

        <a
            href="../products/products.php?category=<?= urlencode($category['title']) ?>"
            class="category <?= htmlspecialchars($category['class']) ?>"
        >

            <div class="category-title">

                <h2>

                    <?= htmlspecialchars($category['title']) ?>

                </h2>

                <p>

                    <?= htmlspecialchars($category['link']) ?>

                </p>

            </div>

            <img
            
                src="images/<?= htmlspecialchars($category['image']) ?>"
                alt="<?= htmlspecialchars($category['alt']) ?>"
            >

        </a>

    <?php endforeach; ?>

</section>



<!-- ========================================
     SHOE TYPES
======================================== -->

<section class="shoe-types" id="shoe-types">

        <?php foreach ($shoeTypes as $shoe): ?>

            <?php
            if ($shoe['name'] === 'Kids') {
                $shoeLink = '../products/products.php?category=Kids';
            } else {
                $shoeLink = '../products/products.php?type=' . urlencode($shoe['name']);
            }
            ?>

            <a
                href="<?= htmlspecialchars($shoeLink) ?>"
                class="shoe-type"
            >

                <img
                    src="images/<?= htmlspecialchars($shoe['image']) ?>"
                    alt="<?= htmlspecialchars($shoe['name']) ?>"
                >

                <span>
                    <?= htmlspecialchars($shoe['name']) ?>
                </span>

            </a>

        <?php endforeach; ?>

</section>



<!-- ========================================
     BRANDS
======================================== -->

<section class="brands" id="brands">

    <div class="section-heading">


        <div class="heading-left">


            <p class="eyebrow">

                TOP BRANDS

            </p>


            <h2 class="shadow-heading">

                SHOP ANY BRAND

            </h2>


            <p class="description">

                We bring you the best from the world's
                most trusted brands.

            </p>


        </div>



        <div class="brand-list">

                <?php foreach ($brands as $brand): ?>

                    <a
                        href="../products/products.php?brand=<?= urlencode($brand['name']) ?>"
                        class="brand-box"
                    >

                        <img
                            src="images/<?= htmlspecialchars($brand['image']) ?>"
                            alt="<?= htmlspecialchars($brand['name']) ?>"
                        >

                    </a>

                <?php endforeach; ?>


                <a href="../products/products.php" class="brand-box view-all">

                    <span>
                        VIEW ALL
                    </span>

                    <strong>
                        →
                    </strong>

                </a>


        </div>


    </div>

</section>



<!-- ========================================
     NEW ARRIVALS
======================================== -->

<section class="products-section" id="just-landed">


    <div class="section-title">


        <div>

            <p class="eyebrow">

                NEW ARRIVAL

            </p>


            <h2 class="shadow-heading">

                JUST LANDED

            </h2>

        </div>


        <a href="../products/products.php?filter=new">
            
            VIEW ALL →

        </a>


    </div>



        <div class="products">

            <?php if (empty($products)): ?>

                <p>
                    No new arrivals available yet.
                </p>

            <?php else: ?>

                <?php foreach ($products as $product): ?>

                    <div class="product">

                        <div class="product-image">

                            <?php if ($product['is_sale'] == 1): ?>

                                <span class="tag sale">
                                    SALE
                                </span>

                            <?php else: ?>

                                <span class="tag">
                                    NEW
                                </span>

                            <?php endif; ?>


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
                                    class="homepage-wishlist-form"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?= (int) $product['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="toggle_wishlist"
                                        class="heart <?= $isWishlisted ? 'active' : '' ?>"
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


                                    <a
                                        href="../products/product_details.php?id=<?= (int) $product['id'] ?>"
                                    >
                                        <img
                                            src="../uploads/products/<?php
                                                echo htmlspecialchars(
                                                    $product['image']
                                                );
                                            ?>"
                                            alt="<?php
                                                echo htmlspecialchars(
                                                    $product['name']
                                                );
                                            ?>"
                                        >
                                    </a>

                        </div>


                        <h3>
                            <a
                                href="../products/product_details.php?id=<?= (int) $product['id'] ?>"
                            >
                                <?php
                                    echo htmlspecialchars(
                                        $product['name']
                                    );
                                ?>
                            </a>
                        </h3>


                        <p>
                            <?php
                            echo htmlspecialchars(
                                $product['category']
                            );
                            ?>

                            ·

                            <?php
                            echo htmlspecialchars(
                                $product['shoe_type']
                            );
                            ?>
                        </p>


                        <strong>
                            ₱<?php
                            echo number_format(
                                (float) $product['price'],
                                2
                            );
                            ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

</section>



<!-- ========================================
     PROMOTIONS
======================================== -->

<section class="promotions">


    <div class="promotion">


        <img
            src="images/new12.jpg"
            alt="Sneakers"
        >


        <div class="promotion-content">


            <h2>

                SNEAKERS THAT<br>
                MATCH YOUR VIBE

            </h2>


            <p>

                From classics to new drops,
                find the sneakers everyone's
                talking about.

            </p>


            <a href="../products/products.php?type=Sneakers">

                SHOP SNEAKERS 

            </a>


        </div>


    </div>



    <div class="promotion">


        <img
            src="images/new13.jpg"
            alt="Comfort shoes"
        >


        <div class="promotion-content">


            <h2>

                COMFORT FOR<br>
                EVERY STEP

            </h2>


            <p>

                Designed for your lifestyle.
                Built for all-day comfort.

            </p>


            <a href="../products/products.php">

                SHOP NOW

            </a>


        </div>


    </div>


</section>



<!-- ========================================
     FEATURES
======================================== -->

<section class="features-section">


    <div class="section-title">


        <div>

            <p class="eyebrow">

                BEST SELLERS

            </p>


            <h2 class="shadow-heading">

                OUR CUSTOMERS LOVE

            </h2>

        </div>


            <a href="../account/reviews.php?tab=all">
                
                VIEW ALL

            </a>


    </div>



    <div class="features">

        <?php foreach ($features as $feature): ?>

            <div class="feature">


                <span>

                    <img
                        src="images/<?= htmlspecialchars($feature['image']) ?>"
                        alt="<?= htmlspecialchars($feature['title']) ?>"
                    >

                </span>


                <div>


                    <strong>

                        <?= htmlspecialchars($feature['title']) ?>

                    </strong>


                    <p>

                        <?= htmlspecialchars($feature['description']) ?>

                    </p>


                </div>


            </div>

        <?php endforeach; ?>


    </div>


</section>


</main>



<!-- ========================================
     FOOTER
======================================== -->

<footer>



<!-- NEWSLETTER -->

<section class="newsletter">


    <h2>

        BE THE FIRST

        <span>
            TO KNOW
        </span>

    </h2>


    <p>

        Get exclusive deals, new arrivals,
        and special offers delivered to your inbox.

    </p>


    <form
        class="subscribe"
        action="#"
        method="POST"
    >


        <input
            type="email"
            name="email"
            placeholder="Enter your email"
            required
        >


        <button type="submit">

            SUBSCRIBE

        </button>


    </form>


</section>



<!-- FOOTER CONTENT -->

<div class="footer-content">


    <!-- ABOUT -->

    <div class="footer-about">


        <p>

            We offer any brand of shoes for men,
            women, and kids. Step into style.
            Step into NexStep.

        </p>


        <div class="social">


            <a href="https://www.facebook.com/zapanta.cutie" target="_blank" rel="noopener noreferrer" >

                <img
                    src="images/facebook.png"
                    alt="Facebook"
                >

            </a>


            <a href="https://www.instagram.com/zapanta.jr/  " target="_blank" rel="noopener noreferrer" >

                <img
                    src="images/instagram.png"
                    alt="Instagram"
                >

            </a>


            <a href="https://www.tiktok.com/@logii143"  target="_blank" rel="noopener noreferrer">

                <img
                    src="images/tiktok.png"
                    alt="TikTok"
                >

            </a>


        </div>


    </div>



    <!-- SHOP -->

    <div>


        <h3>

            SHOP

        </h3>


        <?php foreach ($shopLinks as $label => $url): ?>

            <a href="<?= htmlspecialchars($url) ?>">

                <?= htmlspecialchars($label) ?>

            </a>

        <?php endforeach; ?>


    </div>



    <!-- CUSTOMER CARE -->

    <div>


        <h3>

            CUSTOMER CARE

        </h3>


<?php foreach ($customerLinks as $label => $url): ?>

    <a href="<?= htmlspecialchars($url) ?>">

        <?= htmlspecialchars($label) ?>

    </a>

<?php endforeach; ?>


    </div>



    <!-- ABOUT US -->

    <div>


        <h3>

            ABOUT US

        </h3>


            <?php foreach ($aboutLinks as $label => $url): ?>

                <a href="<?= htmlspecialchars($url) ?>">

                    <?= htmlspecialchars($label) ?>

                </a>

            <?php endforeach; ?>

    </div>



    <!-- POLICIES -->

    <div>


        <h3>

            POLICIES

        </h3>


                <?php foreach ($policyLinks as $label => $url): ?>

                    <a href="<?= htmlspecialchars($url) ?>">

                        <?= htmlspecialchars($label) ?>

                    </a>

                <?php endforeach; ?>


    </div>


</div>



<!-- FOOTER BOTTOM -->

<div class="footer-bottom">


    <p>

        © <?= date('Y') ?> NexStep.
        All rights reserved.

    </p>


</div>


</footer>



<!-- ========================================
     ACCOUNT DROPDOWN JAVASCRIPT
======================================== -->

<script>

const accountButton =
    document.getElementById('accountButton');

const accountDropdown =
    document.getElementById('accountDropdown');


accountButton.addEventListener(
    'click',
    function(event) {

        event.stopPropagation();

        accountDropdown.classList.toggle('show');

    }
);


accountDropdown.addEventListener(
    'click',
    function(event) {

        event.stopPropagation();

    }
);


document.addEventListener(
    'click',
    function() {

        accountDropdown.classList.remove('show');

    }
);

</script>


<!-- ========================================
     HOMEPAGE WISHLIST JAVASCRIPT
======================================== -->

<script>

const homepageWishlistForms =
    document.querySelectorAll(
        '.homepage-wishlist-form'
    );

homepageWishlistForms.forEach(function(form) {

    form.addEventListener(
        'submit',
        async function(event) {

            event.preventDefault();

            const button =
                form.querySelector('.heart');

            const formData =
                new FormData(form);

            formData.append(
                'toggle_wishlist',
                '1'
            );

            button.disabled = true;

            try {

                const response = await fetch(
                    window.location.pathname,
                    {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        'Wishlist update failed.'
                    );
                }

                const result =
                    await response.json();

                if (!result.success) {
                    throw new Error(
                        'Wishlist update failed.'
                    );
                }

                if (result.wishlisted) {

                    button.classList.add(
                        'active'
                    );

                    button.textContent = '♥';

                    button.title =
                        'Remove from Wishlist';

                } else {

                    button.classList.remove(
                        'active'
                    );

                    button.textContent = '♡';

                    button.title =
                        'Add to Wishlist';
                }

            } catch (error) {

                console.error(error);

            } finally {

                button.disabled = false;
            }
        }
    );
});

</script>


</body>

</html>