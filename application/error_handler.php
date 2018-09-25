<?php
function X_error_handler($error) {
    global $X_ERROR;
    $X_ERROR = $error;
}
