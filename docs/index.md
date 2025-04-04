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
- [server-cloudflare](/thundr-cli/server-cloudflare)
- [server-create](/thundr-cli/server-create)
- [server-delete](/thundr-cli/server-delete)
- [server-edit](/thundr-cli/server-edit)
- [server-list](/thundr-cli/server-list)
- [server-monitor-status](/thundr-cli/server-monitor-status)
- [server-provision](/thundr-cli/server-provision)
- [server-ssh](/thundr-cli/server-ssh)
- [site-artisan](/thundr-cli/site-artisan)
- [site-create](/thundr-cli/site-create)
- [site-cron](/thundr-cli/site-cron)
- [site-deploy](/thundr-cli/site-deploy)
- [site-env](/thundr-cli/site-env)
- [site-init](/thundr-cli/site-init)
- [site-logs](/thundr-cli/site-logs)
- [site-rollback](/thundr-cli/site-rollback)
- [site-shell](/thundr-cli/site-shell)
- [site-ssl](/thundr-cli/site-ssl)
- [site-status](/thundr-cli/site-status)

---

## Contributing

Pull requests and suggestions are welcome! Please open an issue or submit a PR.

---

## License

MIT
