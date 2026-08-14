---
name: code-review
description: Multi-perspective code review with confidence scoring. Use when asked to "review this PR", "code review", "review my changes", or "check this code". Launches parallel analysis for project standards, bugs, historical context, and code comments, then filters by confidence score (>=80).
tools: Read, Grep, Glob, Bash
---

# Code Review

Automated code review using multi-perspective analysis with confidence-based scoring to filter false positives.

## Process

### 1. Pre-check
Skip review if the PR is closed, draft, trivial/automated, or already reviewed.

### 2. Gather Context
Collect project guidelines from CLAUDE.md, AGENTS.md, .cursorrules, and any CLAUDE.md files in modified directories.

### 3. Summarize Changes
Create a brief summary of what the PR changes.

### 4. Multi-Perspective Review

**A - Project Standards Compliance:**
- WordPress Coding Standards
- `defined('ABSPATH')` guards in PHP files
- Nonce verification + capability checks in AJAX handlers
- Input sanitization: `sanitize_text_field(wp_unslash(...))`
- Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- Strict comparisons for auth checks
- `WC()->session` / `WC()->customer` null checks
- Distance data guards: `isset()` + `is_object()` before `->value`
- JS: `.textContent` not `.innerHTML`, guard globals, guard `response.data`

**B - Bug Detection:**
Scan for obvious bugs in changes only (not pre-existing):
- Fatal errors (null dereference, undefined access)
- Security vulnerabilities (XSS, SQL injection, CSRF bypass)
- Logic errors (wrong operator, off-by-one, missing return)

**C - Historical Context:**
Check git blame/history of modified code.

**D - Code Comments:**
Ensure changes comply with guidance in code comments.

### 5. Confidence Scoring (0-100)
- **0**: False positive
- **25**: Might be real, unable to verify
- **50**: Real but minor/nitpick
- **75**: Very likely real, important
- **100**: Definitely real, confirmed

### 6. Filter
Only report issues scoring **80 or above**.

### 7. False Positives (do NOT flag)
- Pre-existing issues
- Issues linters/typecheckers catch
- Pedantic nitpicks
- General quality issues unless in project guidelines
- Intentional functionality changes

### 8. Output
```
## Code Review - Found N issues:

1. [Description] (reason)
   File: path/to/file, Lines: X-Y

2. ...
```

If no issues >=80: "No issues found."
