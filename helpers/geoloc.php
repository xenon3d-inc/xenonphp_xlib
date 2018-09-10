<?php
function X_geoloc($ip = null) {
    if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
    if (($data = @file_get_contents("http://ip-api.com/json/$ip")) && ($geoloc = @json_decode($data, true))) {
        return $geoloc;
    }
}

function X_geoloc_get_countryCode($default = null) {
    if ($default == null) {
        $default = "US";
        if (defined('X_DEFAULT_COUNTRYCODE')) $default = X_DEFAULT_COUNTRYCODE;
    }
    $geoloc = X_geoloc();
    $countryCode = @$geoloc['countryCode'];
    if (!$countryCode) return $default;
    return $countryCode;
}

