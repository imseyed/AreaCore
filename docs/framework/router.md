حتماً — این نسخه‌ی اصلاح‌شده و دقیق **Router.md** هست، با رعایت تمام نکاتی که گفتی و بدون اشتباهات قبلی:

---

# 🌐 Router System

The AreaCore framework includes two routing engines:

* ⚡ **Ultra Router (New)**
* 🧱 **Basic Router (Legacy)**

Both systems can be used depending on project complexity, but Ultra Router is the recommended modern approach.

---

# ⚡ Ultra Router

The Ultra Router is a high-performance, pattern-based routing engine with:

* Named routes
* Dynamic parameters
* Nested variable mapping
* Wildcard segments (`[+]`, `[*]`)
* Query binding
* Multi-handler execution
* Flexible response system (page + callbacks)

---

# 🧠 Request URI Structure

The current request path is available in:

```php
Router::$uri
```

It is always an indexed array.

### 📌 Example (Web Request)

Request:

```text
/user/blog/12/add/title
```

Becomes:

```php
[0] => ""
[1] => "user"
[2] => "blog"
[3] => "12"
[4] => "add"
[5] => "title"
```

---

### 📌 Important Notes

* `index 0` is always empty in **web mode**
* In **CLI mode**, index `0` represents the script name (e.g. `index.php`)
* All values are automatically normalized to **lowercase**

---

# 🔁 Case Sensitivity Rule

Routing comparisons are **case-insensitive**.

Internally, comparisons are handled using:

```php
strcasecmp()
```

This means:

```text
/Blog/Post
/blog/post
/BLOG/POST
```

are all treated as identical routes.

---

# 📦 Route Variables

All extracted dynamic values are stored in:

```php
Router::$var
```

### Example Mapping

For route:

```text
/user/blog/12/add/title
```

You may get:

```php
[
  "blog" => [
    "post" => [
      "ID" => 12,
      "type" => "news"
    ]
  ],
  "title" => "hello-world"
]
```

---

# 🔄 Multiple Route Execution

By default, only the first matching route executes.

To enable multiple route execution:

```php
Router::$ALLOW_MULTIPLE = true;
```

This allows multiple matching routes to run for a single request.

---

# 🧭 Supported HTTP / CLI Methods

The router supports multiple request types:

| Method   | Description             |
| -------- | ----------------------- |
| `any`    | Matches any HTTP method |
| `get`    | GET requests            |
| `post`   | POST requests           |
| `put`    | PUT requests            |
| `patch`  | PATCH requests          |
| `delete` | DELETE requests         |
| `cli`    | CLI execution only      |

---

### 📌 Usage Example

```php
Router::get('/user/profile')->page('user/profile.php');
Router::post('/user/save')->call('User::save');
```

---

# 🏷️ Named Routes

Named routes allow reusable path definitions.

```php
Router::define('blogPost', '/user/blog');
```

Usage:

```php
Router::get('[blogPost]/12');
```

---

# 🛣️ Route Definition

## 📌 Basic Route

```php
Router::get('/user/blog')->page('blog/blog-show.php');
```

---

## 📌 Dynamic Route

```php
Router::get('/user/blog/{blog.post.ID}')
    ->page('blog/blog-show.php');
```

---

## 📌 Query Binding

```php
Router::get('/blog?id={blog.post.ID}')
    ->page('blog/show.php');
```

---

# 🔗 Wildcard System

## ➕ `[+]` (Single Segment Skip)

`[+]` skips **exactly one URI segment** but still continues matching.

### Example:

```php
Router::get('/user/[+]/profile')
```

Matches:

```text
/user/admin/profile
/user/mod/profile
```

But only skips **one segment**.

---

## 🌟 `[*]` (Deep Wildcard / Rest Path)

`[*]` captures the remaining URI path.

### Example:

```php
Router::get('/user/blog/[*]/12')
```

Matches:

```text
/user/blog/anything/else/12
```

### ⚠️ Important Behavior

* `[*]` stops strict path matching from that point
* BUT variables AFTER `[*]` are still parsed and assigned
* Query parameter binding still works normally

---

# 📄 Page Rendering

## `->page()`

Loads a view file from the `view/` directory.

```php
Router::get('/blog/{id}')
    ->page('blog/blog-show.php');
```

### Behavior:

* Relative paths automatically resolve to `view/`
* File is included directly
* `.php` extension is optional

---

# ⚙️ Controller Execution

## `->call()`

Executes a controller or callback.

---

### 1. Static Method

```php
Router::get('/blog/{id}')
    ->call('Blog::show');
```

---

### 2. Object Method

```php
Router::get('/blog/{id}')
    ->call([$blog, 'show']);
```

---

### 3. Closure

```php
Router::get('/time')
    ->call(function () {
        echo time();
    });
```

---

# 🔄 Execution Chain

You can chain multiple actions:

```php
Router::get('/blog/{blog.post.ID}')
    ->page('blog/show.php')
    ->call('Blog::show')
    ->call([$blog, 'show_obj'])
    ->call(function () {
        echo time();
    });
```

Execution order:

1. Page render
2. Static controller
3. Object method
4. Closure

---

# 🧬 Variable Mapping System

Dynamic variables:

```text
{blog.post.ID}
```

Becomes:

```php
Router::$var['blog']['post']['ID']
```

---

# 🔍 Query Parameters

Query strings are automatically parsed:

```text
?type={blog.post.type}
```

Mapped into:

```php
Router::$var['blog']['post']['type']
```

---

# 🧠 Internal Flow

1. Load URI → `Router::$uri`
2. Normalize path
3. Match route pattern
4. Extract variables
5. Bind query parameters
6. Check method constraints
7. Execute actions

---

# 🧱 Basic Router (Legacy)

The Basic Router is a simpler, older routing system.

---

## 📦 Global URI Variable

In Basic Router, a global variable exists:

```php
$uri
```

It is an array representation of the request path, always in lowercase.

---

## 📌 Example

Request:

```text
/user/blog/12
```

Becomes:

```php
$uri[0] => ""
$uri[1] => "user"
$uri[2] => "blog"
$uri[3] => "12"
```

---

## ⚙️ Behavior

* Lightweight routing
* No advanced wildcard system
* No nested variable mapping
* Suitable for simple applications
* Legacy compatibility mode

---

# 🧠 Summary

### ⚡ Ultra Router

* Modern routing engine
* Advanced patterns (`[+]`, `[*]`)
* Nested variables
* Multi-handler execution
* View + controller hybrid system

### 🧱 Basic Router

* Simple array-based routing
* Global `$uri` usage
* Minimal features
* Legacy support only

---

If you want, next step I can:

* convert this into a **real GitHub README + navigation docs**
* or design a **routing diagram (visual flow)**
* or add **middleware layer design on top of Ultra Router**
