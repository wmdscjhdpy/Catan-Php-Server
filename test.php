<?php
$a[0]=['x'=>1,'y'=>2];
$a[1]=['x'=>1,'y'=>3];
$b[0]=['x'=>1,'y'=>2];
$b[1]=['x'=>3,'y'=>1];
$c=false;
if(false && $a=null)
{
    echo 'ok ';
}
echo ($a);