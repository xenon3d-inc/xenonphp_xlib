<?php 

function X_sendEmail($to, $subject, $body, $isHtml = false, $replyTo = null, $from_email = null, $from_name = null, $cc = null, $bcc = null) {
    global $X_CONFIG;

    $to = array_filter((array)$to, 'trim');
    if (!trim($to)) {
        if (DEV) die("No recipient given for email with subject $subject");
        return false;
    }

    $filterDontSend = function($email) {
        if (!trim($email)) return false;
        if (strpos(trim($email), '__DONTSEND__') == 0) {
            return false;
        }
        return true;
    };

    $to = array_filter((array)$to, $filterDontSend);
    $cc = array_filter((array)$cc, $filterDontSend);
    $bcc = array_filter((array)$bcc, $filterDontSend);

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if (!empty($X_CONFIG['smtp']['username'])) {
            $mail->isSMTP();
            $mail->Host = !empty($X_CONFIG['smtp']['host'])? $X_CONFIG['smtp']['host'] : 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = $X_CONFIG['smtp']['username'];
            $mail->Password = @$X_CONFIG['smtp']['password'];
            $mail->SMTPSecure = 'ssl';
            $mail->Port = !empty($X_CONFIG['smtp']['port'])? $X_CONFIG['smtp']['port'] : 465;
        }
        if ($from_email === null && !empty($X_CONFIG['smtp']['from_email'])) {
            $from_email = $X_CONFIG['smtp']['from_email'];
            $from_name = @$X_CONFIG['smtp']['from_name'];
        }
        if ($from_email) {
            $mail->setFrom($from_email, $from_name);
        }
        if ($replyTo) {
            $mail->AddReplyTo($replyTo);
        }

        $mail->CharSet = !empty($X_CONFIG['smtp']['charset'])? $X_CONFIG['smtp']['charset'] : 'UTF-8';
        $mail->Encoding = !empty($X_CONFIG['smtp']['encoding'])? $X_CONFIG['smtp']['encoding'] : 'base64';
        
        $mail->isHTML($isHtml);
        
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        if (empty($to)) {
            // __DONTSEND__ : just ignore it, no error
            return true;
        }

        foreach ($to as $to) if (trim($to)) $mail->addAddress(trim($to));
        if (!empty($cc))  foreach ($cc as $cc)   if (trim($cc))  $mail->addCC(trim($cc));
        if (!empty($bcc)) foreach ($bcc as $bcc) if (trim($bcc)) $mail->addBcc(trim($bcc));

        $mail->send();
        return true;
    } catch(\PHPMailer\PHPMailer\Exception $e) {
        if (DEV) die("PHPMailer Error: ".$mail->ErrorInfo);
    }
    return false;
}

function X_sendEmailTemplate($template, array $vars = [], $to = null, $subject = null, $reply_to = null, $from_email = null, $from_name = null, $cc = null, $bcc = null) {
    global $X_VARS, $X_EMAIL_TEMPLATE;

    // Check template
    $templatePath = EMAIL_PATH.$template.".phtml";
    if (!is_file($templatePath)) {
        if (DEV) die("Email Template not found in $templatePath");
        return false;
    }

    // Prepare vars and params
    $tmp_X_VARS = $X_VARS;
    if (!empty($vars) && is_array($vars)) $X_VARS = $vars;
    $body = X_include_return($templatePath);
    if (!empty($X_EMAIL_TEMPLATE) && is_array($X_EMAIL_TEMPLATE)) {
        foreach ($X_EMAIL_TEMPLATE as $key => $val) {
            if ($$key === null) {
                $$key = $val;
            }
        }
    }

    // Reset globals
    $X_VARS = $tmp_X_VARS;
    $X_EMAIL_TEMPLATE = null;

    $to = array_filter((array)$to, 'trim');

    if (empty($to)) {
        if (DEV) die("No recipient given for email template $template");
        return false;
    }

    // Send the email
    return X_sendEmail($to, $subject, $body, true, $reply_to, $from_email, $from_name, $cc, $bcc);
}
