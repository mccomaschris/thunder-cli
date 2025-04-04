# ⚡️ Thundr CLI

Thundr is a PHP CLI tool for managing Laravel and Statamic sites on your servers—without SSHing in manually. It helps you provision, deploy, configure, and maintain sites using a simple `thundr.yml` file.

---

## Features

- 🔧 Site provisioning with PHP, Nginx, and MySQL
- 🔐 SSL via Let's Encrypt or Cloudflare Origin Certificates
- ⚙️ Cron/scheduler management
- 📦 Zero-downtime deployments
- 🛠 Remote `.env`, logs, and Artisan command execution
- 📂 Multiple server support with centralized config
- 🧼 Rollback and release pruning

---

## Requirements

- PHP 8.1+
- Laravel or Statamic project
- `thundr.yml` file in your project root
- Remote server with SSH access

---

## Installation

```bash
composer global require mccomaschris/thundr-cli
```

Make sure Composer's global vendor/bin directory is in your $PATH.

## Quick Start
It's best to start with a clean install of Ubuntu 24.04.

1. Initialize global config:
```bash
thundr config:init
```

2. Provision the server (install nginx, PHP, MySQL, create `thundr` user);
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

5. Deploy it:
```bash
thundr site:deploy
```

---

## Example thundr.yml
```yml
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

[View Full CLI Documentation](https://mccomaschris.github.io/thunder-cli/)

---

## Contributing
Pull requests and suggestions are welcome! Please open an issue or submit a PR.

---

## License
MIT
