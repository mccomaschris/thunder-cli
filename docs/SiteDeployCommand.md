# thundr site:deploy

## Description

Deploys the current project to the remote server.

## Usage

```bash
thundr site:deploy
```

## What it does

- Clones the repo, sets up shared `.env`, installs dependencies.
- Runs migrations, builds frontend, updates symlink to current release.

## Example

```bash
thundr site:deploy
```

## Notes

Keeps last 5 releases.
