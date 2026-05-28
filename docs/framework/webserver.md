# 🌐 AreaCore Web Server Configuration

This document explains how to configure different web servers to run the **AreaCore Framework** correctly.

---

## 📌 Core Principle

> ⚠️ **All dynamic requests MUST always pass through ********************`index.php`******************** (Front Controller).**

* Static files (CSS, JS, images) → served directly by web server
* Dynamic requests → routed to `index.php`
* Security-sensitive files → blocked at server level

---

# 🧩 1. Apache Configuration

![](https://upload.wikimedia.org/wikipedia/commons/1/10/Apache_HTTP_server_logo_%282019-present%29.svg)

## 🧾 .htaccess

```apache
RewriteEngine on

<IfModule mod_rewrite.c>
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteRule ^index\.php$ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
</IfModule>

ErrorDocument 404 /index.php
ErrorDocument 403 /index.php
Options All -Indexes

<Files ".htaccess">
Order allow,deny
Deny from all
</Files>

# Prevent direct access to PHP files
<FilesMatch "\.(php|module|phtml|hphp|ctp|inc)$">
Order allow,deny
Deny from all
</FilesMatch>

# Allow only index.php
<Files "index.php">
Order allow,deny
Allow from all
</Files>
```

## ⚙️ Behavior

* All unknown routes → `index.php`
* Direct PHP file access blocked
* 404/403 handled by framework

---

# ⚡ 2. Caddy Server Configuration
![](https://dqah5woojdp50.cloudfront.net/original/2X/5/5f2c1a30bf4aeec78ece52d64426ec606d9fee7d.png)

First start fastcgi pool:
```bash
 php-cgi.exe -b 127.0.0.1:9000
```

```bash
## 🧾 Caddyfile

```caddy
root * C:\caddy\backend

# Serve static files directly if exist
try_files {path} {path}/ /index.php?{query}

# Block all PHP files except index.php
@not_index_php {
    path_regexp php ^.*\.php$
    not path /index.php
}

respond @not_index_php 403

# PHP handler (FastCGI pool)
php_fastcgi 127.0.0.1:9000 {
    env DEVELOPE_MODE "1"
    env APP_BASE "/FolderName"
}

file_server
```

## ⚙️ Behavior

* Static files served automatically
* Dynamic routes → `index.php`
* Direct PHP execution blocked
* FastCGI pool for performance

---

# 🚀 3. Nginx Configuration
![](https://logodix.com/logo/1638974.png)

First start fastcgi pool:
```bash
 php-cgi.exe -b 127.0.0.1:9000
```

## 🧾 site.conf

```nginx
server {
    listen 80;
    server_name example.com;

    root /var/www/areacore/public;
    index index.php;

    # Main Front Controller routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handling (only via index.php)
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

        # Block direct PHP access except index.php
        if ($uri != "/index.php") {
            return 403;
        }
    }

    # Block hidden files
    location ~ /\.
    {
        deny all;
    }

    # Block sensitive files
    location ~* \.(env|log|ini|sql|conf)$ {
        deny all;
    }
}
```

## ⚙️ Behavior

* High performance routing
* Static files served directly
* PHP execution restricted
* Secure production setup

---

# 🧪 4. PHP Built-in Web Server (Development)
![](https://www.php.net/images/logos/new-php-logo.svg)

## 🟢 Simple Mode

```bash
php -S 127.0.0.1:8000 index.php
```

⚠️ Not recommended if you have static files

---

## 🟡 Recommended Mode (Static & Dynamic files)

```bash
php -S 127.0.0.1:8000 buildin.php
```

## 🧾 router.php (FULL VERSION)

```php
<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = __DIR__ . $path;
// Normalize extension
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
// 🚫 Block dangerous / executable PHP-related extensions
$blockedExtensions = [
    'php',
    'module',
    'phtml',
    'hphp',
    'ctp',
    'inc'
];
// If request targets blocked PHP-like files → never serve directly
if (in_array($ext, $blockedExtensions, true)) {
    require __DIR__ . "/index.php";
    exit;
}
// Serve static files directly (safe files only)
if ($path !== "/" && file_exists($file) && is_file($file)) {
    return false;
}
// Route everything else to index.php (Front Controller)
require __DIR__ . "/index.php";
```

## ⚙️ Behavior

* Lightweight dev server
* No production security
* Simple routing model

---

# 🔁 Request Lifecycle (All Servers)

```
Client Request
      ↓
Web Server (Apache / Nginx / Caddy / PHP Built-in)
      ↓
Is Static File?
   ├── YES → Serve directly
   └── NO  → index.php
                ↓
           AreaCore Router
                ↓
           Controller Layer
                ↓
             Response
```

---

# 🔒 Security Rules

✔ Only `index.php` is public entry point for dynamic logic
✔ Block direct access to PHP files
✔ Block sensitive files (.env, .log, .sql)
✔ Prefer server-level security over PHP-level checks

---

# 📌 Summary

AreaCore enforces a strict **Front Controller Architecture**:

* 🔹 Predictable routing
* 🔹 Centralized request handling
* 🔹 Server-independent deployment
* 🔹 Strong security boundary

> 🚀 Everything flows through: **index.php**
