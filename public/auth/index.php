<?php

require_once ("../../include/init.php");

use UAParser\Parser;

// Set all vars to null
$errors = $login_error = "";
$email_error = $password_error = "";
$login_success = $success = "";

if (!isset($_GET['redirect_url'])) {
    $errors = "Invalid request: lacking redirect url.";
} else {
    $redirect_url = $_GET['redirect_url'];
    $_SESSION['redirect_url'] = $_GET['redirect_url'];
}

if (!isset($_GET['broker'])) {
    $errors = "Invalid request: lacking broker id.";
} else {
    $broker_id = $_GET['broker'];
    $_SESSION['broker'] = $_GET['broker'];
    // Get site name from broker id via db
    $site_name = getSiteName($_GET['broker']);
}

if (empty($errors)) {
    if (isset($_SESSION["logged_in"])) {
        if ($_SESSION["logged_in"] == true) {
            // Do smth
            $success = "You're already logged in, redirecting you..";
            $redirect_script = "<script>setTimeout(function () {window.location.href = '$redirect_url';},2000);</script>";
        }
    }
    if (isset($_SESSION["2fa_user"])) {
        if ($_SESSION["2fa_user"] == true) {
            // Go to 2fa
            if ($_SESSION["stage1_login"] == true) {
                if (!isset($_SESSION["logged_in"])) {
                    $success = "Additonal authentication required...";
                    $redirect_script = "<script>setTimeout(function () {window.location.href = '2fa.php';},2000);</script>";
                }
            }
        }
    }
}

// User agent parser
$parser = Parser::create();
$user_agent = $parser->parse($_SERVER['HTTP_USER_AGENT'])->toString();
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST)) {
        // Validation
        // cehcks if email is empty
        if (empty(trim($_POST["email"]))) {
            $email_error = "Please enter an email address.";
        } else {
            $email = trim($_POST["email"]);
        }

        // Check if password is empty
        if (empty(trim($_POST["password"]))) {
            $password_error = "Please enter your password.";
        } else {
            $password = trim($_POST["password"]);
        }

        if (empty($email_error) && empty($password_error)) {
            $dbconn = connectDBWithVars();
            $sql = "SELECT id, email, password, 2fa_status FROM users WHERE email = ?";
            if ($stmt = $dbconn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                // Set parameters
                $param_email = $email;
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) { // email exists
                        $stmt->bind_result($id, $email, $hashed_password, $tfa_status); // bind sql result to vars
                        if ($stmt->fetch()) {
                            if (password_verify($password, $hashed_password)) {
                                $_SESSION["stage1_login"] = true; // password is correct
                                $_SESSION["id"] = $id;
                                $_SESSION["email"] = $email;
                                $ip = $_SERVER['REMOTE_ADDR'];
                                $stmt->close();
                                if ($tfa_status == "1") {
                                    $login_success = "Success, additonal authentication required...";
                                    $_SESSION['2fa_user'] = true;
                                    $redirect_script = "<script>setTimeout(function () {window.location.href = '2fa.php';},2000);</script>";
                                } else {
                                    $login_success = "Login success, redirecting...";
                                    $_SESSION['logged_in'] = true;
                                    $redirect_script = "<script>setTimeout(function () {window.location.href = '$redirect_url';},2000);</script>";
                                }
//                                logAccessAttempt($id, $ip, $user_agent, "1st stage login success");
                            } else {
                                $login_error = "Invalid email address or password. $email $password";
                            }
                        } else {
                            $login_error = "Error occured whilst fetching results.";
                        }
                    } else {
                        $login_error = "Invalid email address or password.";
                    }
                } else {
                    $login_error = "Error occured whilst executing results.";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Single Sign On">
    <meta name="author" content="Zach Matcham">
    <!--- Favicons -->
    <link rel="icon" href="../assets/favicons/favicon.ico">

    <?php if (!empty($site_name)): ?><title>Login to <?php echo ($site_name); ?> - zSSO</title><?php endif; ?>
    <?php if (empty($site_name)): ?><title>Login - zSSO</title><?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <link href="../css/login.css" rel="stylesheet">
    <?php if (!empty($redirect_script) && empty($errors)): ?>
        <?php echo ($redirect_script); ?>
    <?php endif; ?>
</head>
<body class="text-center">
<div class="wrapper border rounded px-5" style="background-color: #f5f5f5;">
    <img class="mt-2" src="../assets/logo.png" alt="zSSO Logo" style="width: 35%;">
    <h2>zSSO - Login</h2>
    <?php if (!empty($errors) && empty($success)): ?>
        <div class="alert alert-danger"><?php echo $errors ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success ?></div>
    <?php endif; ?>

    <?php if (empty($errors) && empty($success)): ?>
    <p>Please fill in your credentials to login into <?php echo $site_name; ?>.</p>

    <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger"><?php echo $login_error ?></div>
    <?php endif; ?>
    <?php if (!empty($login_success)): ?>
        <div class="alert alert-success"><?php echo $login_success ?></div>
    <?php endif; ?>


    <form class="form-signin" action="" method="post">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="text" id="email" name="email" class="form-control <?php if (!empty($email_error)) { echo ("is-invalid"); }?>" placeholder="Email" required autofocus>
            <span class="invalid-feedback"><?php echo $email_error ?></span>
        </div>
        <div class="form-group mt-2">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control <?php if (!empty($password_error)) { echo ("is-invalid"); }?>" placeholder="Password" required autofocus>
            <span class="invalid-feedback"><?php echo $password_error ?></span>
        </div>
        <div class="form-group mt-3">
            <input type="submit" class="btn btn-lg btn-primary btn-block mb-2" value="Login">
        </div>
    </form>
    <?php endif; ?>
    <hr>
    <p class="mt-2 text-muted">Server: <?php echo $server_name?></p>
    <p class="mt-1 mb-3 text-muted">&copy; <script>document.write(new Date().getFullYear())</script> Zach Matcham</p>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
</body>
