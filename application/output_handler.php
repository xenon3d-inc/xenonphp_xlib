<?php
function X_output_handler($content) {
    global $X_ERROR;
    if (empty($X_ERROR) || DISPLAY_ERRORS) return false;
    switch (@$X_ERROR['type']) {

        // Fatal errors
        case E_ERROR:
        case E_PARSE:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
        case E_CORE_ERROR:
            header("Location: ".BASE_URL.'/500.php');
            return;


        // Warnings
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            //TODO Log WARNING $X_ERROR['message']
            return;


        // Notice
        case E_NOTICE:
        case E_USER_NOTICE:
            //TODO Log NOTICE $X_ERROR['message']
            return false;


        // Deprecation
        case E_STRICT:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            //TODO Log DEPRECATION $X_ERROR['message']
            return false;

    }
    return false;
}
