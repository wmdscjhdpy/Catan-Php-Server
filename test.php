<?php
$a=array(3,4,5);
$c=array(5,4,3);

$b[0]=$a;
$b[1]=array(7,4,3);
echo in_array($c,$b);