<?php
$a=[1,2,3];
$b=[2,3,4];
$jk=array($a,$b);
var_dump($jk);
$it=&$jk;
$it=&$it[0];
var_dump($jk);
