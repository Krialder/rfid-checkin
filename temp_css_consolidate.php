<?php
require_once 'core/AssetConsolidator.php';
$consolidator = AssetConsolidator::getInstance();
$result = $consolidator->generateConsolidatedCSS();
echo $result ? 'CSS consolidated successfully' : 'CSS consolidation failed';
echo "\n";
