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


// ========================================
// CHECK PRODUCT ID
// ========================================

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header(
        "Location: products.php?error=" .
        urlencode("Invalid product.")
    );

    exit;
}


$productId = (int) $_GET['id'];


try {

    $pdo = getConnection();


    // ========================================
    // FIND PRODUCT FIRST
    // ========================================

    $stmt = $pdo->prepare(
        "SELECT id, image
         FROM products
         WHERE id = ?"
    );

    $stmt->execute([
        $productId
    ]);

    $product = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$product) {

        header(
            "Location: products.php?error=" .
            urlencode("Product not found.")
        );

        exit;
    }


    // ========================================
    // DELETE PRODUCT FROM DATABASE
    // ========================================

    $deleteStmt = $pdo->prepare(
        "DELETE FROM products
         WHERE id = ?"
    );

    $deleteStmt->execute([
        $productId
    ]);


    // ========================================
    // DELETE PRODUCT IMAGE
    // ========================================

    if (!empty($product['image'])) {

        $imagePath =
            '../../uploads/products/' .
            $product['image'];


        if (file_exists($imagePath)) {

            unlink($imagePath);

        }

    }


    // ========================================
    // RETURN TO PRODUCTS
    // ========================================

    header(
        "Location: products.php?success=" .
        urlencode("Product deleted successfully.")
    );

    exit;


} catch (PDOException $e) {

    header(
        "Location: products.php?error=" .
        urlencode("Failed to delete product.")
    );

    exit;
}
