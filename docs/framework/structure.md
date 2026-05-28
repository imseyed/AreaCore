# 🏗️ Project Structure

This document explains the core structure of the framework and the responsibility of each directory and file.

---

# 📁 Root Structure

```text
/
├── area/
├── libs/
├── model/
├── view/
├── config.php
└── index.php
```

---

# 📦 `area/`

The `area` directory contains the core engine of the framework.

This directory is responsible for:

* Bootstrapping the framework
* Loading ORM drivers
* Loading extensions
* Loading libraries
* Loading models

---

# ⚙️ `area/Genesis.php`

`Genesis.php` is the main bootstrap file of the framework.

It defines the base constants and prepares the framework environment.

## Base Constants

```php
const _ = null;
const AreaCore = "v2.1.1";
```

## Runtime Environment Detection

```php
if (php_sapi_name() == "cli"){ // CLI MODE
    define('EOL', PHP_EOL);
    define("protocol", __DIR__);
} else{ // Web MODE
    define('EOL', '<br>');
    define("protocol", (@$_SERVER['REQUEST_SCHEME']?:strtolower(explode('/', $_SERVER['SERVER_PROTOCOL'])[0]))."://");
}
```

The framework automatically detects whether it is running in:

* CLI mode
* Web mode

and configures the environment accordingly.

---

# 🔄 Framework Loading Sequence

`Genesis.php` loads the following sections in order:

1. `load_orm`
2. `load_extension`
3. `load_lib`
4. `load_model`

This loading order is important because some modules may depend on previously loaded components.

---

# 🗄️ `area/ORM/`

This directory contains ORM drivers used by the framework.

You can:

* Use existing ORM implementations
* Develop your own ORM driver

## ORM Loader Rule

Every ORM must contain a loader file with the following extension:

```text
.orm.php
```

Example:

```text
mysql.orm.php
pgsql.orm.php
```

Only files with this extension will be automatically loaded by the framework.

---

# 🧩 `area/extension/`

Framework extensions are stored in this directory.

Extensions allow developers to expand framework functionality without modifying the core.

You may:

* Use existing extensions
* Develop custom extensions

## Extension Loader Rule

Extension files must use the following extension:

```text
.ext.php
```

Example:

```text
auth.ext.php
ultra-router.ext.php
```

Only files with this extension will be automatically loaded.

---

# 📚 `libs/`

The `libs` directory is used for third-party libraries and shared utility libraries.

If a library file has the following extension:

```text
.lib.php
```

it will be automatically loaded by `Genesis.php`.

## Examples

### `public.lib.php`

Contains commonly used helper functions.

### `global.lib.php`

Can be used for loading Composer-based libraries or global dependencies.

---

# ⚠️ Preload Recommendation

If a library is only used in a specific section of the project and is not globally required, do not place it in the framework preload system.

Instead, load it manually only where needed using:

```php
require_once
```

This helps reduce unnecessary memory usage and improves application performance.

---

# 🧠 `model/`

The `model` directory contains the project's models and class files.

The framework uses `autoload_register` to automatically load supported files when needed.

## Supported Extensions

```text
.php
.module
.class.php
.inc
```

---

# 🧬 Class Naming Rules

The class name must match the filename.

Example:

```text
User.class.php
```

```php
class User
{
}
```

---

# 🗂️ Namespace Support

If namespaces are used, files must be stored inside matching directories.

Example:

```text
model/App/Services/UserService.class.php
```

```php
namespace App\Services;
```

---

# 🔠 Case Sensitivity

>The framework is not sensitive to uppercase/lowercase naming differences.
> 
>Even on Linux systems, classes will still load correctly if filename casing is inconsistent.

---

# 🎨 `view/`

The `view` directory contains:

* Templates
* Front-end layouts
* Responders
* Themes
* Presentation layers

Its internal structure is completely customizable and controlled by the developer.

The framework does not enforce a strict architecture for this directory.

---

# 🚪 `index.php`

`index.php` is the main application entry point.

All incoming requests must pass through this file, including:

* Web requests
* CLI requests

## Basic Bootstrap

```php
<?php

require_once "config.php";
require_once "area/Genesis.php";
```

After bootstrapping, routes and application logic are defined here.

---

# ⚙️ `config.php`

`config.php` contains the main project configuration.

You may:

* Define static configuration values
* Use environment variables (`$_ENV`)
* Use Docker-based runtime configuration

## Example Configuration

```php
<?php

const siteAddress = "example.com";
const base = "/AreaCore";
const siteVersion = "v1.2.3";

$DB_Config = array(
    'mode'=>'mysql',
    'hostname'=>'localhost',
    'port'=>'3306',
    'username'=>'root',
    'password'=>'',
    'dbname'=>'areacore'
);
```

This file is typically responsible for:

* Database configuration
* Project settings
* Environment configuration
* Runtime constants

---

# ✅ Summary

AreaCore is designed with a lightweight and extensible architecture.

The framework focuses on:

* Simplicity
* Flexible structure
* Dynamic loading
* Modular development
* Easy extension support

Developers have full control over project organization while still benefiting from a centralized framework core.
