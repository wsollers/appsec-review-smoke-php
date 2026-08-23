# Planted inventory

This repository is a smoke target for appsec-review. Everything below is deliberate. The
evaluation answer key lives in the separate `appsec-review-smoke-guide` repository; this file
describes the target, it is not the key.

Line numbers refer to the tagged head. The fixture file `src/synthetic.php` is hand-authored to the
pattern of the appsec-review `python-synthetic` and `javascript-synthetic` graph-export fixtures:
PHP has no CodeQL extractor, so its code-intelligence expectations (`expected-facts.json` in the
guide) describe what the Semgrep taint and Psalm dataflow producers are expected to report.

## True positives, code

| Id | Weakness | Where | Source to sink |
| --- | --- | --- | --- |
| TP-C1 | CWE-601 open redirect | `src/handler.php` `handle_request`, `redirect` action | `$_GET['return_to']` to `header('Location: ' . $target)` |
| TP-C2 | CWE-22 path traversal | `src/handler.php` `serve_template` | `$_GET['name']` to `file_get_contents(TEMPLATE_ROOT . '/' . $name)` via `template` |
| TP-C3 | CWE-611 XML external entity | `src/manifest.php` `parse_manifest` | `php://input` to `simplexml_load_string(..., LIBXML_NOENT)` via `import` |
| TP-C4 | CWE-117 log injection | `src/handler.php` `audit` | `$_GET['action']` and the `User-Agent` header to `file_put_contents(AUDIT_LOG, ...)` |
| TP-C5 | CWE-89 SQL injection | `src/synthetic.php` `show_user` to `run_query` | `$_GET['name']` to `$conn->query($query)` (fixture case `remote_to_sql`) |
| TP-C6 | CWE-78 command injection | `src/synthetic.php` `ping_host` | `$_GET['host']` to the unguarded `system('nslookup ' . $host)` (fixture case `remote_to_command`) |
| TP-C7 | CWE-918 server-side request forgery | `src/synthetic.php` `fetch_url` | `$_POST['url']` to `file_get_contents($url)` (fixture case `remote_to_ssrf`) |
| TP-C8 | CWE-502 unsafe deserialization | `src/synthetic.php` `load_blob` | `php://input` to `unserialize($blob)` (fixture case `remote_to_deserialization`) |

`main` in `src/synthetic.php` also carries an `$argv` source into `run_query` and a `getenv` source
into `system` (fixture cases `argv_source`, `environment_source`).

## True positives, dependencies

Locked in `composer.lock` and declared in `composer.json`; none is installed or required by any
source file, so the build (`php -l`) ignores them while SBOM tools that read the lockfile see them.

| Id | Package | Version | Advisory | Fixed in | How present |
| --- | --- | --- | --- | --- | --- |
| TP-D1 | guzzlehttp/guzzle | 7.4.0 | GHSA-cwmx-hcrq-mhc3 (CVE-2022-29248) | 7.4.3 | direct, `require` |
| TP-D2 | twig/twig | 3.3.0 | GHSA-5mv2-rx3q-4w2v (CVE-2022-23614) | 3.3.8 | direct, `require` |
| TP-D3 | guzzlehttp/psr7 | 2.1.0 | GHSA-q7rv-6hp3-vh96 (CVE-2022-24775) | 2.1.1 | transitive of guzzle 7.4.0, held at 2.1.0 by a `conflict` entry |

The brief named `monolog/monolog` 1.23.0 as the planted dependency; OSV has no record for it, so
`guzzlehttp/guzzle` 7.4.0 was substituted.

## Fixed in history

| Id | What | Introduced | Fixed |
| --- | --- | --- | --- |
| FX-C1 | CWE-78 command injection in `run_tool` (`shell_exec` with the request's tool name) | "Add a tool action that runs maintenance helpers" | "Fix command injection in run_tool (CWE-78)": the sink was removed |
| FX-C2 | CWE-22 path traversal in `read_asset` | "Add an asset action that serves files from the asset root" | "Fix path traversal in read_asset (CWE-22)": `basename` now dominates the read |
| FX-D1 | phpmailer/phpmailer 6.4.0 (GHSA-77mr-wc79-m8j3 / CVE-2021-3603, CVE-2021-34551) | "Pin the HTTP client, templating and mail libraries" | "Upgrade phpmailer/phpmailer from 6.4.0 to 6.5.0" |

`SECURITY.md` and `CHANGELOG.md` describe the three fixed items and nothing else.

## False positives

Each is flagged by a rule under appsec-review `rules/semgrep/review-signals.yml` and refuted by a
code-intelligence fact.

| Id | Where | Flagged by | Refuted by |
| --- | --- | --- | --- |
| FP-1 | `src/store.php` `load_settings`: `$db->query('SELECT name, value FROM ' . SETTINGS_TABLE)` | `appsec-review.review-signal.sink.php-sql-string-concatenation` | constant operand: `SETTINGS_TABLE` is a compile-time constant, so no `dataflow_path` reaches the sink |
| FP-2 | `src/store.php` `list_settings`: `$db->query('SELECT name FROM smoke_settings ORDER BY ' . $order)` | `appsec-review.review-signal.sink.php-sql-string-concatenation` | `guard_fact`: `in_array($order, SORTABLE, true)` dominates the sink (early return) and constrains its operand |
| FP-3 | `src/store.php` `search_users`: `$db->query('SELECT id, name FROM users WHERE name = ' . $db->quote($name))` | `appsec-review.review-signal.sink.php-sql-string-concatenation` | `path_sanitizers`: `PDO::quote` lies on the only path to the sink and dominates it |
| FP-4 | `src/legacy.php` `legacy_restore`: `unserialize($_GET['state'])` | `appsec-review.review-signal.sink.php-unserialize-request` | dead code: no callers and the file is never required, so no `dataflow_path` reaches it |
| FP-5 | `src/legacy.php` `legacy_probe`: `curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)` | `appsec-review.review-signal.privilege_shift.php-curl-verification-disabled` | dead code: no callers and the file is never required, so no `dataflow_path` reaches it |

## High-complexity function

`handle_request` in `src/handler.php` is the dispatch chain of the service and the function the
history touches most. It holds TP-C1. Its intended cyclomatic complexity is above 15 by the
appsec-review counter (branches, loops, boolean and null-coalescing operators and conditional
expressions); the measured value is recorded in the guide.

## Code-intelligence cases

`src/synthetic.php` carries, to the pattern of the appsec-review Python and JavaScript fixtures:
a remote source reaching an SQL, command, path, SSRF and deserialization sink; an `$argv` and a
`getenv` source; a sanitized path (`basename` before `file_get_contents` in `read_file`); a
dominating guard (`in_array($host, ALLOWED, true)` over the first `system` in `ping_host`) and the
unguarded second `system`; interface dispatch (`Prefixed::handle` to `Upper::run` and
`Lower::run`); unresolved indirect calls (`call_user_func` in `dispatch`, `$callback(...)` in
`invoke`); and a cross-function flow summary (`run_query` parameter 1 to the SQL sink). The fixed
state of `read_asset` adds a `basename` sanitizer; `list_settings` carries a dominating
`in_array` guard.

## History

Produced by `tools/make-history.sh` from `tools/history/steps/`: 34 commits by three
synthetic authors, dated 2025-04 to 2026-08, including the introduction and fix of each fixed
item, a dependency upgrade, a rename (`src/import.php` to `src/manifest.php`), a revert and its
reapply, a hardening review that adds `SECURITY.md`, and a commit body that records an
investigated report found not exploitable (FP-2). Every commit touches at most three files.
