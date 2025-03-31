# thundr site:rollback

## Description

Roll back to the previous deployment.

## Usage

```bash
thundr site:rollback
```

## What it does

- Switches the `current` symlink to the second most recent release.
- Useful when a deployment fails or causes issues.

## Example

```bash
thundr site:rollback
```

## Notes

Only works if at least two deployments exist.
