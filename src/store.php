<?php
// Settings and user lookups for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

const SETTINGS_TABLE = 'smoke_settings';

// Reads every setting; the table name is a compile-time constant.
function load_settings(PDO $db): array
{
    $rows = $db->query('SELECT name, value FROM ' . SETTINGS_TABLE);
    return $rows === false ? [] : $rows->fetchAll(PDO::FETCH_KEY_PAIR);
}
