<?php
// Request handling for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

require_once __DIR__ . '/store.php';

const ASSET_ROOT = '/var/lib/smoke/assets';

$requests = 0;

// Runs a maintenance helper named by the request.
function run_tool(string $name): bool
{
    $output = shell_exec('/usr/local/libexec/smoke/' . $name);
    return $output !== null;
}

// Reads an asset named by the request; returns its bytes or null.
function read_asset(string $name): ?string
{
    $data = file_get_contents(ASSET_ROOT . '/' . $name);
    return $data === false ? null : $data;
}

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
    } elseif ($action === 'tool' && isset($_GET['name'])) {
        $status = run_tool($_GET['name']) ? 200 : 500;
    } elseif ($action === 'asset' && isset($_GET['name'])) {
        $body = read_asset($_GET['name']) ?? '';
        $status = $body === '' ? 404 : 200;
    }
    if ($status >= 200 && $status < 300) {
        $requests++;
    }
    echo $body;
    return $status;
}
