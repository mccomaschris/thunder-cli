# `site:ssl` Command

## 📝 Description

Add SSL (Cloudflare or Let's Encrypt) to site and configure nginx

---

## 🚀 Usage

```bash
php thundr site:ssl
```

---

## 📋 Behavior

- Prompts to choose SSL type (`Cloudflare` or `Let's Encrypt`)
- Checks for existing certificates and offers to revoke/replace
- Requests and installs a Cloudflare Origin Certificate or Let's Encrypt cert
- Replaces or updates NGINX configuration for SSL
- Reloads NGINX if configuration passes validation

---

## ✅ Example

```bash
php thundr site:ssl
```

Follow the prompts to choose SSL provider and complete setup.

---

## 🧠 Details

This command is implemented in the `SiteSslCommand` class.

---

_Last updated: April 2025_
