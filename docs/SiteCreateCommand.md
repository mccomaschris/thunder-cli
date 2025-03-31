# thundr site:create

## Description

Creates a site on the selected server using thundr.yml.

## Usage

```bash
thundr site:create
```

## What it does

- Provisions directories, Nginx config, and optionally a MySQL database.
- Uploads `.env`, installs dependencies, and prepares for deployment.

## Example

```bash
thundr site:create
```

## Notes

Ensure `thundr.yml` is configured before running.
