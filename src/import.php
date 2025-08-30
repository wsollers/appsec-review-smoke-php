<?php
// Manifest import for the smoke service. This repository is a deliberately vulnerable smoke target for
// appsec-review; see README.md.
declare(strict_types=1);

// Parses the XML manifest the client posts and returns the entry names.
function parse_manifest(string $xml): ?array
{
    $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOENT);
    if ($document === false) {
        return null;
    }
    $entries = [];
    foreach ($document->entry as $entry) {
        $entries[] = (string) $entry['name'];
    }
    return $entries;
}
