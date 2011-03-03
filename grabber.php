<?php
require_once dirname(__FILE__) .'/Grabber.class.php';
require_once dirname(__FILE__) .'/config.inc.php';

$grabber = new IMAPGrabber($config);
$count = (int) $grabber->grabIt();

echo 'found messages: ', $count, PHP_EOL;
