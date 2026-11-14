<?php

declare(strict_types=1);

// Simple PHP file with environment access patterns
$host = getenv('DB_HOST');
$port = $_ENV['DB_PORT'];
$debug = $_SERVER['APP_DEBUG'];
