<?php
function X_error_handler($type, $message, $file, $line, $vars) {
    global $X_ERROR;
    $X_ERROR = [
        'type' => $type,
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'error_reporting' => error_reporting(),
    ];
    if (DISPLAY_ERRORS) {
        $errorType = null;
        switch ($type) {
            case E_ERROR: $errorType = "E_ERROR";break;
            case E_PARSE: $errorType = "E_PARSE";break;
            case E_COMPILE_ERROR: $errorType = "E_COMPILE_ERROR";break;
            case E_USER_ERROR: $errorType = "E_USER_ERROR";break;
            case E_CORE_ERROR: $errorType = "E_CORE_ERROR";break;
            case E_RECOVERABLE_ERROR: $errorType = "E_RECOVERABLE_ERROR";break;
            case E_WARNING: if(error_reporting()) $errorType = "E_WARNING";break;
            case E_CORE_WARNING: if(error_reporting()) $errorType = "E_CORE_WARNING";break;
            case E_COMPILE_WARNING: if(error_reporting()) $errorType = "E_COMPILE_WARNING";break;
            case E_USER_WARNING: if(error_reporting()) $errorType = "E_USER_WARNING";break;
            case E_NOTICE: if(error_reporting()) $errorType = "E_NOTICE";break;
            case E_USER_NOTICE: if(error_reporting()) $errorType = "E_USER_NOTICE";break;
            case E_STRICT: if(error_reporting()) $errorType = "E_STRICT";break;
            case E_DEPRECATED: if(error_reporting()) $errorType = "E_DEPRECATED";break;
            case E_USER_DEPRECATED: if(error_reporting()) $errorType = "E_USER_DEPRECATED";break;
        }
        if ($errorType) {
            echo "<pre class='error'>\n\n ";
                echo "<i>$errorType</i>: <u>$message</u> in '<b>$file</b>' on line $line\n\n";
                echo "<small>";
                    debug_print_backtrace();
                echo "</small>";
            echo "\n\n</pre>";
        }
    }
}
