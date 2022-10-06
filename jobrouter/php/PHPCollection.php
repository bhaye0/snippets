<?php

// get current DateTime
$date = new DateTime('NOW');
// get current DateTime as string
$date = (new DateTime('NOW'))->format('Y-m-d H:i:s');
// get current DateTime as timestamp
$date = (new DateTime('NOW'))->getTimestamp();
// convert string to DateTime
$date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
// convert DateTime to string
$str = (new DateTime($date))->format('Y-m-d H:i:s');

// explodes a string to an array
$ary = explode($separator, $str);
// implodes an array to a string
$str = implode($glue, $ary);

// replaces parts of a string
$str = str_replace($search, $replace, $subject);


?>