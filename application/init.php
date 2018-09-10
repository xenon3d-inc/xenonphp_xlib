<?php // XenonPHP Base Init file

// Define Domains
if (!defined('PROD_DOMAIN')) define('PROD_DOMAIN', $_SERVER['PROD_DOMAIN']);
if (!defined('DEV_DOMAIN')) define('DEV_DOMAIN', $_SERVER['DEV_DOMAIN']);

// Determine if PROD or DEV
if (!defined('PROD')) define('PROD', preg_match("#^(www\.)?(".str_replace('.', "\\.", PROD_DOMAIN).")$#i", $_SERVER['HTTP_HOST']));
if (!defined('DEV')) define('DEV', !PROD);

// Paths
if (!defined('REAL_DOCUMENT_ROOT')) define('REAL_DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/');
if (!defined('VIEW_PATH')) define('VIEW_PATH', APPLICATION_PATH . 'view/');
if (!defined('CONTROLLER_PATH')) define('CONTROLLER_PATH', APPLICATION_PATH . 'controller/');
if (!defined('MODEL_PATH')) define('MODEL_PATH', APPLICATION_PATH . 'model/');
if (!defined('LIB_PATH')) define('LIB_PATH', APPLICATION_PATH . 'lib/');
if (!defined('XLIB_PATH')) define('XLIB_PATH', APPLICATION_PATH . 'xlib/');
if (!defined('CACHE_PATH')) define('CACHE_PATH', APPLICATION_PATH . 'cache/');
if (!defined('DATA_PATH')) define('DATA_PATH', APPLICATION_PATH . 'data/');
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', APPLICATION_PATH . 'config/');
if (!defined('WIDGET_PATH')) define('WIDGET_PATH', APPLICATION_PATH . 'widget/');
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', APPLICATION_PATH . 'layout/');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', DOCUMENT_ROOT . 'uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', str_replace(DOCUMENT_ROOT, '/', UPLOAD_PATH));

// Languages
if (!defined('MULTILANG')) define('MULTILANG', ($_SERVER['MULTILANG'] == 'on' || $_SERVER['MULTILANG'] == '1' || $_SERVER['MULTILANG'] == 'true') ? true:false);
if (!defined('LANGS')) define('LANGS', $_SERVER['LANGS']);
if (!defined('DEFAULT_LANG')) define('DEFAULT_LANG', $_SERVER['DEFAULT_LANG']);

// Urls
if (!defined('BASE_URL')) define('BASE_URL', (($_BASE_URL=dirname($_SERVER['PHP_SELF']))=='/'?'':$_BASE_URL));
if (!defined('FULL_URL')) define('FULL_URL', $_SERVER['REQUEST_URI']);
if (!defined('URL')) define('URL', preg_replace("#^".preg_quote(BASE_URL, '#')."(.*)$#", "$1", FULL_URL));

// AJAX
if (!defined('AJAX')) define('AJAX',((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')));

///////////////////////////////////////////////////////////////////

// Display Errors only if DEV
ini_set('display_errors', DEV);
error_reporting(E_ALL);

// Include Helpers
if (!defined('DONT_INCLUDE_HELPERS')) {
    require_once XLIB_PATH . 'helpers/url.php';
    require_once XLIB_PATH . 'helpers/upload.php';
    require_once XLIB_PATH . 'helpers/i18n.php';
    require_once XLIB_PATH . 'helpers/simpleImageUpload.php';
    require_once XLIB_PATH . 'helpers/asset.php';
}

// Use Autoload
if (!defined('DONT_USE_AUTOLOAD')) {
    require_once XLIB_PATH . 'application/autoload.php';
    // Define Base Autoload Cache File (JSON Object).
    define('X_BASE_AUTOLOAD_CACHE_FILE', CACHE_PATH . 'autoload.json');
    // Register Autoload Function
    spl_autoload_register('X_BaseAutoload::__autoload', true, true);
}
