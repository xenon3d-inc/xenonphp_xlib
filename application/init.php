<?php // XenonPHP Base Init file

// Domains defined in .htaccess, can accept multiple top-level domains separated by |
if (!defined('PROD_DOMAIN')) define('PROD_DOMAIN', $_SERVER['PROD_DOMAIN']);
if (!defined('DEV_DOMAIN')) define('DEV_DOMAIN', $_SERVER['DEV_DOMAIN']);
if (!defined('MAIN_PROD_DOMAIN')) define('MAIN_PROD_DOMAIN', explode('|', PROD_DOMAIN)[0]);
if (!defined('MAIN_DEV_DOMAIN')) define('MAIN_DEV_DOMAIN', explode('|', DEV_DOMAIN)[0]);

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
if (!defined('EMAIL_PATH')) define('EMAIL_PATH', APPLICATION_PATH . 'email/');
if (!defined('LAYOUT_PATH')) define('LAYOUT_PATH', APPLICATION_PATH . 'layout/');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', DOCUMENT_ROOT . 'uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', str_replace(DOCUMENT_ROOT, '/', UPLOAD_PATH));

// Languages
if (!defined('MULTILANG')) define('MULTILANG', ($_SERVER['MULTILANG'] == 'on' || $_SERVER['MULTILANG'] == '1' || $_SERVER['MULTILANG'] == 'true') ? true:false);
if (!defined('LANGS')) define('LANGS', $_SERVER['LANGS']);
if (!defined('DEFAULT_LANG')) define('DEFAULT_LANG', $_SERVER['DEFAULT_LANG']);

// Urls
if (!defined('METHOD')) define('METHOD', $_SERVER['REQUEST_METHOD']);
if (!defined('CONSOLE')) define('CONSOLE', METHOD==='CONSOLE');
if (!defined('BASE_URL')) define('BASE_URL', (($_BASE_URL=dirname($_SERVER['PHP_SELF']))=='/'?'':($_BASE_URL==='.'?'':$_BASE_URL)));
if (!defined('URL')) define('URL', $_SERVER['REQUEST_URI']);
if (!defined('HTTPS')) define('HTTPS', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443));
if (!defined('DOMAIN_NAME')) define('DOMAIN_NAME', preg_replace("#:\d+$#", "", $_SERVER['HTTP_HOST']));
if (!defined('ROOT_DOMAIN_NAME')) define('ROOT_DOMAIN_NAME', preg_replace("#^(.*\.)?([\w-]+\.[\w-]+)$#", "$2", DOMAIN_NAME));
if (!defined('PROTOCOL')) define('PROTOCOL', HTTPS ? "https://" : "http://");
if (!defined('PORT')) define('PORT', $_SERVER['SERVER_PORT']);
if (!defined('HOST_URL')) define('HOST_URL', PROTOCOL.DOMAIN_NAME.(PORT!=80&&PORT!=443 ? ':'.PORT : ''));
if (!defined('FULL_URL')) define('FULL_URL', HOST_URL.URL);
if (!defined('ROUTE_URL')) define('ROUTE_URL', preg_replace("#^".preg_quote(BASE_URL, '#')."(.*)$#", "$1", URL));
if (!defined('ADMIN_URL_COMPONENT')) define('ADMIN_URL_COMPONENT', 'admin');

// Image processing
if (!defined('IMAGE_PROCESSING_PLACEHOLDER_URL')) define('IMAGE_PROCESSING_PLACEHOLDER_URL', '//placehold.it/'); // '//placehold.it/' OR '/images/placeholder.png?size='

// AJAX
if (!defined('AJAX')) define('AJAX',((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')));
if (!defined('PJAX')) define('PJAX',(!empty($_SERVER['HTTP_X_PJAX'])));

// Global Database Settings
if (!defined('DB_AUTO_UPDATE_STRUCTURE')) define('DB_AUTO_UPDATE_STRUCTURE', true);
if (!defined('DB_UPDATES_CACHE_DIRECTORY')) define('DB_UPDATES_CACHE_DIRECTORY', CACHE_PATH . 'database_schema_updates');

// Asset Minification and Caching
if (!defined('ASSETS_MINIFY_AND_CACHE_PATH')) define('ASSETS_MINIFY_AND_CACHE_PATH', CACHE_PATH . 'minified_assets/');

// Force DEV/PROD if there is an existing DEV or PROD file in the application directory
if (!defined('PROD') && @is_file(APPLICATION_PATH.'PROD')) {define('PROD', true);define('DEV', false);}
if (!defined('DEV') && @is_file(APPLICATION_PATH.'DEV')) {define('DEV', true);define('PROD', false);}

// Determine if PROD or DEV (both can be true, both can be false... that is an accepted behaviour because when using these we may want to exclusively check a stage)
if (!defined('DEV')) define('DEV', preg_match("#(^|\.)(".str_replace('.', "\\.", DEV_DOMAIN).")$#i", DOMAIN_NAME));
if (!defined('PROD')) define('PROD', preg_match("#(^|\.)(".str_replace('.', "\\.", PROD_DOMAIN).")$#i", DOMAIN_NAME));

// Force use of defined domains (redirect to PROD)
if (!defined('USE_FLEXIBLE_DOMAIN_NAME') && !PROD && !DEV) header('Location: https://'.MAIN_PROD_DOMAIN);

///////////////////////////////////////////////////////////////////

// Error Handling
if (!defined('SHUTDOWN_FUNCTION')) {
    define('SHUTDOWN_FUNCTION', 'X_shutdown_function');
    require_once XLIB_PATH . 'application/shutdown_function.php';
}
if (!defined('ERROR_HANDLER')) {
    define('ERROR_HANDLER', 'X_error_handler');
    require_once XLIB_PATH . 'application/error_handler.php';
}
if (!defined('OUTPUT_HANDLER')) {
    define('OUTPUT_HANDLER', 'X_output_handler');
    require_once XLIB_PATH . 'application/output_handler.php';
}
if (!defined('DISPLAY_ERRORS')) define('DISPLAY_ERRORS', DEV && !PROD);
if (!defined('ERROR_REPORTING_FLAG')) define('ERROR_REPORTING_FLAG', E_ALL);
if (ERROR_HANDLER) set_error_handler(ERROR_HANDLER);
if (SHUTDOWN_FUNCTION) register_shutdown_function(SHUTDOWN_FUNCTION);
ini_set('display_errors', DISPLAY_ERRORS);
error_reporting(ERROR_REPORTING_FLAG);

///////////////////////////////////////////////////////////////////

// Include Helpers
if (!defined('DONT_INCLUDE_HELPERS')) {
    require_once XLIB_PATH . 'helpers/include.php';
    require_once XLIB_PATH . 'helpers/url.php';
    require_once XLIB_PATH . 'helpers/upload.php';
    require_once XLIB_PATH . 'helpers/i18n.php';
    require_once XLIB_PATH . 'helpers/simpleImageUpload.php';
    require_once XLIB_PATH . 'helpers/asset.php';
    require_once XLIB_PATH . 'helpers/reCaptcha.php';
    require_once XLIB_PATH . 'helpers/sendEmail.php';
}

// Use Autoload
if (!defined('DONT_USE_AUTOLOAD')) {
    require_once XLIB_PATH . 'application/autoload.php';
    // Define Base Autoload Cache File (JSON Object).
    define('X_BASE_AUTOLOAD_CACHE_FILE', CACHE_PATH . 'autoload.json');
    // Register Autoload Function
    spl_autoload_register('X_BaseAutoload::__autoload', true, true);
}
