<?php
namespace Xenon\Routing;

class Route {

    // Route Public Properties
    public $lang = DEFAULT_LANG;
    public $view;
    public $viewFile;
    public $route = '';
    public $params = array();
    public $routeParams = array();
    public $method = null;

    public $executed = false;

    public static $currentInstance = null;
    protected $url = ROUTE_URL;
    protected $routeArray = array();
    protected $routes = array();

    function __construct(array $routes = array()) {
        self::$currentInstance = $this;
        $this->routes = $routes;
        $this->setView('index');

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

    public function setView($view) {
        $this->view = $view;
        $this->viewFile = VIEW_PATH.$this->view.'.phtml';
    }

    public function setRoute($route, $routeParams = null) {
        if ($this->decode_route($route) === false) {
            $this->return404();
        }
        if ($routeParams !== null) {
            $this->setRouteParams($routeParams);
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
        return $this->getRouteKeyFromRouteUrl(implode('/', $this->routeArray), $this->routeParams, $this->lang, DOMAIN_NAME, PORT);
    }

    protected function decode_route($route = true) { // $route = true means that we take the current url and decode the route from it
        for(;;) {
            $this->route = ($route === true) ? $this->getRouteFromUrl() : $route;
            if ($this->route === false) {
                return false;
            }
            if ($this->route !== null) {
                $this->setView($this->route);
                if (!is_file($this->viewFile) && preg_match("#".preg_quote(ADMIN_URL_COMPONENT)."/#", $this->view) ) {
                    $this->setView(str_replace(ADMIN_URL_COMPONENT.'/', '', $this->view));
                }
                break;
            }
            if (count($this->routeArray) == 0) {
                return false;
            }
            array_unshift($this->routeParams, array_pop($this->routeArray));
        }
        $this->method = METHOD;
        // Add string keys to route params
        if (!empty($this->routeParams) && is_array($this->routeParams) && !empty($this->routes[$this->route]) && is_array($this->routes[$this->route]['params'])) {
            foreach ($this->routeParams as $index => $val) {
                $key = $this->routes[$this->route]['params'][$index];
                if (is_string($key)) $this->routeParams[explode(':',$key)[0]] = $val;
            }
        }
    }

    /**
     *
     * @param string $routeUrl
     * @param int|array $routeParams can be the number of params or the actual route params [key=>val]
     * @param string $lang Pass null to auto detect from the routeUrl, otherwise it must already be removed from routeUrl
     * @return boolean false for 404 NotFound, NULL if exact match not found, otherwise string the matched route key
     */
    public function getRouteKeyFromRouteUrl($routeUrl, $routeParams = 0, $lang = null, $domain = null, $port = null) {
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
                    if ($port !== null && isset($data['port']) && !in_array($port, (array)$data['port'])) {
                        continue;
                    }
                    if ($domain !== null) {
                        if (isset($data['domain']) && !in_array($domain, (array)$data['domain'])) {
                            continue;
                        } else
                        if (isset($data['subdomain']) && !preg_match("#^(".str_replace('.', "\\.", implode('|', (array)$data['subdomain'])).")\.#i", $domain)) {
                            continue;
                        }
                    }
                    if (isset($data['url'])) {
                        $url = $data['url'];
                        if (is_array($data['url'])) {
                            $url = isset($url[$lang])? $url[$lang] : $url[DEFAULT_LANG];
                        }
                        if ($url === $routeUrl) {
                            // URL MATCHES
                            if (isset($data['params'])) {
                                if (is_numeric($routeParams)) {
                                    if (is_array($data['params']) && count($data['params']) != $routeParams) continue;
                                    else if (is_numeric($data['params']) && $data['params'] != $routeParams) continue;
                                } else
                                if (is_array($routeParams)) {
                                    if (is_numeric($data['params']) && count($routeParams) != $data['params']) continue;
                                    else if (is_array($data['params'])) {
                                        if (count($routeParams) != count($data['params'])) continue;
                                        foreach ($data['params'] as $i=>$param) {
                                            if (($pos=strpos($param, ':')) > 0) { // strpos() > 0 this is NOT a mistake. In this case we want to also ignore if the char is at the first position
                                                $k = substr($param, 0, $pos);
                                                $regex = substr($param, $pos+1);
                                                if (isset($routeParams[$i])) {
                                                    // Do not use this route if one of the parameter keys contains a regex and that it does not match the given parameter value
                                                    if (!preg_match("#^($regex)$#i", $routeParams[$i])) continue 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            return $key;
                        }
                    } else {
                        // data[url] not specified
                        if ($key === $routeUrl) return $key;
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
                    // data[url] not specified... route url = route key
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
        elseif (!is_array($routeParams)) $routeParams = [$routeParams];
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

        // Base UrL
        $baseURL = defined('BASE_URL') ? BASE_URL : '';

        $r = isset($this->routes[$route])? $this->routes[$route] : [];
        $specialRoute = isset($r['domain']) || isset($r['subdomain']) || isset($r['port']) || isset($r['protocol']);

        // Domain / Canonical URL
        $protocol = (isset($r['protocol'])? $r['protocol'] : (HTTPS?'https':'http')) . '://';
        $domain = DOMAIN_NAME;
        if (!empty($r['domain'])) {
            if (is_string($r['domain'])) {
                $domain = $r['domain'];
            } else {
                if (in_array(DOMAIN_NAME, (array)$r['domain'])) {
                    $domain = $d;
                } else
                foreach ((array)$r['domain'] as $d) {
                    if (DEV && preg_match("#(^|\.)(".str_replace('.', "\\.", DEV_DOMAIN).")$#i", $d)) {
                        $domain = $d;
                        break;
                    } else
                    if (PROD && preg_match("#(^|\.)(".str_replace('.', "\\.", PROD_DOMAIN).")$#i", $d)) {
                        $domain = $d;
                        break;
                    }
                }
            }
        } else
        if (!empty($r['subdomain'])) {
            if (preg_match("#^(".str_replace('.', "\\.", implode('|', (array)$r['subdomain'])).")\.#i", DOMAIN_NAME)) {
                $domain = DOMAIN_NAME;
            } else
            foreach ((array)$r['subdomain'] as $d) {
                if (DEV && preg_match("#^(".str_replace('.', "\\.", "$d.([a-z0-9.-].)?".str_replace("|","|$d.([a-z0-9.-].)?",DEV_DOMAIN)).")$#i", "$d.".DOMAIN_NAME, $matches)) {
                    $domain = $matches[1];
                    break;
                } else
                if (PROD && preg_match("#^(".str_replace('.', "\\.", "$d.([a-z0-9.-].)?".str_replace("|","|$d.([a-z0-9.-].)?",PROD_DOMAIN)).")$#i", "$d.".DOMAIN_NAME, $matches)) {
                    $domain = $matches[1];
                    break;
                }
            }
            if (!preg_match("#^(".str_replace('.', "\\.", implode('|', (array)$r['subdomain'])).")\.#i", $domain)) {
                if (DEV && preg_match("#(^|\.)(".str_replace('.', "\\.", DEV_DOMAIN).")$#i", $domain)) {
                    $domain = ((array)$r['subdomain'])[0].'.'.explode('|', DEV_DOMAIN)[0];
                } else
                if (PROD && preg_match("#(^|\.)(".str_replace('.', "\\.", PROD_DOMAIN).")$#i", $domain)) {
                    $domain = ((array)$r['subdomain'])[0].'.'.explode('|', PROD_DOMAIN)[0];
                } else {
                    $domain = ((array)$r['subdomain'])[0].'.'.$domain;
                }
            }
        } else
        if (!in_array($domain, explode('|', PROD_DOMAIN.'|'.DEV_DOMAIN))) {
            $domain = explode('|', (DEV? DEV_DOMAIN : PROD_DOMAIN))[0];
        }
        $port = !empty($r['port']) ? (is_array($r['port']) ? ( in_array(PORT, $r['port'])? PORT : $r['port'][0] ) : $r['port']) : PORT;
        if ($port == 443 && $protocol == 'http://') $protocol = 'https://';
        $host_url = $protocol.$domain.($port!=80&&$port!=443 ? ":$port":'');
        if (!$canonical && ($host_url == HOST_URL || !$specialRoute)) {
            $host_url = "";
        }

        // FINAL URL
        return $host_url.$baseURL.$this->getRouteUrlFromRouteKey($route, $lang).$params.$queryString;
    }

    public function return404() {
        $this->returnCode(404);
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

    public function setRouteParams(array $routeParams) {
        $this->routeParams = $routeParams;
    }

    public function getRouteParam($index = 0, $default = null) {
        // if (!is_numeric($index) && !empty($this->routes[$this->route]['params']) && is_array($this->routes[$this->route]['params'])) {
        //     foreach ($this->routes[$this->route]['params'] as $i => $val) {
        //         if ($val == $index) {
        //             $index = $i;
        //             break;
        //         }
        //     }
        // }
        return isset($this->routeParams[$index]) ? $this->routeParams[$index] : $default;
    }

    public function returnCode($code) {
        if (!headers_sent()) http_response_code($code);
        if ($code >= 200 && $code < 300) return; // 2xx codes are considered success. All others must output an error view.
        $this->route = null;
        if (is_file(VIEW_PATH.$code.'.phtml')) {
            $this->setView($code);
            if ($this->executed) {
                $this->outputView();
                exit;
            }
        } else if (is_file(DOCUMENT_ROOT.$code.'.php')) {
            include DOCUMENT_ROOT.$code.'.php';
            exit;
        } else {
            exit;
        }
    }

    protected function parseRouteParamsString($str) {
        $r = $this->routes[$this->route];
        foreach ($this->routeParams as $key => $value) {
            $str = str_replace("%$key%", $value, $str);
        }
        return $str;
    }

    public function execute() {
        global $X, $X_LAYOUT, $X_PROJECT, $X_TITLE, $X_PAGETITLE, $X_VIEW_CONTENT, $X_VIEW_RETURN;

        // Allowed Methods
        if (!empty($this->routes[$this->route]['methods'])) {
            if (!in_array($this->method, $this->routes[$this->route]['methods'])) {
                $this->returnCode(400); // Bad Request
            }
        }

        // Handle request data for uncommon methods
        if (in_array(METHOD, ['PATCH', 'PUT', 'DELETE', 'HEAD']) && !empty($_SERVER["CONTENT_TYPE"]) && stripos($_SERVER["CONTENT_TYPE"], "urlencoded")) {
            parse_str(file_get_contents('php://input'), $_REQUEST);
        }

        // Custom View
        if (!empty($this->routes[$this->route]['view'])) {
            $this->setView($this->parseRouteParamsString($this->routes[$this->route]['view']));
        }

        // Custom Layout
        if (isset($this->routes[$this->route]['layout'])) {
            $X_LAYOUT = $this->parseRouteParamsString($this->routes[$this->route]['layout']);
        }

        // Route Callable Function
        if (!empty($this->routes[$this->route]['function'])) {
            call_user_func_array($this->routes[$this->route]['function'], $this->routeParams);
        }

        // Lang
        if (!defined('LANG')) {
            if (isset($this->routes[$this->route]['lang'])) $this->lang = $this->routes[$this->route]['lang'];
            if ((!is_string($this->lang) || !preg_match("#^(".LANGS.")$#i", $this->lang)) && is_callable($this->lang)) {
                $langFunc = $this->lang;
                $this->lang = $langFunc();
            }
            if (is_string($this->lang) && strlen($this->lang) && preg_match("#^(".LANGS.")$#i", $this->lang)) define('LANG', $this->lang);
        }

        // Title
        if (!isset($X_PAGETITLE) && !empty($this->routes[$this->route]['pagetitle'])) {
            $X_PAGETITLE = $this->routes[$this->route]['pagetitle'];
            if (is_array($X_PAGETITLE) && defined('LANG')) $X_PAGETITLE = isset($X_PAGETITLE[LANG])? $X_PAGETITLE[LANG] : $X_PAGETITLE[DEFAULT_LANG];
            $X_PAGETITLE = $this->parseRouteParamsString($X_PAGETITLE);
        }
        if (!isset($X_TITLE) && !empty($this->routes[$this->route]['title'])) {
            $X_TITLE = $this->routes[$this->route]['title'];
            if (is_array($X_TITLE) && defined('LANG')) $X_TITLE = isset($X_TITLE[LANG])? $X_TITLE[LANG] : $X_TITLE[DEFAULT_LANG];
            $X_TITLE = $this->parseRouteParamsString($X_TITLE);
        }

        // Validate View File exists
        if (!is_file($this->viewFile)) {
            $this->return404();
        }

        $this->executed = true;

        $this->outputView();
    }

    public function outputView() {
        global $X, $X_LAYOUT, $X_PROJECT, $X_TITLE, $X_PAGETITLE, $X_VIEW_CONTENT, $X_VIEW_RETURN;

        // Get View Content
        $X_VIEW_CONTENT = X_include_return($this->viewFile, false, false, $X_VIEW_RETURN);

        // Handle View Return Code
        if (!empty($X_VIEW_RETURN) && is_numeric($X_VIEW_RETURN) && $X_VIEW_RETURN >= 100 && $X_VIEW_RETURN < 600) {
            $this->returnCode($X_VIEW_RETURN);
        }

        // Page Title
        if (!isset($X_TITLE)) {
            if (!empty($X_PROJECT))
            $X_TITLE = $X_PROJECT . (!empty($X_PAGETITLE)?' - '.$X_PAGETITLE : '');
        }

        // Page Layout
        if (!empty($X_LAYOUT)) {
            X_include(LAYOUT_PATH.$X_LAYOUT.".phtml");
        } else {
            echo $X_VIEW_CONTENT;
        }
    }

    public function __toString() {
        return is_string($this->route) ? $this->route : "";
    }

}
