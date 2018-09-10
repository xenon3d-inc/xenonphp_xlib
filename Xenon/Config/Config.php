<?php

namespace Xenon\Config;

class Config {
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