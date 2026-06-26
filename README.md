# appsec-review-smoke-php

A deliberately vulnerable **smoke target** for [appsec-review](https://github.com/wsollers/appsec-review).
It exists so that a review of a tiny, fully understood PHP program has one correct answer at every
stage. **Do not deploy, run against untrusted input, or reuse any of this code.**

The program is a toy request service: `public/index.php` handles one request and dispatches on
its `action` parameter; `src/synthetic.php` is the code-graph fixture program.

## Build

One Composer project, no network, no generated code. The build is a syntax check of every source
file; the dependencies in `composer.lock` are never installed:

```sh
for f in $(find public src -name "*.php"); do php -l "$f" || exit 1; done
```

The check is expected to succeed in the appsec-review `php` build image (PHP 8.3.6, Composer 2.7.1)
as an unprivileged user with networking disabled.

## History

`tools/make-history.sh` rebuilds this repository's commit graph from `tools/history/steps/` with
fixed authors and dates, so the history is reproducible and can be reviewed as code.

## License

MIT; see `LICENSE`.
