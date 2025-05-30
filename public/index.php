<?php
// Entry point of the smoke service: one request per invocation. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

require_once __DIR__ . '/../src/handler.php';

$db = new PDO('sqlite:' . __DIR__ . '/../var/smoke.db');
http_response_code(handle_request($db));
