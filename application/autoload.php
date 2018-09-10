<?php // XenonPHP Autoload Class v0.2

class X_BaseAutoload
{
    public static $cachedAutoloadClasses = array();

    public static $AUTOLOAD_CACHE_FILE = X_BASE_AUTOLOAD_CACHE_FILE;

    public static function __autoload($className)
    {
        // Check for override
        if (defined('X_AUTOLOAD_CACHE_FILE')) {
            self::$AUTOLOAD_CACHE_FILE = X_AUTOLOAD_CACHE_FILE;
        }

        // If Cache file exists, use cache
        if (self::$AUTOLOAD_CACHE_FILE) {
            if (empty(self::$cachedAutoloadClasses) && is_file(self::$AUTOLOAD_CACHE_FILE)) {
                // If Autoload Cache array is empty and cache file exists, fill it from cache file
                self::$cachedAutoloadClasses = json_decode(file_get_contents(self::$AUTOLOAD_CACHE_FILE), true);
            }
            if (!empty(self::$cachedAutoloadClasses) && array_key_exists($className, self::$cachedAutoloadClasses)) {
                require_once self::$cachedAutoloadClasses[$className];
                return;
            }
        }

        // If no cache or class not found in cache, search for the file by path priority
        $include_paths = array(
            XLIB_PATH,
            MODEL_PATH,
            CONTROLLER_PATH,
            LIB_PATH,
        );
        foreach ($include_paths as $path) {
            $classPath = $path . str_replace('\\', '/', $className) . '.php';
            if (is_file($classPath)) {
                // Class File found, include it and stop the search
                require_once $classPath;

                // If Cache file is enabled, set found class path in cache file as JSON Object
                if (self::$AUTOLOAD_CACHE_FILE) {
                    self::$cachedAutoloadClasses = array();
                    if (is_file(self::$AUTOLOAD_CACHE_FILE)) {
                        self::$cachedAutoloadClasses = json_decode(file_get_contents(self::$AUTOLOAD_CACHE_FILE), true);
                    }
                    self::$cachedAutoloadClasses[$className] = $classPath;
                    if ((is_file(self::$AUTOLOAD_CACHE_FILE) && is_writable(self::$AUTOLOAD_CACHE_FILE)) || is_writable(dirname(self::$AUTOLOAD_CACHE_FILE))) {
                        file_put_contents(self::$AUTOLOAD_CACHE_FILE, json_encode(self::$cachedAutoloadClasses));
                    }
                }
                return;
            }
        }
    }

}
