# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release. Omnishield ALTCHA: a proof of work the site issues and checks itself, on altcha-org/altcha - a challenge signed with the site's key, the solution the visitor's browser found checked, a solution refused the second time; no third party, no cookie.

### Changed

- Requires `omnisense/contracts: ^1.0`, `omnisense/omnishield: ^1.0`: stable versions, so `composer require omnishield/altcha` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- `phpstan-baseline.neon`, empty: the shared workflow requires the file; nothing is accepted.
