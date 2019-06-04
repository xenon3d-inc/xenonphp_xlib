<?php 

function X_reCaptcha_init($action = null) {
    global $X_CONFIG, $RECAPTCHA_INIT;
    if (empty($X_CONFIG['recaptcha']['site_key'])) {
        trigger_error("Missing site_key in application/config/recaptcha.php", E_USER_ERROR);
    }
    if (empty($RECAPTCHA_INIT)) {$RECAPTCHA_INIT = true;?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?=$X_CONFIG['recaptcha']['site_key']?>"></script>
    <?php }?>
    <script>
        var X_reCaptcha_token = null;
        function X_runReCaptcha() {
            grecaptcha.execute('<?=$X_CONFIG['recaptcha']['site_key']?>', {action: '<?=$action?>'}).then(function(token) {
                X_reCaptcha_token = token;
                $('input[name="recaptcha-token"]').val(token);
            });
        }
    </script>
    <?php
}

function X_reCaptcha_run($runNow = false) {
    ?>
    <!-- ReCaptcha -->
    <input type="hidden" name="recaptcha-token" value="">
    <?php if ($runNow):?>
        <script>
            $(function(){
                grecaptcha.ready(function() {
                    X_runReCaptcha();
                });
            });
        </script>
    <?php endif?>
    <!-- -->
    <?php
}

function X_reCaptcha_check($token = null) {
    global $X_CONFIG;
    if ($token === null) $token = $_POST['recaptcha-token'];
    if (empty($X_CONFIG['recaptcha']['secret_key'])) {
        trigger_error("Missing secret_key in application/config/recaptcha.php", E_USER_ERROR);
    }
    if (!empty($token)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'secret'   => $X_CONFIG['recaptcha']['secret_key'],
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);
        $data = curl_exec($ch);
        if ($data && ($data = json_decode($data, true)) && !empty($data['success'])) {
            // Success
            curl_close($ch);
            return true;
        }
        curl_close($ch);
    }
    return false;
}

