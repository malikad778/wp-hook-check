# wp-hook-check

**Static analysis for WordPress hooks. Find broken hook connections before they reach production.**

[![Tests](https://github.com/malikad778/wp-hook-check/actions/workflows/tests.yml/badge.svg)](https://github.com/malikad778/wp-hook-check/actions)
[![Latest Version](https://img.shields.io/packagist/v/malikad778/wp-hook-check)](https://packagist.org/packages/malikad778/wp-hook-check)
[![PHP Version](https://img.shields.io/packagist/php-v/malikad778/wp-hook-check)](https://packagist.org/packages/malikad778/wp-hook-check)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

![Demo](demo.gif)

---

## The problem

WordPress hooks are silent. When `add_action('my_hook', $cb)` exists but `do_action('my_hook')` was renamed or removed, nothing throws. The callback just stops running. You find out in production when a feature breaks.

```php
// v1 - fires a hook before checkout
do_action( 'my_plugin_before_checkout', $order_id );

// Another plugin listens for it
add_action( 'my_plugin_before_checkout', 'apply_discount' );

// v2 - renamed without announcement
do_action( 'my_plugin_checkout_start', $order_id );
// apply_discount() never runs again. No error, no warning.
```

This tool parses every PHP file in a directory, maps all hook registrations and invocations, and reports mismatches. No WordPress install needed.

---

## Install

```bash
composer require --dev malikad778/wp-hook-check
```

PHP 8.2+.

---

## Usage

```bash
# Scan current directory
vendor/bin/wp-hook-audit audit .

# Scan a plugin
vendor/bin/wp-hook-audit audit ./wp-content/plugins/my-plugin

# Scan all plugins at once (hooks are cross-referenced across all files)
vendor/bin/wp-hook-audit audit ./wp-content/plugins
```

---

## What gets flagged

| Type | Severity | When |
|---|---|---|
| `ORPHANED_LISTENER` | 🔴 HIGH | `add_action/filter` exists, no matching `do_action/apply_filters` found |
| `UNHEARD_HOOK` | 🟡 MEDIUM | `do_action/apply_filters` fired, no listener registered anywhere |
| `HOOK_NAME_TYPO` | 🔴 HIGH | Hook name differs from another by 1–2 characters |
| `DYNAMIC_HOOK` | 🔵 INFO | Hook name is a variable - can't be resolved, skipped by other detectors |

---

## Output formats

### Table (default)

```
  WP HOOK AUDITOR  Scanned 47 files in 0.091s
  ──────────────────────────────────────────────────────────

  [HIGH] ORPHANED_LISTENER
  File  : includes/class-checkout.php:234
  Hook  : my_plugin_before_checkout

  add_action('my_plugin_before_checkout') registered (callback: apply_discount)
  - no matching do_action() or apply_filters() found.

  Fix: Either remove the add_action() call or add do_action('my_plugin_before_checkout')
  where it should fire.

  ──────────────────────────────────────────────────────────
  SUMMARY  1 HIGH   0 MEDIUM   0 INFO
```

### JSON (`--format=json`)

```json
{
  "meta": {
    "files_scanned": 47,
    "duration_sec": 0.091,
    "issue_count": 1
  },
  "issues": [
    {
      "type": "orphaned_listener",
      "severity": "high",
      "hook": "my_plugin_before_checkout",
      "file": "includes/class-checkout.php",
      "line": 234,
      "message": "...",
      "safe_alternative": "...",
      "suggestion": null
    }
  ]
}
```

### GitHub Annotations (`--format=github`)

```
::error file=includes/class-checkout.php,line=234,title=ORPHANED_LISTENER::...
::warning file=...,title=UNHEARD_HOOK::...
::notice file=...,title=DYNAMIC_HOOK::...
```

Issues show up as inline annotations on the exact lines in GitHub pull requests.

---

## CLI options

### `audit`

```bash
vendor/bin/wp-hook-audit audit [path] [options]
```

| Option | Default | Description |
|---|---|---|
| `--format` | `table` | `table`, `json`, or `github` |
| `--fail-on` | `high` | Exit 1 if issues at this level exist: `high`, `medium`, `any`, `none` |
| `--exclude` | - | Comma-separated paths to skip |
| `--ignore-dynamic` | - | Hide INFO dynamic hook notices |
| `--only` | all | Run only these detectors: `orphaned`, `unheard`, `typo`, `dynamic` |
| `--config` | `wp-hook-audit.json` | Path to config file |

### `dump`

```bash
vendor/bin/wp-hook-audit dump [path] [--format=table|json]
```

Dumps the full hook map - every `add_action`, `do_action`, `add_filter`, `apply_filters` call, with file, line, and priority. No detectors run. Good for exploring an unfamiliar codebase.

---

## Exit codes

| Code | Meaning |
|---|---|
| `0` | Clean (no issues above threshold) |
| `1` | Issues found at or above `--fail-on` level |
| `2` | Parse error, unreadable file, or bad config |

---

## Config file

Drop a `wp-hook-audit.json` in the directory you're scanning, or point to one with `--config`:

```json
{
    "exclude": ["vendor/", "tests/", "node_modules/"],
    "detectors": {
        "orphaned_listener": true,
        "unheard_hook": true,
        "typo": true,
        "dynamic_hook": false
    },
    "ignore": [
        { "type": "unheard_hook", "hook": "my_plugin_extensibility_point" }
    ],
    "external_prefixes": [
        "wp_", "admin_", "woocommerce_", "init", "shutdown"
    ]
}
```

### `external_prefixes`

WordPress core fires hundreds of hooks (`init`, `plugins_loaded`, `save_post`, etc.) that live inside WordPress itself, not your plugin. Without this setting, every `add_action('init', ...)` flags as `ORPHANED_LISTENER` because the matching `do_action('init')` is in WordPress core - outside the folder you're scanning.

The defaults already cover 40+ common WP core patterns. Add your own plugin's extensibility hooks here too if you're getting false positives from a third-party plugin you depend on.

See `wp-hook-audit.json.example` for the full default list.

---

## CI/CD

### GitHub Actions

```yaml
name: Hook Audit
on: [pull_request]

jobs:
  audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: composer require --dev malikad778/wp-hook-check
      - run: vendor/bin/wp-hook-audit audit . --format=github --fail-on=high
```

### GitLab CI

```yaml
hook_audit:
  script:
    - composer install
    - vendor/bin/wp-hook-audit audit . --format=json > hook-report.json
  artifacts:
    paths: [hook-report.json]
```

---

## How it works

Parses PHP files into an AST using `nikic/php-parser`, walks every function call node, and records any of the 10 tracked WordPress functions. Hook names are extracted from the first argument - string literals are captured as-is, variables and concatenations are marked dynamic and skipped by mismatch detectors. The result is a `HookMap` keyed by hook name, which the four detectors then query.

Parse errors are non-fatal. A file that fails to parse is skipped with a warning, scan continues.

---

## Tracked functions

| Function | Role |
|---|---|
| `add_action()`, `add_filter()` | Registration - checked for orphaned listeners |
| `do_action()`, `apply_filters()` | Invocation - checked for missing listeners |
| `do_action_ref_array()`, `apply_filters_ref_array()` | Invocation |
| `remove_action()`, `remove_filter()` | Tracked, never flagged |
| `has_action()`, `has_filter()` | Counts as invocation - stops false UNHEARD positives |

---

## Known gaps

- Dynamic hook names (variables, string concatenation) are skipped by all mismatch detectors
- Hooks registered inside conditionals are still tracked - may produce false positives if the condition never runs
- Closures show as `{closure}` in the hook map output
- Hooks from WordPress core or third-party plugins need their prefixes in `external_prefixes`

---

## License

MIT - see [LICENSE](LICENSE)
