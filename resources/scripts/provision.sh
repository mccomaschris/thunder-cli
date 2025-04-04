#!/bin/bash

# -----------------------------
# Thundr Server Provisioning Script
# -----------------------------

set -e
export DEBIAN_FRONTEND=noninteractive

# -----------------------------
# Utility Functions
# -----------------------------

function version() {
  echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'
}

function apt_wait() {
  files="/var/lib/dpkg/lock /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock"
  if [ -f /var/log/unattended-upgrades/unattended-upgrades.log ]; then
    files="$files /var/log/unattended-upgrades/unattended-upgrades.log"
  fi
  echo "⏳ Waiting on apt/dpkg locks..."
  while fuser $files >/dev/null 2>&1; do
    sleep 5
  done
}

# -----------------------------
# System Pre-checks
# -----------------------------
function pre_checks() {
  if [[ $EUID -ne 0 ]]; then
    echo "❌ This script must be run as root."
    exit 1
  fi

  if [[ -f /root/.thundr-provisioned ]]; then
    echo "ℹ️  This server has already been provisioned by Thundr."
    exit 0
  fi

  OS_NAME=$(awk -F= '/^NAME=/{print $2}' /etc/os-release | tr -d '"')
  VERSION_ID=$(awk -F= '/^VERSION_ID=/{print $2}' /etc/os-release | tr -d '"')

  if [[ "$OS_NAME" == "Ubuntu" && "$VERSION_ID" == "24.04" ]]; then
    echo "✅ Detected supported OS: Ubuntu 24.04"
  elif [[ "$OS_NAME" == "Oracle Linux Server" && "$VERSION_ID" == "8.10" ]]; then
    echo "✅ Detected supported OS: Oracle Linux 8.10"
  else
    echo "❌ Unsupported OS: $OS_NAME $VERSION_ID"
    exit 1
  fi

  echo "✅ Pre-checks passed."
}

# -----------------------------
# Base Package Installation
# -----------------------------

function install_base_packages() {
  apt_wait
  apt-get update
  apt_wait
  apt-get install -y --allow-downgrades --allow-remove-essential --allow-change-held-packages curl gnupg unzip software-properties-common lsb-release ca-certificates apt-transport-https build-essential
  apt_wait
}

# -----------------------------
# SSH and User Setup
# -----------------------------

function configure_ssh() {
  mkdir -p /root/.ssh
  chmod 700 /root/.ssh
  touch /root/.ssh/authorized_keys
  chmod 600 /root/.ssh/authorized_keys
  chown -R root:root /root/.ssh
  touch /root/.hushlogin
}

function create_thundr_user() {
  useradd thundr
  mkdir -p /home/thundr/.ssh
  adduser thundr sudo
  chsh -s /bin/bash thundr
  cp /root/.profile /home/thundr/.profile
  cp /root/.bashrc /home/thundr/.bashrc
  chown -R thundr:thundr /home/thundr
  chmod -R 755 /home/thundr
  touch /home/thundr/.hushlogin
  cp /root/.ssh/authorized_keys /home/thundr/.ssh/authorized_keys
  chmod 600 /home/thundr/.ssh/authorized_keys
  chown -R thundr:thundr /home/thundr/.ssh
  # Give thundr user passwordless sudo access
  echo "thundr ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/thundr
  chmod 440 /etc/sudoers.d/thundr
}

function configure_thundr_ssh_for_github() {
  echo "🔐 Setting up SSH key and GitHub trust for 'thundr'..."

  sudo -u thundr mkdir -p /home/thundr/.ssh
  sudo -u thundr chmod 700 /home/thundr/.ssh

  # Generate an ed25519 SSH key for GitHub if it doesn't exist
  if [ ! -f /home/thundr/.ssh/id_ed25519 ]; then
    sudo -u thundr ssh-keygen -t ed25519 -C "thundr@localhost" -f /home/thundr/.ssh/id_ed25519 -N ""
    echo "✅ SSH key generated for thundr"
  fi

  # Add GitHub to known_hosts
  sudo -u thundr ssh-keyscan github.com >> /home/thundr/.ssh/known_hosts
  sudo chmod 644 /home/thundr/.ssh/known_hosts
  echo "✅ GitHub host key added to known_hosts"
}

function disable_password_auth() {
  mkdir -p /etc/ssh/sshd_config.d
  cat > /etc/ssh/sshd_config.d/49-thundr.conf << 'EOF'
# Managed by Thundr
PasswordAuthentication no
EOF
}

function restart_ssh() {
  ssh-keygen -A
  service ssh restart
}

# -----------------------------
# NGINX Installation
# -----------------------------

function install_nginx() {
  add-apt-repository -y ppa:ondrej/nginx
  apt_wait
  apt-get update
  apt_wait
  apt-get install -y nginx
  systemctl enable nginx
  systemctl start nginx
  echo "✅ Nginx installed and running."
}

# -----------------------------
# PHP Installation
# -----------------------------

function install_php_stack() {
  add-apt-repository -y ppa:ondrej/php
  apt_wait
  apt-get update
  apt_wait
  apt-get install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-soap php8.3-intl php8.3-readline php8.3-opcache php8.3-redis php8.3-memcached

  # Configure PHP-FPM to run as thundr
  sed -i "s/^user = www-data/user = thundr/" /etc/php/8.3/fpm/pool.d/www.conf
  sed -i "s/^group = www-data/group = thundr/" /etc/php/8.3/fpm/pool.d/www.conf
  sed -i "s/;listen.owner = .*/listen.owner = thundr/" /etc/php/8.3/fpm/pool.d/www.conf
  sed -i "s/;listen.group = .*/listen.group = thundr/" /etc/php/8.3/fpm/pool.d/www.conf
  sed -i "s/;listen.mode = .*/listen.mode = 0666/" /etc/php/8.3/fpm/pool.d/www.conf
  sed -i "s/;request_terminate_timeout = .*/request_terminate_timeout = 60/" /etc/php/8.3/fpm/pool.d/www.conf

  systemctl enable php8.3-fpm
  systemctl restart php8.3-fpm
  echo "✅ PHP 8.3 stack installed and configured."
}

# -----------------------------
# MySQL Installation
# -----------------------------
function install_mysql() {
  apt_wait
  apt-get install -y mysql-server
  systemctl enable mysql
  systemctl start mysql

  MYSQL_PASS=$(openssl rand -base64 18)

  # Securely escape the password for SQL use
  ESCAPED_PASS=$(printf '%s\n' "$MYSQL_PASS" | sed "s/'/''/g")

  # Create user and grant privileges
  mysql -u root -e "CREATE USER IF NOT EXISTS 'thundr'@'localhost' IDENTIFIED BY '${ESCAPED_PASS}';"
  mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'thundr'@'localhost' WITH GRANT OPTION;"
  mysql -u root -e "FLUSH PRIVILEGES;"

  # Write password to secure file
  sudo mkdir -p /etc/thundr
  echo "${MYSQL_PASS}" | sudo tee /etc/thundr/mysql-pass > /dev/null
  sudo chmod 600 /etc/thundr/mysql-pass
  sudo chown root:root /etc/thundr/mysql-pass

  echo "✅ MySQL user 'thundr' created and password saved to /etc/thundr/mysql-pass"
}

# -----------------------------
# Composer & Node Installation
# -----------------------------
function install_composer_and_node() {
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer

  su - thundr -c "curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash"
  su - thundr -c "export NVM_DIR=\$HOME/.nvm && source \$NVM_DIR/nvm.sh && nvm install 22 && nvm use 22 && nvm alias default 22"

  echo "✅ Composer installed globally and Node.js v22 set up via NVM for user 'thundr'."
}

# -----------------------------
# Redis, Memcached, Supervisor
# -----------------------------
function install_redis_memcached_supervisor() {
  apt_wait
  apt-get install -y redis-server memcached supervisor
  systemctl enable redis-server
  systemctl enable memcached
  systemctl enable supervisor
  systemctl restart redis-server
  systemctl restart memcached
  systemctl restart supervisor

  echo "✅ Redis, Memcached, and Supervisor installed and running."
}

# -----------------------------
# Logrotate & Firewall
# -----------------------------

function configure_logrotate_and_ufw() {
  for service in nginx php8.3-fpm mysql-server; do
    file="/etc/logrotate.d/$service"
    if [[ -f "$file" && $(grep --count "maxsize" "$file") == 0 ]]; then
      sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" "$file"
    fi
  done

  apt_wait
  apt-get install -y ufw
  ufw allow 22
  ufw allow 80
  ufw allow 443
  ufw --force enable

  echo "✅ Logrotate limits and UFW firewall rules configured."
}

# -----------------------------
# Catch-All NGINX Site
# -----------------------------

function configure_nginx_catchall() {
  mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
  cat > /etc/nginx/sites-available/000-catch-all << EOF
server {
  listen 80 default_server;
  listen [::]:80 default_server;
  server_name _;
  return 444;
}
EOF
  ln -sf /etc/nginx/sites-available/000-catch-all /etc/nginx/sites-enabled/000-catch-all
  rm -f /etc/nginx/sites-enabled/default
  rm -f /etc/nginx/sites-available/default
  systemctl reload nginx
  echo "✅ Catch-all NGINX site configured."
}

# -----------------------------
# Finalization
# -----------------------------

function mark_provisioned() {
  touch /root/.thundr-provisioned
  echo "✅ Thundr provisioning complete."
}

# -----------------------------
# Main Script
# -----------------------------

pre_checks
install_base_packages
configure_ssh
create_thundr_user
configure_thundr_ssh_for_github
disable_password_auth
restart_ssh
install_nginx
install_php_stack
install_mysql
install_composer_and_node
install_redis_memcached_supervisor
configure_logrotate_and_ufw
configure_nginx_catchall
mark_provisioned
