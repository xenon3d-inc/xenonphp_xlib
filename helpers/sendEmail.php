<?php 

function X_sendEmail($to, $subject, $body, $isHtml = false, $replyTo = null, $from_email = null, $from_name = null, $cc = null, $bcc = null) {
    global $X_CONFIG;

    $to = array_filter((array)$to, 'trim');
    if (!$to) {
        if (DISPLAY_ERRORS) echo "X_sendEmail ERROR: No recipient given for email with subject $subject\n";
        return false;
    }

    $filterDontSend = function($email) {
        if (!trim($email)) return false;
        if (strpos(trim($email), '@fakeemail.com') !== false) {
            return false;
        }
        return true;
    };

    $to = array_filter((array)$to, $filterDontSend);
    $cc = array_filter((array)$cc, $filterDontSend);
    $bcc = array_filter((array)$bcc, $filterDontSend);

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->Host = !empty($X_CONFIG['smtp']['host'])? $X_CONFIG['smtp']['host'] : 'localhost';
        $mail->Port = !empty($X_CONFIG['smtp']['port'])? $X_CONFIG['smtp']['port'] : 465;
        $mail->SMTPSecure = !empty($X_CONFIG['smtp']['security'])? $X_CONFIG['smtp']['security'] : 'ssl';
        $mail->isSMTP();
        if (!isset($X_CONFIG['smtp']['auth']) || $X_CONFIG['smtp']['auth']) {
            $mail->SMTPAuth = true;
            $mail->Username = @$X_CONFIG['smtp']['username'];
            $mail->Password = @$X_CONFIG['smtp']['password'];
        }
        if ($from_email === null && !empty($X_CONFIG['smtp']['from_email'])) {
            $from_email = @$X_CONFIG['smtp']['from_email'];
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
            // fake email : just ignore it, no error
            return true;
        }

        foreach ($to as $to) if (trim($to)) $mail->addAddress(trim($to));
        if (!empty($cc))  foreach ($cc as $cc)   if (trim($cc))  $mail->addCC(trim($cc));
        if (!empty($bcc)) foreach ($bcc as $bcc) if (trim($bcc)) $mail->addBcc(trim($bcc));
        
        if (!empty($X_CONFIG['smtp']['smtp_debug'])) {
		    $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
	    }
        
        $mail->send();
        return true;
    } catch(\PHPMailer\PHPMailer\Exception $e) {
        if (DISPLAY_ERRORS) echo "X_sendEmail ERROR: ".$mail->ErrorInfo."\n";
    }
    return false;
}

function X_sendEmailTemplate($template, array $vars = [], $to = null, $subject = null, $reply_to = null, $from_email = null, $from_name = null, $cc = null, $bcc = null) {
    global $X_VARS, $X_EMAIL_TEMPLATE;

    // Check template
    $templatePath = EMAIL_PATH.$template.".phtml";
    if (!is_file($templatePath)) {
        if (DISPLAY_ERRORS) echo "X_sendEmail ERROR: Email Template not found in $templatePath\n";
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
        if (DISPLAY_ERRORS) echo "X_sendEmail ERROR: No recipient given for email template $template\n";
        return false;
    }

    // Send the email
    return X_sendEmail($to, $subject, $body, true, $reply_to, $from_email, $from_name, $cc, $bcc);
}
