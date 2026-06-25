<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
logoutUser();
redirect(baseUrl('index.php'));
