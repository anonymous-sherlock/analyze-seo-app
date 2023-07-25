<?php
// Title
$title = '';
$titleNode = $xpath->query('/html/head/title')->item(0);
if ($titleNode) {
  $title = $titleNode->nodeValue;
}