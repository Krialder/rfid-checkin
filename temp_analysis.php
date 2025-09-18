<?php
require_once 'core/AssetConsolidator.php';
$consolidator = AssetConsolidator::getInstance();
$report = $consolidator->generateOptimizationReport();
echo json_encode($report, JSON_PRETTY_PRINT);
