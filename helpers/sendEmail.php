<?php 

function X_sendEmail($to, $subject, $body, $isHtml = false, $replyTo = null, $from_email = null, $from_name = null) {
    global $X_CONFIG;
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
        foreach ((array) $to as $to) $mail->addAddress($to);
        $mail->isHTML($isHtml);
        
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch(\PHPMailer\PHPMailer\Exception $e) {
        if (DEV) die("PHPMailer Error: ".$mail->ErrorInfo);
    }
    return false;
}
