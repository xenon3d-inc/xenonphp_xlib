<?php
function X_error_handler($type, $message, $file, $line) {
    global $X_ERROR;
    $X_ERROR = [
        'type' => $type,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ];
    if (DISPLAY_ERRORS) {
        echo "<br>ERROR: $message in '$file' on line $line<br>";
    }
}
