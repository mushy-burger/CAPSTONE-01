<?php
require_once __DIR__ . '/../includes/functions.php';

return [
    'api_key' => envValue('GEMINI_API_KEY', ''),
    'model' => envValue('GEMINI_MODEL', 'gemini-2.5-flash'),
];
