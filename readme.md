
# Areacore

> A lightweight, extensible PHP framework for both web applications and CLI services.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)](https://php.net)
[![License](https://img.shields.io/github/license/imseyed/AreaCore)](https://github.com/imseyed/AreaCore/blob/main/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/imseyed/AreaCore)](https://github.com/imseyed/AreaCore)

---

## ✨ Features

- **Lightweight** — Minimal footprint, no unnecessary overhead
- **Optimized** — Built for performance from the ground up
- **Small Size** — Tiny codebase, easy to understand and audit
- **Web & CLI Support** — First-class support for HTTP applications *and* background services/scripts
- **Flexible** — Works with MVC, ADR, or any custom structure you prefer

---

## 📦 Installation

Clone directly from GitHub:

```bash
git clone https://github.com/imseyed/AreaCore.git
cd AreaCore
```

---

## 🏗️ Architecture:  Action-Based Architecture (ABA)

Areacore uses an **Action-Based Architecture**, where every route maps directly to an *action* — a file, a method, a function, or an inline closure. This keeps the flow explicit and traceable.

### Request Lifecycle


Client

    └─▶ index.php
        └─▶ Router
              ├─▶ File (e.g. blog/blog-show.php)
              ├─▶ Static method (e.g. Blog::show())
              ├─▶ Controller method (e.g. Controller::blog_show())
              ├─▶ Object method (e.g. [$blog, 'show'])
              └─▶ Inline closure (function() { ... })

### Routing Examples

php
```php
// Route to a file
Router::get("[blogPost]/{blog.post.ID}/add/{title}")
    ->page("blog/blog-show.php");

// Route to a static method
Router::get("[blogPost]/{blog.post.ID}/add/{title}")
    ->call('Blog::show_blog');

// Route to an object method
Router::get("[blogPost]/{blog.post.ID}/add/{title}")
    ->call([$blog, 'show_blog_obj']);

// Route to an inline closure
Router::get("[blogPost]/{blog.post.ID}/add/{title}")
    ->call(function () {
        echo time();
    });

```
---

### Flexibility: MVC vs ADR

The same routing system supports both patterns — choose what fits your project.

#### MVC Pattern

```php
// Controller handles the request, Model fetches data, View renders output
Router::get('/blog/{id}')->call('BlogController::show');

// BlogController.php
class BlogController {
    public static function show(int $id): void {
        $post = Blog::find($id);       // Model
        View::render('blog/show', $post); // View
    }
}
```

#### ADR Pattern (Action–Domain–Responder)

```php
// Each action is a single-responsibility class or file
Router::get('/blog/{id}')->page('actions/blog/Show-blog-action.php');

// Show-blog-action.php
$post = new Blog($id);      // Domain
BlogResponder::respond($post);    // Responder
```

> Both patterns are fully supported. You can even mix them per route.

---

## 📋 Requirements

| Requirement | Version   |
|-------------|-----------|
| PHP         | `>= 8.1`  |
| PDO         | `Enabled` |

No additional dependencies required.

> **Note:** Areacore is designed to run in both **web server environments** (Apache, Nginx, Caddy, PHP built-in server) and **CLI environments** — including cron jobs, queue workers, daemons, and background service scripts.

---

## 📚 Documentation

| Document                                             | Description                            |
|------------------------------------------------------|----------------------------------------|
| [Project Structure](docs/framework/structure.md)     | Directory layout and file organization |
| [Router](docs/framework/router.md)                   | Full routing API reference             |
| [ORM](docs/framework/orm.md)                         | Database layer and model usage         |
| [Quick Start](docs/framework/quick-start.md)         | Step-by-step getting started guide     |
| [Contributing](docs/framework/contribute.md)         | How to contribute to Areacore          |
| [Web Server Deployment](docs/framework/webserver.md) | How to deploy using any web server     |

---

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).