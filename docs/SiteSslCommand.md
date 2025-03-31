# thundr site:ssl

## Description

Create or replace SSL certs for the site.

## Usage

```bash
thundr site:ssl
```

## What it does

- Supports Cloudflare Origin Certs and Let's Encrypt.
- Regenerates Nginx config, installs certs, reloads Nginx.

## Example

```bash
thundr site:ssl
```

## Notes

Automatically generates CSR on remote if using Cloudflare.
