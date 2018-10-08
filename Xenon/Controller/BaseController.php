<?php

namespace Xenon\Controller;

abstract class BaseController
{
    public function redirect($route, $routeParams = [], $params = [], $lang = true) {
        header("Location: ".X_url($route, $routeParams, $params, $lang));
        $this->exit();
    }

    public function return404() {
        if (!headers_sent()) http_response_code(404);
        $this->forward('404');
    }

    public function forward($route, $routeParams = null) {
        global $X_ROUTE;
        cleanOutputBuffers();
        $X_ROUTE->setRoute($route, $routeParams);
        $X_ROUTE->execute();
        exit;
    }

    public function getConfig($key) {
        return @$GLOBALS['X_CONFIG'][$key];
    }

    public function cleanOutputBuffers() {
        while (ob_get_level()) ob_end_clean();
    }

    public function exit() {
        $this->cleanOutputBuffers();
        exit;
    }

}
