<?php
function X_output_handler($content) {
    global $X_ERROR;
    if (empty($X_ERROR)) return false;
    switch (@$X_ERROR['type']) {

        // Fatal errors
        case E_ERROR:
        case E_PARSE:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
        case E_CORE_ERROR:
            $X_ERROR['fatal'] = true;
            if (DISPLAY_ERRORS) return false;
            header("Location: ".BASE_URL.'/500.php');
            if (defined('ERROR_LOGGER')) call_user_func(ERROR_LOGGER, $X_ERROR, 'FATAL');
            return;


        // Warnings
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
        case E_RECOVERABLE_ERROR:
            $X_ERROR['fatal'] = false;
            if (defined('ERROR_LOGGER')) call_user_func(ERROR_LOGGER, $X_ERROR, 'WARNING');
            break;


        // Notice
        case E_NOTICE:
        case E_USER_NOTICE:
            $X_ERROR['fatal'] = false;
            if (defined('ERROR_LOGGER')) call_user_func(ERROR_LOGGER, $X_ERROR, 'NOTICE');
            break;


        // Deprecation
        case E_STRICT:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $X_ERROR['fatal'] = false;
            if (defined('ERROR_LOGGER')) call_user_func(ERROR_LOGGER, $X_ERROR, 'DEPRECATION');
            break;

    }
    return false;
}
