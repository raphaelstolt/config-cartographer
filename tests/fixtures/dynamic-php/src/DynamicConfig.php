<?php

declare(strict_types=1);

// Dynamic PHP access patterns - tests handling of dynamic variable names
$name = 'DATABASE_URL';
$value = getenv($name); // Dynamic access - should be marked as unresolved

$key = $_ENV['APP_SECRET']; // Static access - should be found
$dynamic = $_ENV[$name]; // Dynamic access - should be marked as unresolved
