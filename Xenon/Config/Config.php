<?php

namespace Xenon\Config;

class Config implements \ArrayAccess {
    private $globalconfig = [];
    private $path = "";

    public function __construct($path) {
        if (!is_dir($path)) trigger_error("Config Path Not Found", E_USER_ERROR);
        $this->path = $path;
    }

    public function findAndInclude($path, $filename) {
        global $X, $X_PROJECT, $X_ERROR, $X_CONFIG, $X_CHARSET, $X_ROUTE, $X_DB, $X_LAYOUT, $X_TITLE, $X_PAGETITLE, $X_VIEW_CONTENT, $X_VIEW_RETURN, $X_USER, $X_CONTROLLER, $X_VARS, $X_EMAIL_TEMPLATE;
        if (isset($this->globalconfig[$filename])) 
            return true;
        if (is_file($path.$filename.".php")) {
                $this->globalconfig[$filename] = include($path.$filename.".php");
            return true;
        }
        if (($glob = glob("$path*", GLOB_MARK | GLOB_ONLYDIR | GLOB_NOSORT))) {
            foreach ($glob as $dir) if (!preg_match("#^\.#", basename($dir))) {
                if ($this->findAndInclude($dir, $filename)) 
                    return true;
            }
        }
        return false;
    }

    // ArrayAccess stuff...
    public function offsetGet($filename) {
        if (!isset($this->globalconfig[$filename])) 
            if (!$this->findAndInclude($this->path, $filename))
                return null;
        return @$this->globalconfig[$filename];
    }
    public function offsetSet($filename, $value) {
        $this->globalconfig[$filename] = $value;
    }
    public function offsetExists($filename) {
        if (!isset($this->globalconfig[$filename])) 
            return $this->findAndInclude($this->path, $filename);
        return true;
    }
    public function offsetUnset($filename) {
        if (isset($this->globalconfig[$filename])) 
            unset($this->globalconfig[$filename]);
    }



    ///////////////////////////////////////////////////////////////////////////////
    // This method is deprecated since 0.8.0... we should simply create an instance instead
    public static function getAll($path) {
        $arr = [];
        if (!is_dir($path)) return $arr;
        $glob = glob($path."*", GLOB_MARK);
        if ($glob) {
            foreach ($glob as $file) {
                if (is_dir($file)) {
                    if (!preg_match("#^\.#", basename($file))) {
                        $arr += self::getAll($file);
                    }
                } else {
                    if (is_file($file) && preg_match("#.+\.php$#", $file)) {
                        $arr += [basename($file, ".php") => include($file)];
                    }
                }
            }
        }
        return $arr;
    }
}
