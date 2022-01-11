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