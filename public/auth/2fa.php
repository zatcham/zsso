<?php
require_once ("../../include/init.php");

use UAParser\Parser;

if (!empty($_SESSION['redirect_url'])) {
    $redirect_url = $_SESSION['redirect_url'];
} else {
    $errors = "Invalid session, no redirect url found.";
}

if (!empty($_SESSION['broker'])) {
    $broker_id = $_SESSION['broker'];
    $site_name = getSiteName($_SESSION['broker']);
} else {
    $errors = "Invalid session, no broker found.";
}

// Assign all vars from session
if (!empty($_SESSION['email'])) {
    $email = $_SESSION['email'];
} else {
    $errors = "Invalid session, missing user details.";
}

if (!empty($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
} else {
    $errors = "Invalid session, missing user details.";
}

// User agent parser
$parser = Parser::create();
$user_agent = $parser->parse($_SERVER['HTTP_USER_AGENT'])->toString();
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // generate token and add to db
    $generated_token = mt_rand(111111, 999999);
    $sql = "INSERT INTO tfa_tokens (user_id, token) VALUES ($user_id, $generated_token);";
    $dbconn = connectDBWithVars();
    if ($dbconn->query($sql) === TRUE) {
        // Inserted ok, now email
        $dbconn->close();
        if (Email::send2FA($email, $ip, $user_agent, $generated_token, $site_name)) {
//            $success = "Successfully sent 2FA";
            // do nowt if success
        } else {
            $errors = "An error occured whilst sending 2FA email";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST)) {
        if (empty($errors)) {
            $digits = $_POST['2fa-verify'];
            if (strlen($digits) != 6) {
                $errors = "Error: Not enough digits entered for 2fa input";
            }
            $x = verify2FA($user_id, $digits);
            if ($x == "1") {
                $success = "2FA Verified, redirecting you..";
//                Auth::logAccessAttempt($userid, $ip, "2FA login successful", $user_agent);
                $_SESSION['logged_in'] = True;
                $_SESSION['stage2_login'] = True;
                $redirect_script = "<script>setTimeout(function () {window.location.href = '$redirect_url';},2000);</script>";
            } else {
                if ($x == "Invalid") {
                    $errors = "The code entered is incorrect";
                } else {
                    $errors = "An error occured whislt checking the 2FA code";
                }
            }
        }
    }

}


?>

<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Single Sign On">
    <meta name="author" content="Zach Matcham">
    <!--- Favicons -->
    <link rel="icon" href="../assets/favicons/favicon.ico">

    <title>2FA Verification - zSSO</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <link href="../css/login.css" rel="stylesheet">
    <?php if (!empty($redirect_script) && empty($errors)): ?>
        <?php echo ($redirect_script); ?>
    <?php endif; ?>
</head>
<body class="text-center">
<div class="wrapper border rounded px-5" style="background-color: #f5f5f5;">
    <img class="mt-2" src="../assets/logo.png" alt="zSSO Logo" style="width: 20%;">
    <h2>zSSO</h2>
    <h5>To log into <?php echo $site_name; ?>, we just need to check who you are.</h5>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo $errors ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success ?></div>
    <?php endif; ?>
    <?php if (empty($success) && empty($errors)): ?>
    <p>To do this, we've sent a code to your email address.</p>
    <p>Email Address: <?php echo mask_email($email); ?> <br>
    </p>
    <hr/>
    <form id="2fa-form" name="2fa-form" action="" method="post" class="mt-1">
        <label class="my-2">Please enter the code sent to you: </label>
        <div class="digit-group" data-group-name="digits" data-autosubmit="true" autocomplete="off">
            <input type="number" id="digit-1" name="digit-1" data-next="digit-2" autofocus/>
            <input type="number" id="digit-2" name="digit-2" data-next="digit-3" data-previous="digit-1" />
            <input type="number" id="digit-3" name="digit-3" data-next="digit-4" data-previous="digit-2" />
            <span class="splitter">&ndash;</span>
            <input type="number" id="digit-4" name="digit-4" data-next="digit-5" data-previous="digit-3" />
            <input type="number" id="digit-5" name="digit-5" data-next="digit-6" data-previous="digit-4" />
            <input type="number" id="digit-6" name="digit-6" data-previous="digit-5" />
        </div>
        <br>
        <input type="hidden" id="2fa-verify" name="2fa-verify">
        <!--        <input type="submit" class="btn btn-primary my-1" value="Verify">-->
    </form>
    <?php endif; ?>
    <div class="mt-1">
        <hr>
        <p class="mt-2 text-muted">Server: <?php echo $server_name?></p>
        <p class="mt-1 mb-3 text-muted">&copy; <script>document.write(new Date().getFullYear())</script> Zach Matcham</p>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
</body>

<script>
    // Handles movement between inputs
    // Get every digit in group
    $('.digit-group').find('input').each(function() {
        $(this).attr('maxlength', 1);
        // When keyup event
        $(this).on('keyup', function(e) {
            var parent = $($(this).parent());
            // Get previous if backspace or left arrow
            if(e.keyCode === 8 || e.keyCode === 37) {
                var prev = parent.find('input#' + $(this).data('previous'));
                if (prev.length) {
                    $(prev).select();
                }
                // Otherwise, if 0-9, a-z, A-Z, or right arrow entered, go right
            } else if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 65 && e.keyCode <= 90) || (e.keyCode >= 96 && e.keyCode <= 105) || e.keyCode === 39) {
                var next = parent.find('input#' + $(this).data('next'));
                if (next.length) {
                    $(next).select();
                } else {
                    if (parent.data('autosubmit')) {
                        // document.forms["2fa_form"].submit();
                        document.getElementById('2fa-form').submit();
                    }
                }
            }
        });
    });
    // Concatenate the inputs into one
    $(function() {
        $('#digit-1, #digit-2, #digit-3, #digit-4, #digit-5, #digit-6').on('input', function() {
            $('#2fa-verify').val(
                $('#digit-1, #digit-2, #digit-3, #digit-4, #digit-5, #digit-6').map(function() {
                    return $(this).val();
                }).get().join('')
            );
        });
    });
</script>
