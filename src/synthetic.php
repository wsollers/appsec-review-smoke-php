<?php
// Synthetic PHP service for the appsec-review graph-export fixture. Hand-authored: PHP has no
// CodeQL extractor, so the dataflow producers for this file are Semgrep taint and Psalm.
declare(strict_types=1);

const ALLOWED = ['alpha', 'beta'];
const FILE_ROOT = '/srv/files';

function run_query(PDO $conn, string $query): array
{
    $statement = $conn->query($query);
    return $statement->fetchAll();
}

function show_user(PDO $conn): string
{
    $name = $_GET['name'];
    $rows = run_query($conn, "SELECT * FROM users WHERE name = '" . $name . "'");
    return (string) count($rows);
}

function ping_host(): string
{
    $host = $_GET['host'];
    if (in_array($host, ALLOWED, true)) {
        system('ping -c 1 ' . $host);
    }
    system('nslookup ' . $host);
    return 'ok';
}

function read_file(): string
{
    $safe = basename($_GET['file']);
    file_get_contents(FILE_ROOT . '/' . $safe);
    return 'read';
}

function fetch_url(): string
{
    $url = $_POST['url'];
    file_get_contents($url);
    return 'fetched';
}

function load_blob(): string
{
    $blob = file_get_contents('php://input');
    unserialize($blob);
    return 'loaded';
}

interface Handler
{
    public function run(string $value): string;
}

abstract class Prefixed implements Handler
{
    public function __construct(protected string $prefix)
    {
    }

    public function handle(string $value): string
    {
        return $this->run($value);
    }
}

final class Upper extends Prefixed
{
    public function run(string $value): string
    {
        return $this->prefix . strtoupper($value);
    }
}

final class Lower extends Prefixed
{
    public function run(string $value): string
    {
        return $this->prefix . strtolower($value);
    }
}

function dispatch(Prefixed $handler, string $action, string $value): string
{
    return call_user_func([$handler, $action], $value);
}

function invoke(callable $callback, string $value): string
{
    return $callback($value);
}

function main(array $argv): void
{
    $conn = new PDO('sqlite::memory:');
    run_query($conn, 'SELECT * FROM t WHERE id = ' . $argv[1]);
    run_query($conn, "SELECT * FROM t WHERE pw = '" . readline('password: ') . "'");
    system(getenv('APP_HOOK'));
    dispatch(new Upper('>'), 'handle', $argv[2]);
    invoke('strtoupper', $argv[3]);
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    main($argv);
}
