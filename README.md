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
composer global require thundrlabs/thundr-cli
```

Make sure Composer's global `vendor/bin` directory is in your `$PATH`.

---

## Quick Start

It's best to start with a clean install of Ubuntu 24.04 or Oracle Linux 9.

1. Initialize global config:
    ```bash
    thundr config:init
    ```

2. Provision the server (installs PHP, Nginx, MySQL, Redis, Node via NVM, etc.):
    ```bash
    thundr server:provision
    ```

3. Set up your project:
    ```bash
    thundr site:init
    ```

4. Provision the site on the server:
    ```bash
    thundr site:create
    ```

5. Deploy the project:
    ```bash
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

## Full Command Reference

We’ve documented each command with descriptions, usage examples, and notes:

[View Full CLI Documentation](/docs/index)

---

## Contributing

Pull requests and suggestions are welcome! Please open an issue or submit a PR.

---

## License

MIT
