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

        // Decode class path
        $relativeClassPath = str_replace('\\', '/', $className) . '.php';
        $vendor = preg_match("#^/?(\w+)/.+$#", $relativeClassPath, $matches)? $matches[1] : null;
        // If no cache or class not found in cache, search for the file by path priority
        $potentialClassPaths = array(
            MODEL_PATH . $relativeClassPath,
            LIB_PATH . $relativeClassPath,
            XLIB_PATH.'External/' . $relativeClassPath,
        );
        // Vendors
        if ($vendor) {
            array_unshift($potentialClassPaths, 
                LIB_PATH.'vendor/' . $relativeClassPath,
                LIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/src/", $relativeClassPath),
                LIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/src/$vendor/", $relativeClassPath),
                LIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/lib/", $relativeClassPath),
                LIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/lib/$vendor/", $relativeClassPath),
                XLIB_PATH . $relativeClassPath,
                XLIB_PATH.'vendor/' . $relativeClassPath,
                XLIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/src/", $relativeClassPath),
                XLIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/src/$vendor/", $relativeClassPath),
                XLIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/lib/", $relativeClassPath),
                XLIB_PATH.'vendor/' . preg_replace("#^/?($vendor/)+#", "$vendor/lib/$vendor/", $relativeClassPath),
            );
        }
        // Controllers
        if (preg_match("#\w+Controller$#", $className)) {
            array_unshift($potentialClassPaths, CONTROLLER_PATH . $relativeClassPath);
        }
        // Find first existing class file
        foreach ($potentialClassPaths as $classPath) {
            $classPath = str_replace('//', '/', $classPath);
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
