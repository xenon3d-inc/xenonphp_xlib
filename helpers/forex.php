<?php
function X_forex($base = "CAD") {
    if (($json = @file_get_contents("http://api.fixer.io/latest?base=$base")) && ($forex = @json_decode($json, true)) && $forex) {
        return $forex;
    }
}

function X_display_price($price, $base = null) {
    return X_get_price($price, $base, true);
}

function X_get_price($price, $base = null, $format = false) {
    global $X_COUNTRY_CODE, $X_FOREX;
    if ($base == null) {
        $base = "USD";
        if (defined('X_DEFAULT_CURRENCY')) $base = X_DEFAULT_CURRENCY;
    }
    include_once 'geoloc.php';
    if (empty($X_FOREX)) {
        $cache_path = CACHE_PATH."forex_$base.php";
        if (!is_file($cache_path) || !($cachedForex = @file_get_contents($cache_path)) || !($X_FOREX = @json_decode($cachedForex, true)) || $X_FOREX['date'] != date('Y-m-d')) {
            $X_FOREX = X_forex($base);
            if ($X_FOREX) file_put_contents($cache_path, @json_encode($X_FOREX, JSON_UNESCAPED_UNICODE));
        }
    }
    if (!$X_FOREX) return ($format) ? $price . " " . $base : $price;
    if (empty($X_COUNTRY_CODE)) $X_COUNTRY_CODE = X_geoloc_get_countryCode();
    $text = "price";
    $dec_point = ".";
    $t_sep = ",";
    if (!in_array($X_COUNTRY_CODE, ['CA', 'US'])) {
        $countryCode = "US";
        if (defined('X_DEFAULT_COUNTRYCODE')) $countryCode = X_DEFAULT_COUNTRYCODE;
    } else $countryCode = $X_COUNTRY_CODE;
    switch ($countryCode) {
        case 'US':
            $currency = 'USD';
            $text = 'USD $price';
            break;
        case 'CA':
            $currency = 'CAD';
            $text = 'price$ CAD';
            $t_sep = " ";
            break;
    }
    if ($base == $currency) {
        $rate = 1;
    } else {
        $rate = $X_FOREX['rates'][$currency];
    }
    if ($format) {
        return str_replace("price", number_format($price * $rate, 2, $dec_point, $t_sep), $text);
    } else {
        return $price * $rate;
    }
}
