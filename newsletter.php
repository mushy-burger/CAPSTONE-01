<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
flashMessage('newsletter', 'Thanks for signing up.');
redirect(baseUrl('index.php'));
