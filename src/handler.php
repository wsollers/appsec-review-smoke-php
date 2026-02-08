<?php
// Request handling for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/import.php';

const ASSET_ROOT = '/var/lib/smoke/assets';
const TEMPLATE_ROOT = '/var/lib/smoke/templates';
const AUDIT_LOG = '/var/log/smoke/audit.log';

$requests = 0;

// Runs one of the two maintenance helpers; any other name is refused.
function run_tool(string $name): bool
{
    global $requests;
    if ($name === 'status') {
        echo 'ok ' . $requests . "\n";
        return true;
    }
    if ($name === 'version') {
        echo 'smoke php ' . PHP_VERSION . "\n";
        return true;
    }
    return false;
}

// Reads an asset named by the request; the name is reduced to its final path component
// before it is joined onto the asset root, so it cannot leave that directory.
function read_asset(string $name): ?string
{
    $leaf = basename($name);
    $data = file_get_contents(ASSET_ROOT . '/' . $leaf);
    return $data === false ? null : $data;
}

// Serves a template named by the request; returns its bytes or null.
function serve_template(string $name): ?string
{
    $data = file_get_contents(TEMPLATE_ROOT . '/' . $name);
    return $data === false ? null : $data;
}

// Appends one line per request to the audit log.
function audit(string $action, string $detail): void
{
    file_put_contents(AUDIT_LOG, date('c') . ' ' . $action . ' ' . $detail . "\n", FILE_APPEND);
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
    } elseif ($action === 'template' && isset($_GET['name'])) {
        $body = serve_template($_GET['name']) ?? '';
        $status = $body === '' ? 404 : 200;
    } elseif ($action === 'redirect') {
        $target = $_GET['return_to'] ?? '/';
        header('Location: ' . $target);
        $status = 302;
    } elseif ($action === 'import' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $entries = parse_manifest((string) file_get_contents('php://input'));
        $body = $entries === null ? '' : (string) count($entries);
        $status = $entries === null ? 422 : 200;
    } elseif ($action === 'search' && isset($_GET['q'])) {
        $rows = search_users($db, $_GET['q']);
        $body = (string) count($rows);
        $status = $rows === [] ? 404 : 200;
    } elseif ($action === 'settings') {
        $body = implode(',', list_settings($db, $_GET['order'] ?? 'name'));
        $status = 200;
    } elseif ($action === 'status') {
        $body = 'smoke php ' . PHP_VERSION . ' settings ' . count(load_settings($db));
        $status = 200;
    }
    if ($status >= 200 && $status < 300) {
        $requests++;
    }
    echo $body;
    audit($action, $_SERVER['HTTP_USER_AGENT'] ?? '-');
    return $status;
}
