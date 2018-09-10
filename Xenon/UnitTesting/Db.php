<?php
namespace Xenon\UnitTesting;

class Db extends UnitTesting {
    
    public function __construct() {
        self::test1();
        self::test2();
        self::test3();
        self::test4();
        
        self::totals();
    }
    
    public static function test1() { // Simple insert
        $result = "";
        
        $test = new \Test(['text' => 'texte 1', 'encrypted_text' => "Texte encrypté"]);
        $test->save();
        
        $result = $test != null ? $test->text . $test->encrypted_text : '';
        
        $test->delete();
        
        return self::test($result, "texte 1Texte encrypté");
    }
    
    public static function test2() { // Insert and fetch data - confirming the data
        $result = "";
        
        $test = new \Test(['text' => 'texte 1', 'encrypted_text' => "Texte encrypté"]);
        $test->save();
        
        $test = \Test::select()->fetchRow();
        
        $result = $test != null ? $test->text . $test->encrypted_text : '';
        
        $test->delete();
        
        return self::test($result, "texte 1Texte encrypté");
    }
    
    public static function test3() { // Insert and concatenate
        $result = "";
        
        $test = new \Test(['text' => 'texte 1', 'encrypted_text' => "Texte encrypté"]);
        $test->encrypted_text .= ' 1';
        $test->save();
        
        $test = \Test::select()->fetchRow();
        
        $result = $test != null ? $test->text . $test->encrypted_text : '';
        
        $test->delete();
        
        return self::test($result, "texte 1Texte encrypté 1");
    }
    
    public static function test4() { // Insert then update
        $result = "";
        
        $test = new \Test(['text' => 'texte 1', 'encrypted_text' => "Texte encrypté"]);
        $test->encrypted_text .= ' 1';
        $test->save();
        
        $test = \Test::select()->fetchRow();
        
        $result = $test != null ? $test->text . $test->encrypted_text : '';
        
        $test->encrypted_text = "Hello !";
        $test->save();
        $result .= $test->encrypted_text;
        
        $test->delete();
        
        return self::test($result, "texte 1Texte encrypté 1Hello !");
    }
    
}