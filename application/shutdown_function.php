<?php
function X_shutdown_function() {
    global $X_ERROR;
    $X_ERROR = error_get_last();
    if ($X_ERROR !== NULL) {
        if (ERROR_HANDLER) {
            call_user_func(ERROR_HANDLER, $X_ERROR['type'], $X_ERROR['message'], $X_ERROR['file'], $X_ERROR['line'], []);
        }
    }
}
