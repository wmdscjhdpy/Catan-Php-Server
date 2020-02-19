<?php
class test{
    public static $a=3;
    public static function go(){
        return test::$a;
    }
}
echo test::go();