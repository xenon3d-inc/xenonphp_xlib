<?php
function X_include_start_buffer($filepath, $required = false, $once = false) {
    global $X, $X_PROJECT, $X_ERROR, $X_CONFIG, $X_CHARSET, $X_ROUTE, $X_DB, $X_LAYOUT, $X_TITLE, $X_PAGETITLE, $X_VIEW_CONTENT, $X_USER;
    ob_start(OUTPUT_HANDLER);
    if ($required && $once) {
        require_once $filepath;
    } else if ($required) {
        require $filepath;
    } else if ($once) {
        include_once $filepath;
    } else {
        include $filepath;
    }
}

function X_include($filepath, $required = false, $once = false) {
    X_include_start_buffer($filepath, $required, $once);
    ob_flush();
}

function X_include_return($filepath, $required = false, $once = false) {
    X_include_start_buffer($filepath, $required, $once);
    return ob_get_clean();
}

function X_include_discard($filepath, $required = false, $once = false) {
    X_include_start_buffer($filepath, $required, $once);
    ob_clean();
}
