<?php

/**
 * 
 * @param route string or bool(true) $route (true to use existing)
 * @param routeParams array or bool(true) $routeParams (true to use existing params) default empty
 * @param params array or bool(true) $params (true to use existing params) default empty
 * @param lang string or bool(true) $lang (true to use existing) default use existing
 * @param canonical bool $canonical Return FULL URL with domain name
 * @return string URL
 */
function X_url($route, $routeParams = [], $params = [], $lang = true, $canonical = false) {
    return \Xenon\Routing\Route::$currentInstance->getUrl($route, $routeParams, $params, $lang, $canonical);
}
