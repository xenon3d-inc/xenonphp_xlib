<?php

namespace Xenon\Controller;

abstract class BaseController
{
    public function redirect($route, $routeParams = [], $params = [], $lang = true) {
        if (AJAX && !PJAX) header("X-Redirect: ".X_url($route, $routeParams, $params, $lang));
        else header("Location: ".X_url($route, $routeParams, $params, $lang));
        $this->exit();
    }

    public function setView($view) {
        global $X_ROUTE;
        $X_ROUTE->setView($view);
    }

    public function return404() {
        global $X_ROUTE;
        $X_ROUTE->return404();
    }

    public function returnCode($code) {
        global $X_ROUTE;
        $X_ROUTE->returnCode($code);
    }

    public function forward($route, $routeParams = null) {
        global $X_ROUTE;
        $this->cleanOutputBuffers();
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
