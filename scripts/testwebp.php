<?php

require_once '../vendor/autoload.php'; // Assuming SVG.php is in the same directory

$values = [
    'Jan' => 0.1,
    'Feb' => 0.3,
    'Mar' => 0.5,
    'Apr' => 0.7,
    'May' => 0.9,
    'Jun' => 0.2,
    'Jul' => 0.4,
    'Aug' => 0.6,
    'Sep' => 0.8,
    'Oct' => 0.5,
    'Nov' => 0.7,
    'Dec' => 0.9,
];

RPurinton\modify2\WEBP::generateBarChart(1, 400, 225, $values);
