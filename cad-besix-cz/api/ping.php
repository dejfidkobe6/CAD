<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode(['ok' => true, 'php' => PHP_VERSION, 'time' => time()]);
