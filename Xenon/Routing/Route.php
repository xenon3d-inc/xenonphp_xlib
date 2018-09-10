<?php
namespace Xenon\Routing;

class Route {

    // Route Public Properties
    public $lang = DEFAULT_LANG;
    public $viewFile;
    public $route = '';
    public $params = array();
    public $routeParams = array();

    public static $currentInstance = null;
    protected $url = URL;
    protected $routeArray = array();
    protected $routes = array();

    function __construct(array $routes = array()) {
        self::$currentInstance = $this;
        $this->routes = $routes;
        $this->viewFile = VIEW_PATH.'index.phtml';

        if ( // Stop at the first function that returns false
            $this->prepare_url() === false ||
            $this->decode_lang() === false ||
            $this->decode_url() === false ||
            $this->decode_params() === false ||
            $this->decode_route() === false
            ){ // Return 404 if any function returns false
            $this->return404();
        }
    }

    public function setRoute($route) {
        if ($this->decode_route($route) === false) {
            $this->return404();
        }
    }

    protected function prepare_url() {
        $this->url = strtolower($this->url);
        $this->url = str_replace('..', '.', $this->url);
    }

    protected function decode_lang() {
        if (!MULTILANG) return;
        if (preg_match("#^/(".LANGS.")(/(.*)/?)?$#i", $this->url, $matches)) {
            $this->url = $matches[3];
            $this->lang = $matches[1];
        } else {
            return false;
        }
    }

    protected function decode_url() {
        $this->params = array();
        if (preg_match("#^/?(([\w\d_/\.-]*[\w\d_\.-]+))/?(\?(.+))?$#i", $this->url, $matches)) {
            $this->routeArray = array_filter(explode('/', $matches[1]), 'strlen');
            $this->url = !empty($matches[3]) ? $matches[3] : null;
        }
    }

    protected function decode_params() {
        if (!empty($_GET)) {
            $this->params = $_GET;
        }
    }

    protected function getRouteFromUrl() {
        return $this->getRouteKeyFromRouteUrl(implode('/', $this->routeArray), count($this->routeParams), $this->lang);
    }

    protected function decode_route($route = true) { // $route = true means that we take the current url and decode the route from it
        for(;;) {
            $this->route = ($route === true) ? $this->getRouteFromUrl() : $route;
            if ($this->route === false) {
                return false;
            }
            if ($this->route !== null) {
                $this->viewFile = VIEW_PATH . $this->route . '.phtml';
                if (!is_file($this->viewFile)) $this->viewFile = str_replace('admin/', '', $this->viewFile);
                break;
            }
            if (count($this->routeArray) == 0) {
                return false;
            }
            array_unshift($this->routeParams, array_pop($this->routeArray));
        }
    }

    /**
     *
     * @param string $routeUrl
     * @param int $nbParams
     * @param string $lang Pass null to auto detect from the routeUrl, otherwise it must already be removed from routeUrl
     * @return boolean false for 404 NotFound, NULL if exact match not found, otherwise string the matched route key
     */
    public function getRouteKeyFromRouteUrl($routeUrl, $nbParams = 0, $lang = null) {
        //TODO check route definitions and return true if found and set $this->route
        // Return true to stop here and take current route, return false to stop here and get 404, otherwise continue
        if ($lang === null && MULTILANG) {
            if (preg_match("#^/(".LANGS.")(/(.*)/?)?$#i", $routeUrl, $matches)) {
                $routeUrl = $matches[3];
                $lang = $matches[1];
            } else return false;
        }
        foreach ($this->routes as $key => $data) {
            if (is_numeric($key)) {
                switch ($data) {
                    case 'EXISTING_FILE' :
                        if (is_file(VIEW_PATH . $routeUrl . '.phtml')) return $routeUrl;
                        break;
                }
            } else {
                if (is_array($data)) {
                    if (isset($data['url'])) {
                        $url = $data['url'];
                        if (is_array($data['url'])) {
                            $url = isset($url[$lang])? $url[$lang] : $url[DEFAULT_LANG];
                        }
                        if ($url === $routeUrl) {
                            // URL MATCHES
                            if (isset($data['params'])) {
                                if (is_array($data['params']) && count($data['params']) != $nbParams) continue;
                                if (is_numeric($data['params']) && $data['params'] != $nbParams) continue;
                            }
                            return $key;
                        }
                    } else {
                        // data[url] not specified... More options to be added in the future
                    }
                } elseif ($data === $routeUrl) return $key;
            }
        }
    }

    public function getRouteUrlFromRouteKey($route, $lang = null) {
        if ($route === "" || $route === null) return null;
        if (isset($this->routes[$route])) {
            if (is_array($this->routes[$route])) {
                if (isset($this->routes[$route]['url'])) {
                    if (is_array($this->routes[$route]['url'])) {
                        if (isset($this->routes[$route]['url'][$lang])) {
                            $route = $this->routes[$route]['url'][$lang];
                        } else {
                            $route = $this->routes[$route]['url'][DEFAULT_LANG];
                        }
                    } else {
                        $route = $this->routes[$route]['url'];
                    }
                } else {
                    // data[url] not specified... More options to be added in the future
                }
            } else {
                $route = $this->routes[$route];
            }
        }
        return (($lang && MULTILANG)? '/' . $lang : '') . '/' . $route;
    }

    /**
     *
     * @param route string or bool(true) $route (true to use existing)
     * @param routeParams array or bool(true) $routeParams (true to use existing params) default empty
     * @param getparams array or bool(true) $getparams (true to use existing getparams) default empty
     * @param lang string or bool(true) $lang (true to use existing) default use existing
     * @param canonical bool $canonical Return FULL URL with domain name
     * @return string URL
     */
    public function getUrl($route, $routeParams = [], $getparams = [], $lang = true, $canonical = false) {
        if ($route === true) $route = $this->route;
        if ($routeParams === true) $routeParams = $this->routeParams;
        if ($getparams === true) $getparams = $this->params;
        if ($lang === true) $lang = $this->lang;

        // Handle non-existing or null route
        if ($route === null) return null;

        // Route Params
        $params = $routeParams;
        // Check if the routeParams array is associative
        if (is_array($params) && count(array_filter(array_keys($params), 'is_string')) > 0 && is_array($this->routes[$route]['params'])) {
            $params = [];
            foreach ($routeParams as $key => $val) {
                foreach ($this->routes[$route]['params'] as $i => $p) {
                    if ($key === $p) {
                        $params[$i] = (trim($val) == "")? "_" : trim($val);
                        break;
                    }
                }
            }
            sort($params, SORT_NUMERIC);
        }
        $params = (is_array($params) && count($params)) ? '/' . implode('/', $params) : "";

        // GET Params
        $queryString = '';
        if (is_array($getparams)) {
            foreach ($getparams as $key => $val) {
                $queryString .= ($queryString? '&':'?') . urlencode($key) . '=' . urlencode($val);
            }
        }

        // Domain / Canonical URL
        $domain = "";
        if ($canonical) {
            $domain = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://").$_SERVER['HTTP_HOST'];
        }

        // Base UrL
        $baseURL = defined('BASE_URL') ? BASE_URL : '';

        // FINAL URL
        return $domain.$baseURL.$this->getRouteUrlFromRouteKey($route, $lang).$params.$queryString;
    }

    public function return404() {
        if (!headers_sent()) http_response_code(404);
        $this->route = null;
        $this->viewFile = VIEW_PATH . '404.phtml';
    }

    public function getTranslatedUrl($lang) {
        return $this->getUrl($this->route, null, null, $lang);
    }

    public function setParam($key, $value = null) {
        $this->params[$key] = $value;
    }

    public function getParam($key, $default = null) {
        return isset($this->params[$key]) ? $this->params[$key] : $default;
    }

    public function setRouteParam($index = 0, $value = null) {
        $this->routeParams[$index] = $value;
    }

    public function getRouteParam($index = 0, $default = null) {
        if (!is_numeric($index) && !empty($this->routes[$this->route]['params']) && is_array($this->routes[$this->route]['params'])) {
            foreach ($this->routes[$this->route]['params'] as $i => $val) {
                if ($val == $index) {
                    $index = $i;
                    break;
                }
            }
        }
        return isset($this->routeParams[$index]) ? $this->routeParams[$index] : $default;
    }

    public function __toString() {
        return is_string($this->route) ? $this->route : "";
    }

}
