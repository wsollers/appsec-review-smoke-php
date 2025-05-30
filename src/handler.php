<?php
// Request handling for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

require_once __DIR__ . '/store.php';


$requests = 0;

// Handles one request and returns an HTTP-like status code.
function handle_request(PDO $db): int
{
    global $requests;
    $action = $_GET['action'] ?? 'ping';
    $status = 400;
    $body = '';
    if ($action === 'ping') {
        $body = 'pong';
        $status = 200;
    }
    if ($status >= 200 && $status < 300) {
        $requests++;
    }
    echo $body;
    return $status;
}
