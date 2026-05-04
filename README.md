# pbump

`pbump` is a CLI tool for semver releases in PHP projects with automatic release commit creation and git tagging.

## Features

- bump the version manually (`patch`, `minor`, `major`, `as-is`) or by conventional commits (`next`);
- read the current version from `composer.json` or the latest git tag;
- update `composer.json` when the version source is set to `composer`;
- run in `dry-run` mode without making changes;
- optionally release with unrelated uncommitted files while committing only `composer.json`;
- work interactively through a menu or non-interactively in CI.

## Requirements

- PHP `^8.2`
- Git

## Installation

```bash
composer require --dev rhapsodic/pbump
```

After installation, the binary will be available as `vendor/bin/pbump`.

## Quick Start

Preview what will happen:

```bash
php vendor/bin/pbump --dry-run --type=next
```

Create a patch release without interactive confirmation:

```bash
php vendor/bin/pbump --type=patch --yes
```

Show the current version:

```bash
php vendor/bin/pbump --version
```

Show help:

```bash
php vendor/bin/pbump --help
```

## How a Release Works

During a normal `pbump` run:

1. it determines the current version;
2. it calculates the target version;
3. it updates `composer.json` when needed;
4. it creates a `chore: release vX.Y.Z` commit;
5. it creates a git tag;
6. it pushes the current branch and tag.

By default, the tag is named `vX.Y.Z`, but you can disable tagging or provide a custom tag name.

By default, `pbump` requires a clean working tree. If you need to release while unrelated files are modified, use
`--allow-dirty` or set `"allowDirty": true`; in that mode `pbump` updates `composer.json`, commits only
`composer.json`, and leaves the other files untouched.

## Release Types

- `patch` - `X.Y.Z -> X.Y.(Z+1)`
- `minor` - `X.Y.Z -> X.(Y+1).0`
- `major` - `X.Y.Z -> (X+1).0.0`
- `as-is` - keep the current version
- `next` - calculate the bump from conventional commits
- `conventional` - alias for `next`

## Version Source

The `--version-source` flag supports three modes:

- `auto` - use `composer.json.version` first, and fall back to the latest git tag if it is missing;
- `composer` - always read the version from `composer.json.version` and update it during release;
- `tag` - always read the version from the latest git tag; `composer.json` is not modified.

If there are no tags yet and the source is `tag`, the current version is treated as `0.0.0`.

The latest tag must contain a semver version at the end of the name, for example `v1.2.3` or `release-1.2.3`.

## Configuration via `.pbump.config.json`

You can create a `.pbump.config.json` file in the project root with default values:

```json
{
  "type": "next",
  "versionSource": "auto",
  "tag": true,
  "push": true,
  "yes": false,
  "quiet": false,
  "allowDirty": false
}
```

Supported keys:

- `type`
- `dryRun`
- `tag`
- `push`
- `yes`
- `quiet`
- `versionSource`
- `allowDirty`

CLI arguments take precedence over `.pbump.config.json`.

`tag` can be:

- `true` - create the standard `vX.Y.Z` tag;
- `false` - do not create a tag;
- a string - create a tag with the provided name.

## Main Options

- `-h`, `--help` - show help
- `-v`, `--version` - print the current version
- `--dry-run` - show the plan without making changes
- `--type=<type>` - explicitly set the release type
- `--version-source=<source>` - choose the version source: `auto`, `composer`, `tag`
- `-t`, `--tag[=<name>]` - create a tag, optionally with a custom name
- `--no-tag` - do not create a tag
- `-p`, `--push` - push using the current git upstream
- `--no-push` - do not push
- `-y`, `--yes` - skip confirmation
- `-q`, `--quiet` - hide the summary
- `--allow-dirty` - allow unrelated uncommitted files and commit only `composer.json`

## Interactive and CI Behavior

If `--type` is not provided and input is interactive, `pbump` will show a release type selection menu.

For non-interactive runs, it is best to always pass:

```bash
php vendor/bin/pbump --type=next --yes
```

Otherwise, the tool will not be able to ask for confirmation before creating a release.

## Development and Tests

Run linter:

```bash
composer lint
```

Run static analysis:

```bash
composer analyse
```

Run tests:

```bash
composer test
```

Format code:

```bash
composer format
```

Run the entrypoint locally without a Composer bin proxy:

```bash
php scripts/release.php --help
```

## License

[MIT License](./LICENSE)
