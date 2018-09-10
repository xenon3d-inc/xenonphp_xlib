<?php

namespace Xenon\Controller;

abstract class AbstractController
{
    public function redirect($url) {
        header((AJAX? 'X-Redirect' : 'Location').": $url");
        ob_clean();
        exit;
    }

    public function replaceUrl($url) {
        header("X-ReplaceUrl: $url");
	if (AJAX) {
	    ob_clean();
	    exit;
	}
    }
    
    public function getRoute() {
        return \Xenon\Routing\Route::$currentInstance;
    }
    
    public function return404() {
        if (!headers_sent()) http_response_code(404);
        $this->forward('404');
    }
    
    public function forward($view) {
        // Reset and Get View Buffer
        ob_clean();
        ob_start();
        include VIEW_PATH . $view . '.phtml';
        $X_VIEW_CONTENT = ob_get_clean();

        // Output Layout
        if (!empty($X_LAYOUT)) {
            include LAYOUT_PATH . $X_LAYOUT . ".phtml";
        } else {
            echo $X_VIEW_CONTENT;
        }
        exit;
    }

    public function getConfig($key) {
        return $GLOBALS['X_CONFIG'][$key];
    }

}
