<?php

// NexStep Sign Up Page

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>NexStep | Sign Up</title>

    <link rel="stylesheet" href="signup.css">

</head>

<body>

    <main class="signup-page">

        <div class="background-shape shape-one"></div>
        <div class="background-shape shape-two"></div>

        <section class="signup-card">

            <!-- BRAND SIDE -->

            <div class="brand-panel">

                <div class="logo-container">

                    <img
                        src="../homepage/images/nexstep-logo.png"
                        alt="NexStep Logo"
                        class="logo"
                    >

                </div>

                <div class="brand-message">

                    <p class="eyebrow">
                        JOIN THE MOVEMENT
                    </p>

                    <h1>
                        Create Your
                        <span>NexStep Account.</span>
                    </h1>

                    <p>
                        Sign up to save your favorite shoes,
                        manage your orders, and enjoy a better
                        shopping experience.
                    </p>

                </div>

                <a href="../homepage/index.php"
                   class="store-link">
                    ← Return to Store
                </a>

            </div>


            <!-- FORM SIDE -->

            <div class="form-panel">

                <div class="form-heading">

                    <p class="small-heading">
                        CREATE ACCOUNT
                    </p>

                    <h2>
                        Join NexStep
                    </h2>

                    <p>
                        Enter your details to create an account.
                    </p>

                </div>

                <div class="form-heading">
                        <p class="small-heading">JOIN NEXSTEP</p>
                        <h2>Create Account</h2>
                    <p>Create your account and start shopping with NexStep.</p>
                </div>

<!-- PUT THE ERROR MESSAGE HERE -->
                    <?php if (isset($_GET['error'])): ?>

                            <div class="message error-message">
                            <?php echo htmlspecialchars($_GET['error']); ?>
                            </div>

                      <?php endif; ?>


                <form action="signup_function.php"
                      method="POST">


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Choose a username"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            required
                        >

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            required
                        >

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                        >

                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        name="signup"
                        class="signup-button"
                    >
                        CREATE ACCOUNT
                    </button>


                </form>


                <!-- LOGIN LINK -->

                <div class="login-box">

                    <span>
                        Already have an account?
                    </span>

                    <a href="../login/login.php">
                        Login
                    </a>

                </div>

            </div>

        </section>

    </main>

</body>

</html>