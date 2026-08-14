---
name: claude-md-management
description: Audit and update CLAUDE.md project memory files. Use when asked to "audit CLAUDE.md", "update project memory", "revise CLAUDE.md", or "capture session learnings". Scans for all CLAUDE.md files, evaluates quality, outputs report, then makes targeted updates with approval.
tools: Read, Glob, Grep, Bash, Edit
---

# CLAUDE.md Management

Audit, evaluate, and improve project memory files to ensure AI assistants have optimal project context.

## Two Modes

### Mode 1: Audit & Improve
Trigger: "audit CLAUDE.md", "check project memory"

1. **Discover** all project memory files (CLAUDE.md, AGENTS.md, .cursorrules, etc.)
2. **Assess quality** (commands, architecture, patterns, conciseness, currency, actionability)
3. **Output quality report** with scores per file (A/B/C/D/F)
4. **Propose targeted updates** as diffs
5. **Apply after approval**

### Mode 2: Capture Session Learnings
Trigger: "revise CLAUDE.md", "capture learnings"

1. **Reflect** on missing context (commands, patterns, quirks, gotchas)
2. **Draft additions** - one line per concept, concise
3. **Show proposed changes** as diffs
4. **Apply with approval**

## Quality Criteria

| Criterion | Weight |
|-----------|--------|
| Commands/workflows | High |
| Architecture clarity | High |
| Non-obvious patterns | Medium |
| Conciseness | Medium |
| Currency | High |
| Actionability | High |

## FoodXpress-Specific Checks
- WC()->session / WC()->customer null-check patterns
- Distance data guard pattern
- HPOS fallback for get_edit_post_link()
- Nonce verification for AJAX handlers
- Custom order statuses
- Order meta keys
- Google Maps API requirements
