<?php

$count = @file_get_contents('counter.txt');
$count++;

@file_put_contents('counter.txt',$count);

$version = @file_get_contents('version.txt');
header("Location: freedom-$version.apk");
//print "<h1>You are downloading <u>Freedom v.$version</u>. Please wait 3 seconds. Your download was <u><i>$count</i></u> in our list. Thanks.</h1>";
?>
