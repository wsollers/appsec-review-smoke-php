<?php
// Routines kept for the previous client; nothing in the service calls them. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

// Restores the saved view state the old client sent as a query parameter.
function legacy_restore(): array
{
    $state = unserialize($_GET['state']);
    return is_array($state) ? $state : [];
}

// Probes the old upstream API, which served a self-signed certificate.
function legacy_probe(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $ok = curl_exec($ch) !== false;
    curl_close($ch);
    return $ok;
}
