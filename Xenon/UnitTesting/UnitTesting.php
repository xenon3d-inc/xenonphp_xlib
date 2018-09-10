<?php
namespace Xenon\UnitTesting;

abstract class UnitTesting {
    public static $nbTotal = 0;
    public static $nbPass = 0;
    public static $nbFail = 0;
    
    public abstract function __construct();
    
    public static function test($result, $expected) {
        $testfunc = debug_backtrace()[1];
        echo basename($testfunc['file'], '.php').".".$testfunc['function'] . " : ";
        self::$nbTotal++;
        if ($result === $expected) {
            echo "PASSED   <br>\n";
            self::$nbPass++;
            return true;
        }
        echo "FAILED   <br>\n";
        self::$nbFail++;
        return false;
    }
    
    public static function totals() {
        echo "\n<br><br>\n SUCCESS : ".self::$nbPass."/".self::$nbTotal;
        echo "\n<br><br>\n FAILED : ".self::$nbFail;
    }
    
}