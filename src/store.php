<?php
// Settings and user lookups for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

const SETTINGS_TABLE = 'smoke_settings';
const SORTABLE = ['name', 'updated_at'];

// Reads every setting; the table name is a compile-time constant.
function load_settings(PDO $db): array
{
    $rows = $db->query('SELECT name, value FROM ' . SETTINGS_TABLE);
    return $rows === false ? [] : $rows->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Lists setting names in the requested order; only the two indexed columns may be named.
function list_settings(PDO $db, string $order): array
{
    if (!in_array($order, SORTABLE, true)) {
        return [];
    }
    $rows = $db->query('SELECT name FROM smoke_settings ORDER BY ' . $order);
    return $rows === false ? [] : $rows->fetchAll(PDO::FETCH_COLUMN);
}

// Finds users by name; the driver quotes the name before it enters the statement.
function search_users(PDO $db, string $name): array
{
    $rows = $db->query('SELECT id, name FROM users WHERE name = ' . $db->quote($name));
    return $rows === false ? [] : $rows->fetchAll(PDO::FETCH_ASSOC);
}
