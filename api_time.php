<?php
require_once 'settings.php';

header('Content-Type: application/json');

echo json_encode([
    'time' => date('H:i:s'),
    'date' => date('Y-m-d'),
    'datetime' => date('Y-m-d H:i:s'),
    'timezone' => getSetting('timezone', 'Asia/Jakarta')
]);
