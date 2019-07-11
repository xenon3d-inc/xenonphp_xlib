<?php
function X_include_start_buffer($filepath, $required = false, $once = false) {
    global $X, $X_PROJECT, $X_ERROR, $X_CONFIG, $X_CHARSET, $X_ROUTE, $X_DB, $X_LAYOUT, $X_TITLE, $X_PAGETITLE, $X_VIEW_CONTENT, $X_VIEW_RETURN, $X_USER, $X_CONTROLLER, $X_VARS, $X_EMAIL_TEMPLATE;
    if (!empty($X_VARS) && is_array($X_VARS)) {
        foreach ($X_VARS as $key=>$val) {
            if (!is_numeric($key)) $$key = $val;
        }
    }
    ob_start(OUTPUT_HANDLER);
    if ($required && $once) {
        return require_once $filepath;
    } else if ($required) {
        return require $filepath;
    } else if ($once) {
        return include_once $filepath;
    } else {
        return include $filepath;
    }
}

function X_include($filepath, $required = false, $once = false) {
    $return = X_include_start_buffer($filepath, $required, $once);
    ob_flush();
    return $return;
}

function X_include_return($filepath, $required = false, $once = false, &$return = null) {
    $return = X_include_start_buffer($filepath, $required, $once);
    return ob_get_clean();
}

function X_include_discard($filepath, $required = false, $once = false) {
    $return = X_include_start_buffer($filepath, $required, $once);
    ob_clean();
    return $return;
}
