<?php

// NexStep Login Page

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>NexStep | Login</title>

    <link rel="stylesheet" href="login.css">

</head>

<body>

    <main class="login-page">

        <div class="background-shape shape-one"></div>
        <div class="background-shape shape-two"></div>


        <section class="login-card">


            <!-- BRAND SIDE -->

            <div class="brand-panel">

                <div class="logo-link">

                     <img
                        src="../homepage/images/nexstep-logo.png"
                        alt="NexStep Logo"
                         class="logo"
                      >

                </div>


                <div class="brand-message">

                    <p class="eyebrow">
                        MOVE WITH CONFIDENCE
                    </p>

                    <h1>
                        Every Step
                        <span>Starts Here.</span>
                    </h1>

                    <p>
                        Sign in to continue exploring
                        your favorite shoes, brands,
                        and styles.
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
                        MEMBER ACCESS
                    </p>

                    <h2>
                        Welcome Back
                    </h2>

                    <p>
                        Enter your account details below.
                    </p>

                </div>

                        <?php if (isset($_GET['error'])): ?>

                    <div class="message error-message">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                     </div>

                        <?php endif; ?>

                <form action="login_function.php" method="POST"     autocomplete="off">


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Your username"
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
                            placeholder="Your password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        name="login"
                        class="login-button"
                    >
                        CONTINUE TO NEXSTEP
                    </button>


                </form>


                <div class="signup-box">

                    <span>
                        New to NexStep?
                    </span>

                    <a href="../signup/signup.php">
                        Create Account
                    </a>

                </div>


            </div>

        </section>

    </main>

</body>

</html>