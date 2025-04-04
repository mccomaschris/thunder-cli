# ⚡️ Thundr CLI

Thundr is a PHP CLI tool for managing Laravel and Statamic sites on your servers—without SSHing in manually. It helps you provision, deploy, configure, and maintain sites using a simple `thundr.yml` file.

---

## Features

- 🔧 Site provisioning with PHP, Nginx, MySQL, Redis, and more
- 🔐 SSL via Let's Encrypt or Cloudflare Origin Certificates
- ⚙️ Scheduler and cron management
- 📦 Zero-downtime deployments with shared `.env`, releases, and rollbacks
- 🛠 Remote `.env`, logs, and Artisan command execution
- 📂 Multiple server support with centralized global config
- 🧼 Rollback support and automatic release pruning

---

## Requirements

- PHP 8.1+
- Laravel or Statamic project
- `thundr.yml` file in your project root
- Remote server with SSH access (`thundr` user will be provisioned)

---

## Installation

```bash
composer global require mccomaschris/thundr-cli
```

Make sure Composer's global `vendor/bin` directory is in your `$PATH`.

---

## Quick Start

```bash
thundr config:init
thundr server:provision
thundr site:init
thundr site:create
thundr site:deploy
```

---

## Example `thundr.yml`

```yaml
production:
  root_domain: example.com
  repo: user/repo.git
  branch: main
  php_version: '8.3'
  project_type: laravel
  server: my-production-server
  operating_system: ubuntu
```

---

## 📚 Command Reference
- [server-cloudflare](/docs/server-cloudflare)
- [server-create](/docs/server-create)
- [server-delete](/docs/server-delete)
- [server-edit](/docs/server-edit)
- [server-list](/docs/server-list)
- [server-monitor-status](/docs/server-monitor-status)
- [server-provision](/docs/server-provision)
- [server-ssh](/docs/server-ssh)
- [site-artisan](/docs/site-artisan)
- [site-create](/docs/site-create)
- [site-cron](/docs/site-cron)
- [site-deploy](/docs/site-deploy)
- [site-env](/docs/site-env)
- [site-init](/docs/site-init)
- [site-logs](/docs/site-logs)
- [site-rollback](/docs/site-rollback)
- [site-shell](/docs/site-shell)
- [site-ssl](/docs/site-ssl)
- [site-status](/docs/site-status)

---

## Contributing

Pull requests and suggestions are welcome! Please open an issue or submit a PR.

---

## License

MIT
