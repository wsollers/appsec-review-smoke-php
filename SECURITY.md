# Security notes

This repository is a deliberately vulnerable smoke target. It is never deployed, so there is no
disclosure process. The entries below record the issues that were fixed in this history, so that
the record of what changed and why is in one place.

## 2026-02-08: path traversal in `read_asset` (CWE-22)

The `asset` action joined the client-supplied name onto the asset root and read the result, so a
name containing `../` read files outside the asset directory. `read_asset` now reduces the name to
its final path component with `basename()` before joining it, so the read cannot leave the root.

## 2026-01-27: command injection in `run_tool` (CWE-78)

The `tool` action built a shell command from the client-supplied tool name and ran it with
`shell_exec()`, so shell metacharacters in the name ran arbitrary commands. `run_tool` now accepts
only the names `status` and `version` and performs them directly; there is no command string.

## 2026-01-10: phpmailer/phpmailer 6.4.0 (GHSA-77mr-wc79-m8j3, CVE-2021-3603)

PHPMailer 6.4.0 could load untrusted code through the language-file path (CVE-2021-3603) and
through an attacker-supplied validator callable (CVE-2021-34551). The lockfile now pins 6.5.0,
which fixes both.
