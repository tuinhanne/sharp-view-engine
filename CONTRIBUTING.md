# Contributing to Sharp

Thank you for your interest in contributing! This document covers everything you need to get started.

---

## Table of Contents

- [Development setup](#development-setup)
- [Running tests](#running-tests)
- [Project structure](#project-structure)
- [Submitting changes](#submitting-changes)
- [Coding standards](#coding-standards)
- [Reporting bugs](#reporting-bugs)
- [Feature requests](#feature-requests)

---

## Development setup

**Requirements:** PHP 8.1+, Composer 2+

```bash
git clone https://github.com/bynhan/sharp.git
cd sharp
composer install
```

---

## Running tests

```bash
# Full suite
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Integration tests only
./vendor/bin/phpunit --testsuite Integration

# Human-readable output
./vendor/bin/phpunit --testdox

# Single test file
./vendor/bin/phpunit tests/Unit/Compiler/LexerTest.php
```

All tests must pass before submitting a pull request. The suite currently has 69 tests.

---

## Project structure

```
src/
├── Sharp.php               ← public entry point
├── Compiler/               ← Lexer, Parser, AST nodes, pipeline stages
├── Loader/                 ← FileLoader, NamespaceLoader, MemoryLoader
├── Runtime/                ← Environment, LoopVariable, Layout, Component, Directive
├── Security/               ← AstValidator (sandbox + structural checks)
├── Support/                ← Config, FileCache, NullCache
├── Contract/               ← Interfaces (Loader, Cache, Pipeline)
└── Exception/              ← ParseException, CompileException, RenderException, ConfigException

tests/
├── Unit/Compiler/          ← LexerTest, ParserTest
├── Unit/Runtime/           ← LoopVariableTest
└── Integration/            ← RenderBasicTest, RenderLayoutTest, RenderLoopTest

docs/                       ← documentation
```

For a detailed description of each component, see [docs/architecture.md](docs/architecture.md).

---

## Submitting changes

1. **Fork** the repository and create a branch from `main`:
   ```bash
   git checkout -b feature/my-feature
   ```

2. **Write tests** for your changes. New features need both unit and integration tests.

3. **Ensure all tests pass:**
   ```bash
   ./vendor/bin/phpunit
   ```

4. **Submit a pull request** against `main`. Include a clear description of what changes were made and why.

### PR checklist

- [ ] Tests added / updated
- [ ] All 69 existing tests still pass
- [ ] No new runtime dependencies added
- [ ] Code follows PHP 8.1+ style (typed properties, enums, `match`, etc.)
- [ ] `docs/changelog.md` updated under `[Unreleased]`

---

## Coding standards

- **PHP 8.1+** — use typed properties, backed enums, `readonly`, `match`, named arguments
- **PSR-12** code style
- **No runtime dependencies** — Sharp has zero runtime deps by design; do not add any
- **One concern per class** — follow existing separation (Lexer, Parser, nodes, pipeline stages, runtime)
- **No regex for structural parsing** — the Lexer is char-by-char by design
- **Explicit-stack parser** — do not add recursion to the Parser

---

## Reporting bugs

Open an issue with:

1. PHP version (`php --version`)
2. Sharp version
3. A minimal `.sp` template that reproduces the problem
4. The PHP code calling `Sharp::render()`
5. Expected output vs actual output / exception message

---

## Feature requests

Open an issue describing the use case. Please include:

- What problem you're trying to solve
- A proposed template syntax example (if applicable)
- Whether the feature requires runtime changes or compile-time only

Features that add runtime dependencies, break existing template syntax, or require regex structural parsing are unlikely to be accepted.
