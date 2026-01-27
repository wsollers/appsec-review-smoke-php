# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Smoke service with ping, tool and asset actions.
- Composer manifest pinning the HTTP client, templating and mail libraries.

### Security

- 2026-01-10: Upgraded phpmailer/phpmailer from 6.4.0 to 6.5.0 (GHSA-77mr-wc79-m8j3 / CVE-2021-3603, untrusted code loaded by the language-file path, and CVE-2021-34551).
- 2026-01-27: Fixed command injection in `run_tool` (CWE-78); tool names are now matched against a fixed list.
