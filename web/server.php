<?php

/**
 * ThinkPHP-Upgrade Built-in Web Server Router
 *
 * Usage: php -S 0.0.0.0:8190 -t web/ web/server.php
 * Env:   PHPLIFT_PROJECT=/path/to/project PHPLIFT_TARGET=8.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API routes
if (str_starts_with($uri, '/api/')) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $handler = new \ThinkUpgrade\Web\ApiHandler(
        projectPath: getenv('PHPLIFT_PROJECT') ?: '',
        targetVersion: getenv('PHPLIFT_TARGET') ?: '8.0',
    );

    echo $handler->handle($uri, $_SERVER['REQUEST_METHOD']);
    exit;
}

// SPA: serve index.html for non-file paths
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false; // Let built-in server handle static files
}

// Serve SPA index
readfile(__DIR__ . '/index.html');
