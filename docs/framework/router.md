# 🌐 Router System

The AreaCore framework includes two routing engines:

* ⚡ **Ultra Router (New)**
* 🧱 **Basic Router (Legacy)**

Both systems can be used depending on project complexity, but Ultra Router is the recommended modern approach.


## ⚡ Ultra Router – Documentation

The Ultra Router is a high-performance, pattern-based routing engine for modern PHP applications. It supports dynamic parameters, nested variable mapping, wildcard segments, multi-handler execution, and a flexible response system.


---

### Pattern: Route + Method + Address + Action

```php
Router::method('/address')->action();
```

#### Example:

```php
Router::get('/user/profile')->page("blog/show");
```

Or with static controller method:

```php
Router::post('/user/save', 'UserController::save');
```

---

### Supported Method Types

| Method   | Description             |
|----------|-------------------------|
| `any`    | Matches any HTTP method |
| `get`    | GET request             |
| `post`   | POST request            |
| `put`    | PUT request             |
| `patch`  | PATCH request           |
| `delete` | DELETE request          |
| `cli`    | CLI execution only      |

---

### Variable Mapping System

#### Defining Variables in URI

To define dynamic variables in your route, use curly braces `{}` with dot notation for nested structures:

```php
Router::get('/user/{profile.name}/post/{post.id}')
```

#### How Variables Are Mapped

When a route matches, variables are available in two ways:

**1. Automatically injected into the called function**

```php
Router::get('/user/{name}/post/{id}', function($name, $id) {
    // $name and $id are automatically passed
    echo "User: $name, Post ID: $id";
});
```

**2. Stored in `Router::$var` array**

All variables are also available globally via `Router::$var` with nested structure:

```php
Router::get('/user/{profile.name}/post/{post.id}')


// For route: /user/john/post/42
Router::$var = [
    'profile' => [
        'name' => 'john'
    ],
    'post' => [
        'id' => 42
    ]
];
```

#### Using Variables in both URI & Query Parameters

```php
// Route definition
Router::get('/blog/{category.name}/article/{article.ID}?type={type}', function($category, $article, $type) {
    echo "Category: {$category['name']}<br>";
    echo "Article ID: {$article['ID']}<br>";
    echo "Type: $type<br>";
    
    // Also accessible via Router::$var
    print_r(Router::$var);
});

// Request URL: /blog/technology/article/123?type=premium

// Router::$var will contain:
[
    'category' => [
        'name' => 'technology'
    ],
    'article' => [
        'ID' => 123
    ],
    'type' => 'premium'  // from query parameter
]
```

---

### Addressing & Wildcards

The router supports two powerful wildcards:

| Wildcard | Description                                                |
|----------|------------------------------------------------------------|
| `[+]`    | Skips **exactly one** URI segment (but continues matching) |
| `[*]`    | Captures **all remaining** URI segments (deep wildcard)    |

#### Web Examples:

```php
// Skips one segment (e.g., /user/admin/profile)
Router::get('/user/[+]/profile')->page('profile.php');

// Captures everything after /user/blog/
Router::get('/user/blog/[*]/edit')->call('BlogController::edit');
```

> ✅ Variables after `[*]` are still parsed and assigned normally.

#### CLI Examples:

```php
Router::get('/cron/backup')->page('cron/db/backup');

// That call when un bellow command:
// >$php index.php cron backup  
```

> ✅ Variables after `[*]` are still parsed and assigned normally.


---

### 4. Defining Constants & Using `Router::prefix()`

You can define reusable constants for use in routes and views.

```php
Router::define('adminBase', '/admin/dashboard');
```

#### Usage in route definition:

```php
Router::get('[adminBase]/settings')->page('admin/settings.php');
```

#### Usage in views / HTML:

```php
<a href="<?=Router::prefix('adminBase')?>/user/manager">Manage Users</a>
```

The `Router::prefix()` method returns the constant value for use in links.

---

### 5. Types of Actions

| Action     | Description                                                    |
|------------|----------------------------------------------------------------|
| `->page()` | Loads a view file from the `view/` directory                   |
| `->call()` | Executes a static controller method, object method, or closure |
| Chaining   | Multiple actions can be executed in sequence                   |

#### Examples:

**run a file:**

```php
Router::get('/blog/{id}')->page('blog/blog-show.php');
```

> * files run using `include()` from folder `/view`. if you want to call from another root directory you can use `../anotherFolder`
> * Write files extension are optional when is `.php`
> * if action file not found, that close process and return string status. if you want to show specific error message must define function `router_file_not_found` 


**Static method:**

```php
Router::get('/blog/{id}')->call('Blog::show');
```

**Object method:**

```php
Router::get('/blog/{id}')->call([$blog, 'show']);
```

**Closure:**

```php
Router::get('/time')->call(function() {
    echo time();
});
```

**Chaining:**

```php
Router::get('/blog/{id}')
    ->page('blog/show.php')
    ->call('Blog::show')
    ->call([$blog, 'logVisit']);
```

---

### 6. Automatic Variable Injection

Variables defined in the route path are automatically passed to the called function — **without requiring type hints**.

The router detects the parameter name and passes the value automatically.

#### Example:

```php
Router::get('/user/{name}/post/{postId}', function($name, $postId) {
    echo "User: $name, Post ID: $postId";
});
```

Even if the function doesn't declare the parameter, no error occurs — the router only injects if the parameter exists.

---

Let me know if you need any further adjustments.

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

## 🔄 Execution Chain

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
