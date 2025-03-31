# thundr site:status

## Description

Check health and service status for the site.

## Usage

```bash
thundr site:status
```

## What it does

- Verifies SSL, PHP-FPM, Nginx, and `/thundr-up` route availability.
- Supports different OS service names.

## Example

```bash
thundr site:status
```

## Notes

Requires `server_type` in `thundr.yml` for accurate checks.
