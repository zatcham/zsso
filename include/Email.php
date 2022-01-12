<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Email {
    public static function instantiateMailer() {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            global $smtp_host, $smtp_username, $smtp_password, $smtp_security, $smtp_port, $smtp_auth;
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = $smtp_auth;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = $smtp_security;
            $mail->Port = $smtp_port;
            global $email_from_address;
            $mail->setFrom($email_from_address, "zSSO");
            return $mail;
        } catch (Exception $e) {
            return ($e);
        }
    }

    // Sends the 2FA code to the user
    public static function send2FA($to_address, $ip_address, $device, $token, $broker) {
        $template = self::getTemplate("2fa");
        $variables = array();
        $variables['REQUEST_TIME'] = date('Y-m-d H:i:s');
        $variables['REQUEST_DEVICE'] = $device;
        $variables['REQUEST_SITE'] = $broker;
        if ($ip_address == "127.0.0.1") {
            $location = "Localhost";
        } else {
            $ip_details = json_decode(file_get_contents("http://ipinfo.io/{$ip_address}/json"));
            $location = $ip_details->city . ", " . $ip_details->region . ", " . $ip_details->country;
        }
        $variables['REQUEST_LOCATION'] = $location;
        $token = strval($token);
        for ($i = 0; $i < strlen($token); $i++) {
            $a = $i + 1;
            $variables['digit_' . $a] = $token[$i];
        }
        foreach ($variables as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }
        try {
            $mailer = self::instantiateMailer();
            $mailer->Subject = "Your verification code is $token";
            $mailer->addAddress($to_address);
            $mailer->isHTML();
            $mailer->MsgHTML($template);
            if ($mailer->send()) {
                return True;
            } else {
                return False;
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return False;
        }
    }

    // Gets template based off of file
    public static function getTemplate($template) {
        $template_path = '../../email-templates';
        if ($template == "2fa") {
            $template_path .= "/2fa.html";
            $file = fopen($template_path, "r") or die("Unable to open file!");
            $file_out = fread($file, filesize($template_path));
            fclose($file);
            return ($file_out);
        }
        return False;
    }

}

