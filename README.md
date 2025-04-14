# appsec-review-smoke-php

A deliberately vulnerable **smoke target** for [appsec-review](https://github.com/wsollers/appsec-review).
It exists so that a review of a tiny, fully understood PHP program has one correct answer at every
stage. **Do not deploy, run against untrusted input, or reuse any of this code.**

The program is a toy request service: `public/index.php` handles one request and dispatches on
its `action` parameter; `src/synthetic.php` is the code-graph fixture program.

## License

MIT; see `LICENSE`.
