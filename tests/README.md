# OmniGEO AI SEO: tests and tools

These files live outside the plugin folder and are not part of the release ZIP.

```bash
php tests/run.php      # logic tests (Persian text handling, answer analysis, rules, robots, tracker, visibility, settings)
php tests/render.php   # renders every admin page with sample data and fails on any PHP notice/warning
php tools/make-pot.php # regenerate languages/omnigeo-ai-seo.pot
php tools/build-po.php # rebuild fa_IR .po/.mo from tools/fa_IR.tsv
```

The tests use small WordPress stand-ins (`tests/bootstrap.php`), so they run with plain PHP 7.2+ and need no database.
They do not replace testing in a real WordPress install (see the release checklist in the pull request / commit message).
