<?php

require_once ("init.php");

function connectToDB($dbhost, $dbusername, $dbpassword, $dbname) {
    $dbconn = new mysqli($dbhost, $dbusername, $dbpassword, $dbname);
    if ($dbconn->connect_error) { // If error in connection, end
        die("DB connection failed: " . $dbconn->connect_error);
    } else {
        return $dbconn;
    }
}

function connectDBWithVars() {
    global $db_host, $db_username, $db_password, $db_name;
    $dbconn = new mysqli($db_host, $db_username, $db_password, $db_name);
    if ($dbconn->connect_error) { // If error in connection, end
        die("DB connection failed: " . $dbconn->connect_error);
    } else {
        return $dbconn;
    }
}

function getSiteName($broker_token) {
    $dbconn = connectDBWithVars();
    $sql = "SELECT site_name FROM brokers WHERE token=?;";
    $stmt = $dbconn->prepare($sql);
    if ($stmt == False) {
        return False;
    } else {
        $stmt->bind_param("s", $broker_token);
        $stmt->execute();
        if ($stmt == False) {
            return False;
        } else {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($name);
                if ($stmt->fetch()) {
                    return $name;
                }
            } else {
                return False;
            }
        }
    }
}

function mask($str, $first, $last) {
    $len = strlen($str);
    $toShow = $first + $last;
    return substr($str, 0, $len <= $toShow ? 0 : $first).str_repeat("*", $len - ($len <= $toShow ? 0 : $toShow)).substr($str, $len - $last, $len <= $toShow ? 0 : $last);
}

function mask_email($email) {
    $mail_parts = explode("@", $email);
    $domain_parts = explode('.', $mail_parts[1]);

    $mail_parts[0] = mask($mail_parts[0], 2, 1); // show first 2 letters and last 1 letter
    $domain_parts[0] = mask($domain_parts[0], 2, 1); // same here
    $mail_parts[1] = implode('.', $domain_parts);

    return implode("@", $mail_parts);
}

function verify2FA($userid, $otp) {
    $dbconn = connectDBWithVars();
    $sql = "SELECT id, token FROM tfa_tokens WHERE user_id = ? and used = 0 and expiration_date >= DATE_SUB(NOW(), INTERVAL '1' HOUR);";
    if ($stmt = $dbconn->prepare($sql)) {
        $stmt->bind_param("s", $userid);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $count = 0;
            while ($row = $result->fetch_array()) {
                if ($row['token'] == $otp) {
                    $token_id = $row['id'];
                    $count = 1;
                }
            }
            if ($count == 1) {
                $sql2 = "UPDATE tfa_tokens SET used = 1 WHERE id = ?";
                if ($stmt = $dbconn->prepare($sql2)) {
                    $stmt->bind_param("s", $token_id);
                    if ($stmt->execute()) {
                        return True;
                    } else {
                        return False;
                    }
                } else {
                    return False;
                }
            } else {
                return "Invalid";
            }
        } else {
            return "Invalid";
        }
    } else {
        return False;
    }
}

function generateToken() {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $len = strlen($chars);
    $max_len = 40;
    $str = '';
    for ($i = 0; $i < $max_len; $i++) {
        $str .= $chars[rand(0, $len - 1)];
    }
    return $str;
}

function generateLoginToken($user_id, $broker, $ip_address, $device) {
    $token = generateToken();
    $dbconn = connectDBWithVars();
    $sql = "INSERT INTO auth_tokens (user_id, token, ip_address, device, initiating_broker) VALUES (?, ?, ?, ?, ?);";
    $stmt = $dbconn->prepare($sql);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("sssss", $user_id, $token, $ip_address, $device, $broker);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    } else {
        return $token;
    }
}

function verifyBrokerToken($token) {
    $dbconn = connectDBWithVars();
    $sqlq = "SELECT `id` FROM brokers WHERE `token`=? LIMIT 1;";
    $stmt = $dbconn->prepare($sqlq);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    }
    $stmt->store_result();
    $stmt->bind_result($id);
    $stmt->fetch();
    if ($stmt !== False) {
        return $id;
    } else {
        return False;
    }
}

function getBrokerEndpoint($broker) {
    $dbconn = connectDBWithVars();
    $sqlq = "SELECT `endpoint` FROM brokers WHERE `id`=? LIMIT 1;";
    $stmt = $dbconn->prepare($sqlq);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("s", $broker);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    }
    $stmt->store_result();
    $stmt->bind_result($url);
    $stmt->fetch();
    if ($stmt !== False) {
        return $url;
    } else {
        return False;
    }
}

function getTFAType($userid) {
    $dbconn = connectDBWithVars();
    $sqlq = "SELECT `2fa_type` FROM users WHERE `id`=? LIMIT 1;";
    $stmt = $dbconn->prepare($sqlq);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("s", $userid);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    }
    $stmt->store_result();
    $stmt->bind_result($type);
    $stmt->fetch();
    if ($stmt !== False) {
        return $type;
    } else {
        return False;
    }
}

function verifyBrokerSrvToken($token) {
    $dbconn = connectDBWithVars();
    $sqlq = "SELECT `id` FROM brokers WHERE `server_token`=? LIMIT 1;";
    $stmt = $dbconn->prepare($sqlq);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    }
    $stmt->store_result();
    $stmt->bind_result($id);
    $stmt->fetch();
    if ($stmt !== False) {
        return $id;
    } else {
        return False;
    }
}

function verifyUserToken($token, $broker) {
    $dbconn = connectDBWithVars();
    $sqlq = "SELECT `id` FROM brokers WHERE `token`=? and `initiating_broker`=? LIMIT 1;";
    $stmt = $dbconn->prepare($sqlq);
    if ($stmt == False) {
        return False;
    }
    $stmt->bind_param("s", $token, $broker);
    $stmt->execute();
    if ($stmt == False) {
        return False;
    }
    $stmt->store_result();
    $stmt->bind_result($count);
    $stmt->fetch();
    if ($stmt !== False) {
        if ($count == 1) {
            return True;
        } else {
            return False;
        }
    } else {
        return False;
    }
}