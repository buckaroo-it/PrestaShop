# CLAUDE.md

Guidance for Claude Code when working on the Buckaroo PrestaShop plugin.

---

# Project Scope — READ FIRST

This directory is the **Buckaroo PrestaShop plugin repository**.

The repository contains the plugin code that must be modified for Jira/GitHub tasks.

Treat the **current plugin repository root** as the project root.

Claude may:

* Read files anywhere inside the repository when necessary.
* Read the installed PrestaShop source/core for reference when available.
* Read Composer dependencies for reference when necessary.

Claude may modify **only files belonging to the Buckaroo plugin repository**.

Never modify:

* PrestaShop core
* Other plugins
* `vendor/`
* `node_modules/`
* generated build output
* runtime/cache files
* environment files containing credentials
* files outside the plugin repository

Git commands MUST be executed from the plugin repository root.

---

# CRITICAL RULES

The following rules are mandatory and take priority over convenience:

1. NEVER modify files outside the plugin repository.
2. NEVER modify PrestaShop core.
3. NEVER modify `vendor/`.
4. NEVER modify `node_modules/`.
5. NEVER modify generated files unless they are explicitly part of the ticket.
6. NEVER commit directly to the default branch.
7. NEVER commit without explicit user confirmation.
8. NEVER push without explicit user confirmation.
9. NEVER merge a PR.
10. NEVER expose or commit secrets.
11. NEVER invent a Jira ticket or GitHub issue number.
12. NEVER claim a test passed unless it was actually executed successfully.
13. NEVER claim a manual verification was performed unless it was actually performed.
14. Keep changes strictly scoped to the requested ticket/issue.
15. Do not fix unrelated problems discovered during investigation.
16. Do not discard or overwrite existing user changes.
17. Do not perform destructive Git operations without explicit confirmation.
18. Do not upgrade dependencies unless explicitly required.
19. Do not change public behavior outside the requested scope.
20. When uncertain, inspect the existing implementation and relevant PrestaShop code before changing anything.

---

# The Plugin

## Project

Buckaroo payment provider integration for PrestaShop.

The plugin provides Buckaroo payment methods and payment integrations for PrestaShop stores.

## Repository

Use the current repository root as the project root.

The git repository is the module directory itself:

```text
html/modules/buckaroo3/
```

It lives inside the local PrestaShop installation at `C:\Buckaroo\PrestaShop`. The PrestaShop installation itself is **not** a git repository — never run git commands from the PrestaShop root.

The main source code is located under:

```text
src/
```

The module's main service and integration code should be investigated before making changes.

---

# PrestaShop Development Rules

## Architecture

Follow the existing architecture of the Buckaroo PrestaShop module.

Before implementing functionality:

1. Search the existing plugin for similar functionality.
2. Identify the existing service/controller/helper responsible for the behavior.
3. Follow the existing module structure and conventions.
4. Reuse existing helpers and services where appropriate.
5. Avoid introducing duplicate abstractions.
6. Keep changes as small as possible.

Do not introduce a new architecture for a small bug fix.

Do not refactor unrelated code while implementing a ticket.

---

# Existing Code First

Before writing new code:

1. Search the repository for existing implementations.
2. Search for related:

   * services
   * controllers
   * payment handlers
   * helpers
   * configuration
   * templates
   * assets
   * URL/path generation
   * API clients
   * tests
3. Check whether the required functionality already exists.
4. Reuse existing methods and conventions where possible.
5. Avoid duplicate helpers or services.

For example, before changing a URL or path:

* search for other uses of the same path;
* check whether PrestaShop already provides a helper/constants for the required behavior;
* check whether the plugin already has a URL/path helper;
* inspect how the same functionality is implemented elsewhere in the plugin.

---

# Investigation Before Implementation

For non-trivial tickets, do not immediately edit files.

First:

1. Read the issue/ticket carefully.
2. Identify the affected component.
3. Locate the relevant source files.
4. Trace the existing execution flow.
5. Search for related implementations.
6. Inspect relevant tests.
7. Check compatibility with supported PrestaShop versions.
8. Identify possible side effects.
9. Determine the root cause.
10. Implement the smallest appropriate fix.

For bug fixes:

**Root cause must be identified before implementation.**

Do not apply speculative fixes.

---

# PrestaShop Compatibility

All changes MUST remain compatible with the PrestaShop versions supported by the plugin.

Before using a PrestaShop API, class, constant, hook, method, or service:

1. Check whether it exists in the minimum supported version.
2. Check how the plugin currently uses it.
3. Inspect the relevant PrestaShop source when necessary.

Do not introduce APIs that are unavailable in supported versions.

Do not raise the minimum supported PrestaShop version unless explicitly requested.

Do not silently drop support for an existing PrestaShop version.

---

# PrestaShop Core

PrestaShop core may be inspected for:

* classes
* interfaces
* services
* constants
* hooks
* controllers
* URL generation
* configuration
* payment behavior
* compatibility

However:

**NEVER modify PrestaShop core files.**

If a core behavior appears problematic, implement the required compatibility/fix inside the Buckaroo module where appropriate.

If the issue cannot safely be solved inside the plugin, report the limitation instead of modifying core.

---

# Module Structure

Respect the existing module structure.

The module root currently contains:

```text
buckaroo3/
├── src/
├── controllers/
├── classes/
├── api/
├── library/
├── config/
├── views/
├── translations/
├── mails/
├── dev/
├── tests/
├── buckaroo3.php
├── composer.json
├── config.xml
└── phpunit.xml
```

Do not assume every directory exists.

Inspect the actual repository before making changes.

Do not reorganize the directory structure unless explicitly required.

---

# Payment Integration

Buckaroo is a payment provider plugin.

Payment-related changes require additional care.

Preserve existing:

* payment flows
* transaction states
* order states
* authorization behavior
* capture behavior
* refund behavior
* cancellation behavior
* asynchronous callbacks
* push/webhook handling
* redirect behavior
* return URLs
* payment method configuration

unless the ticket explicitly requires a change.

Before changing payment logic:

1. Trace the complete existing flow.
2. Identify where the request originates.
3. Identify how the Buckaroo request is created.
4. Identify how the response is processed.
5. Identify how transaction/order state is updated.
6. Check synchronous and asynchronous flows.
7. Check retry and duplicate-request behavior.
8. Check error handling.

Never assume that a payment operation can safely be executed multiple times.

Preserve idempotency where it exists.

Do not swallow payment/API exceptions.

Do not expose:

* API credentials
* API keys
* signatures
* tokens
* customer payment data
* card information
* personal data

in logs, exceptions, tests, or commits.

---

# Security

Security-related tickets must be treated as high priority.

When modifying:

* signature validation
* webhook/PUSH validation
* request parameters
* authentication
* payment callbacks
* redirects
* URL handling
* user input
* file handling

inspect the complete data flow before implementing the fix.

Do not trust request parameters.

Validate and sanitize input according to the existing PrestaShop/plugin conventions.

Do not weaken existing validation to make a request succeed.

Do not introduce:

* hardcoded secrets
* unsafe redirects
* SQL injection
* XSS
* command injection
* path traversal
* insecure deserialization
* authentication bypasses
* signature-validation bypasses

If a security issue is identified, keep the fix strictly scoped to the reported vulnerability.

---

# Configuration and Secrets

Never hardcode:

* API keys
* secrets
* passwords
* merchant credentials
* private keys
* access tokens

Never commit credentials.

Never print credentials in logs.

When debugging API requests, redact sensitive values.

Do not modify production credentials.

Use the existing plugin/PrestaShop configuration mechanisms.

---

# URL and Path Handling

PrestaShop installations may run:

* in the web root
* in a subdirectory
* behind a reverse proxy
* with different shop URLs
* with multiple shop configurations

Do not assume that the shop is installed at `/`.

When generating URLs or asset paths:

1. Check how PrestaShop exposes the shop's base URI.
2. Check existing plugin conventions.
3. Check whether the path needs to work in a subdirectory.
4. Avoid hardcoded root-relative paths when they are incompatible with the supported installation configuration.

For example, when a ticket concerns a shop installed at:

```text
https://example.com/shop/
```

the generated URL must preserve the `/shop/` base path.

Do not fix URL/path issues by hardcoding a particular domain or installation path.

---

# Templates and Frontend

When modifying:

* Smarty templates
* JavaScript
* CSS
* payment logos
* storefront assets
* checkout UI

follow the existing plugin structure.

Do not rewrite unrelated frontend code.

Do not replace existing assets unless explicitly required.

When modifying paths to assets, verify both:

1. normal root installation;
2. subdirectory installation.

---

# Database

Database schema changes must follow the existing PrestaShop module migration/upgrade mechanism.

Before changing database behavior:

1. Inspect existing install/upgrade scripts.
2. Determine how the plugin handles version upgrades.
3. Follow the existing migration convention.
4. Preserve backwards compatibility.

Never manually modify the production database as part of normal development.

Do not delete or rename existing columns/tables without explicitly understanding upgrade implications.

Do not perform destructive database operations unless explicitly required.

Never modify an already released migration/upgrade script if doing so would break existing installations.

Create a new upgrade step when appropriate.

---

# Dependencies

Do not add dependencies unless required by the ticket.

Before adding a dependency:

1. Search the plugin.
2. Search existing Composer dependencies.
3. Check whether PrestaShop already provides the required functionality.
4. Check whether the Buckaroo SDK already provides it.

Do not upgrade unrelated dependencies.

Do not run broad dependency upgrades.

Do not modify:

```text
composer.json
composer.lock
package.json
package-lock.json
```

unless required by the ticket.

Do not run:

```bash
composer update
```

unless explicitly required.

Prefer the smallest dependency change possible.

---

# Generated Files

Never manually edit generated files.

Do not modify:

```text
vendor/
node_modules/
```

or generated/cache/build output unless the ticket explicitly requires generated output to be committed.

If generated output needs to change:

1. Modify the source.
2. Run the appropriate build command.
3. Verify the generated result.
4. Check the final diff.

---

# Testing

For every code change:

1. Identify relevant existing tests.
2. Add or update tests when behavior changes.
3. Run the smallest relevant test suite first.
4. Run broader checks when practical.
5. Review failures.
6. Fix failures caused by the implementation.
7. Do not modify tests merely to make them pass.

Never claim a test passed unless it was actually executed.

If a test cannot be executed, state exactly why.

---

# Manual Testing

For issues involving checkout, payment methods, URLs, assets, redirects, or browser behavior, automated tests may not be sufficient.

When manual testing is required:

1. Identify the required environment.
2. Test the affected behavior.
3. Test the normal/root installation if relevant.
4. Test the subdirectory installation if relevant.
5. Check browser/network requests where appropriate.
6. Verify the expected payment method/logo/redirect behavior.

Do not claim manual testing was performed if Claude did not actually perform it.

---

# Static Analysis and Code Quality

Use the repository's existing tooling.

Before running commands, inspect:

```text
composer.json
package.json
phpunit.xml*
phpstan*
phpcs*
.php-cs-fixer.dist.php
```

and any existing development documentation.

Use the project's configured commands rather than inventing new ones.

Do not introduce a new linter or static-analysis tool for an unrelated ticket.

---

# Scope Discipline

Work only on what is necessary to solve the requested Jira/GitHub issue.

DO NOT:

* perform unrelated refactoring
* rename unrelated classes
* reorganize directories
* upgrade dependencies
* rewrite working code
* change unrelated payment methods
* fix unrelated bugs
* modify unrelated tests
* change public APIs without a requirement
* change formatting across unrelated files
* modify PrestaShop core

If an unrelated problem is discovered:

**Report it to the user instead of fixing it.**

---

# Diff Quality

Before requesting commit approval:

Run:

```bash
git status
git diff
```

Review the complete diff.

Verify:

* only expected files changed;
* no unrelated changes exist;
* no debug statements remain;
* no credentials/secrets are present;
* no generated files were accidentally modified;
* no vendor files were modified;
* code follows existing style;
* the implementation is limited to the ticket.

---

# Git Workflow

## Branches

Never work directly on:

```text
main
master
develop
```

unless the repository's actual default branch differs and the user explicitly instructs otherwise.

In this repository the remote default branch is `master`, and `develop` is the shared development branch. Neither may be committed to directly.

Always work on a dedicated ticket branch.

If the user provides an exact branch name:

**Use it exactly.**

Never rename or alter a branch name provided by the user.

---

# Current Branch Safety

Before making changes:

1. Run:

```bash
git status
```

2. Check the current branch.
3. Inspect existing uncommitted changes.
4. Confirm that the branch is appropriate for the requested ticket.

If currently on the default/develop branch:

Create a dedicated branch before modifying files.

If currently on a different ticket branch:

Stop and ask the user whether to switch/create the correct branch.

Never assume an unrelated branch is safe to reuse.

---

# User Changes

Before modifying files, inspect the working tree.

If existing uncommitted changes are present:

* Do not overwrite them.
* Do not revert them.
* Do not discard them.
* Do not reset them.
* Do not assume they belong to the current ticket.

If affected files already contain user changes and ownership is unclear:

**Ask the user before modifying those files.**

---

# Branch Naming

When the user provides a Jira ticket such as:

```text
BTI-1234
```

or a GitHub issue such as:

```text
#271
```

derive a short description from the requested task.

Do not ask for a short description unless necessary.

Use:

```text
<type>/<ticket>-<short-description>
```

Allowed types:

```text
feature/
fix/
refactor/
chore/
docs/
```

Keep the description short:

**2–4 words, kebab-case.**

Examples:

```text
fix/BTI-1426-payment-logo-path
fix/271-payment-logo-path
fix/BTI-1500-subdirectory-assets
```

---

# Before Starting Work

Before modifying code:

1. Run:

```bash
git status
```

2. Check the current branch.
3. Inspect existing local changes.
4. Fetch remote references if branch creation/synchronization is required.
5. Create the appropriate ticket branch.
6. Confirm the repository is in a safe state.

Never modify the default branch directly.

---

# Commits

Never commit without explicit user confirmation.

Before committing, show the user:

* current branch;
* files changed;
* short summary of changes;
* tests performed;
* proposed commit message.

Then ask:

**"Do you want me to commit these changes?"**

Wait for explicit confirmation.

Commit messages should be short and descriptive.

When a Jira ticket exists, include it in the commit message.

Example:

```text
BTI-1426 Fix payment logo paths
```

For GitHub issue #271:

```text
#271 Fix payment logos in subdirectory
```

---

# PUSHING — ALWAYS ASK FOR CONFIRMATION

Claude MUST NEVER run:

```bash
git push
```

without explicit user confirmation.

Before pushing, tell the user:

* branch that will be pushed;
* remote;
* commits that will be pushed;
* intended PR target.

Then ask:

**"Do you want me to push?"**

Wait for explicit confirmation.

---

# Pull Requests

Pushing and creating a PR are separate actions.

Confirmation to push is **NOT** confirmation to create a PR.

After pushing successfully, stop and ask:

**"Pushed `<branch>` to `<remote>`. Do you want me to create a PR?"**

Only create the PR after explicit confirmation.

When creating a PR:

* target the repository's normal development branch;
* include the Jira/GitHub issue reference;
* keep the title concise;
* include a short description;
* summarize the change;
* explain the reason;
* list tests performed;
* mention relevant limitations.

Claude MUST NOT merge the PR.

---

# Git Safety

Prefer non-destructive Git operations.

Never use destructive commands to solve normal workflow problems.

Do not use:

```bash
git reset --hard
```

to discard changes.

Do not use:

```bash
git clean
```

to remove files unless explicitly instructed.

Never discard user work.

If local changes would be overwritten, deleted, or lost:

**Stop and ask the user.**

---

# Dangerous Git Operations

Always ask for explicit confirmation before:

```text
git push
git push --force
git push --force-with-lease
git reset --hard
git clean
branch deletion
remote branch deletion
merge
rebase of shared branches
```

Never perform these automatically.

---

# Issue Implementation Workflow

For every Jira/GitHub issue, follow this workflow.

## Phase 1 — Understand

Read the complete issue.

Identify:

* problem;
* expected behavior;
* actual behavior;
* affected versions;
* affected files;
* reproduction steps;
* proposed solution;
* testing requirements.

Do not immediately edit code.

---

## Phase 2 — Investigate

Search the repository.

Identify:

* relevant class/service;
* callers;
* related helpers;
* existing tests;
* configuration;
* related payment methods;
* related URL/path handling;
* compatibility concerns.

Determine the root cause.

---

## Phase 3 — Plan

Before editing, determine the smallest change that solves the issue.

Prefer:

* existing helpers;
* existing constants;
* existing services;
* existing conventions.

Avoid unnecessary abstraction.

---

## Phase 4 — Implement

Implement only the required change.

Do not refactor unrelated code.

Do not change behavior outside the issue.

---

## Phase 5 — Verify

Run the relevant tests/checks.

Inspect:

```bash
git diff
git status
```

Verify the implementation against the issue's acceptance criteria.

---

## Phase 6 — Report

Before asking for commit approval, report:

### Changed

List the files changed.

### Root cause

Explain the actual root cause found.

### Fix

Explain the implementation.

### Testing

List tests/checks that were actually executed.

### Not tested

Clearly identify anything that could not be tested.

### Git

Show:

* current branch;
* changed files;
* proposed commit message.

Then ask for explicit commit confirmation.

---

# Final Principle

The goal is not to change as much code as possible.

The goal is to make the **smallest correct, compatible, maintainable change that completely solves the requested ticket**, while preserving existing Buckaroo payment behavior and PrestaShop compatibility.

When in doubt:

**Investigate first. Reuse existing code. Change as little as necessary. Verify before claiming success.**
