[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Contributing

Thank you for your interest in contributing to this project.
This document covers everything you need to know before opening a pull request.

We produce these packages for use within our own production environments and offer them freely for both personal and commercial use to give back to the open source community we ourselves rely on.

---

## Table of Contents

- [PR Title Format](#pr-title-format)
- [Commit Messages](#commit-messages)
- [Code Style](#code-style)
- [Branching](#branching)

---

## PR Title Format

Every pull request title **must** follow this format:

```text
<prefix>: <Description starting with a capital letter>
```

- The prefix is **always lowercase**.
- A colon and a single space separate the prefix from the description.
- The description starts with a **capital letter**.
- **No trailing period.**

### Allowed Prefixes

| Prefix | When to use |
| --- | --- |
| `fix:` | Something was broken and is now corrected |
| `feat:` | A new user-facing feature or capability |
| `improve:` | An enhancement to existing behavior that is neither a bug fix nor a new feature |
| `ci:` | Changes limited to GitHub Actions workflows or CI scripts |
| `docs:` | Documentation-only changes |
| `style:` | Cosmetic or formatting changes with no logic impact |
| `build:` | Build system, dependencies, or packaging |
| `refactor:` | Internal restructuring with no behavior change |
| `test:` | Test additions or corrections only |
| `chore:` | Maintenance tasks that do not fit any other category |

### Examples

```text
fix: Correct connection timeout handling
feat: Add runtime device info lookup
ci: Add PR title validation workflow
docs: Added contribution documentation
```

### Why This Matters

PR titles become GitHub Release titles verbatim. Users reading the release history
use the prefix to understand at a glance whether an update is a bug fix, a new
feature, or an internal change. Automated pipelines built by community members
also parse these prefixes to classify releases — a non-conforming title will
break those integrations.

A required CI check enforces this format on every PR. The check runs on
`.github/workflows/validate_pr_title.yml`.

---

## Commit Messages

Individual commit messages within a PR follow the same prefix convention and
format as PR titles. Squash commits inherit the PR title, so keeping them
consistent is the most important thing.

---

## Code Style

- **Markdown**: Validated with `markdownlint-cli2` (config at `.rules/.markdownlint.jsonc`).
  The CI workflow applies and auto-commits fixes.
- **PHP**: Validated with Laravel Pint using its default ruleset.

All linters run automatically on pull requests. You do not need to run them locally,
but doing so before pushing will save you a round trip.

---

## Branching

Work on a feature branch and open a PR targeting `main`. There is no separate
`dev` branch in the current workflow. Keep branches short-lived and scoped to
a single logical change.