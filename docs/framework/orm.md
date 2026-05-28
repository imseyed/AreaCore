# 🧠 ORM System

The AreaCore ORM is a lightweight, fluent, and expressive database abstraction layer designed to simplify working with relational databases while maintaining full flexibility and performance.

It is fully integrated with the framework core and supports both **static query building** and **object-oriented data manipulation**.

---

# ⚡ Core Concept

Each model class in the framework acts as an ORM entry point.

```php
User::get();
User::insert();
User::table();
```

The ORM is designed around a **chainable method system** that allows expressive query building.

---

# 📥 1. Data Retrieval

## 🔹 `::get()`

Retrieves database rows matching specific conditions and returns them as an array.

```php
User::get()->do;
```

---

## 🔧 Chain Methods

### 📌 `->limit()`

Limits the number of returned rows.

```php
User::get()->limit(10)->do;
```

Behavior:

* `limit(n)` → first `n` rows
* `limit(start, count)` → range selection

```php
User::get()->limit(5, 10)->do;
```

---

### 📌 `->select()`

Selects specific columns from the table.

```php
User::get()->select('name', 'email')->do;
```

---

### 📌 `->sort()`

Sorts results by a column.

```php
User::get()->sort('age')->do;
```

* Numeric sort:

  ```php
  ->sort('age')
  ```

* String sort:

  ```php
  ->sort('#name')
  ```

---

### 📌 `->has()`

Returns rows where a column has a value.

```php
User::get()->has('name')->do;
```

---

### 📌 `->null()`

Returns rows where a column is empty.

> NULL and empty string are treated the same.

```php
User::get()->null('name')->do;
```

---

### 📌 `->table()`

Specifies a different table name.

```php
User::get()->table('members')->do;
```

If not defined, the class name is used as the table name.

---

# 🧩 2. Query Conditions

Conditions inside `::get()` support multiple operators.

```php
User::get(['age>' => 18])->do;
```

---

## 📊 Supported Operators

| Operator           | Description                     |              |
| ------------------ | ------------------------------- | ------------ |
| `^`                | Neutral / no-op                 |              |
| `~`                | Reverse order                   |              |
| `@INT@INT@`        | Range selection (start → count) |              |
| `@INT@`            | Limit count                     |              |
| `!=`               | Not equal                       |              |
| `                  | `                               | OR condition |
| `==`               | Equal (default if omitted)      |              |
| `>=, <=, >, <`     | Numeric comparison              |              |
| `>=#, <=#, >#, <#` | String comparison               |              |
| `*`                | LIKE / pattern match            |              |

---

## 🧠 Condition Rules

Conditions are defined as arrays:

```php
User::get(['name' => "Ali", 'age' => 21])->do;
```

If all conditions are equality-based, PHP 8.1 named arguments style can also be used:

```php
User::get(name: "Ali", age: 21)->do;
```

---

## 🔀 Logical Grouping

### OR Conditions

```php
User::get(['name' => "Ali", '|name' => "Hasan"])->do;
```

### Multiple OR

```php
User::get(['name' => "Ali", '|name' => "Hasan", '||name' => "Mahdi"])->do;
```

---

### Complex Groups

```php
User::get([
    ['gender' => "male", 'age<15'],
    ['|', 'gender' => "female", 'age>18']
])->do;
```

---

### Pattern Matching

```php
User::get(['name' => "*Ali*"])->do;
User::get(['name' => "Ali*"])->do;
User::get(['name' => "*Ali"])->do;
```

---

### Combined Conditions

```php
User::get([
    'name' => "Ali",
    ['age<=' => 20, '|age>=' => 30]
])->do;
```

---

# 🚀 3. Execution Modes

## 📦 `->do`

Returns all results as an array.

```php
User::get()->do;
```

---

## 🔁 `->ro`

Iterates results row by row.

```php
while ($row = User::get()->ro) {
    echo $row->name;
}
```

---

## 🔢 `->co`

Returns count of rows.

```php
User::get()->co;
```

---

## ⚡ `->noBuffer`

Streams results without storing in memory.

```php
$data = User::get();

while ($row = $data->noBuffer) {
    if ($row->ID > 1000) {
        $data->kill();
        break;
    }
}
```

> ⚠️ Always call `kill()` when breaking early.

---

# 📌 Example Usage

```php
User::get()->limit(5)->do;
User::get()->sort('name')->limit(10)->do;
User::get(['age>' => 18])->do;
User::get(['name' => "*ali*"])->do;
```

---

# 🧾 4. Row Counting

## `::count_rows()`

Counts rows with conditions.

```php
User::count_rows(['age>' => 18])->do;
```

---

# 🧬 5. Insert Data

## `::insert($data, $tableName = null)`

Inserts multiple rows into a table.

---

## 📌 Array Formats

### Key-Value Format

```php
User::insert([
    ['username' => "ali", 'name' => "Ali"],
    ['username' => "hasan", 'name' => "Hasan"]
]);
```

---

### Column-First Format

```php
User::insert([
    ['username', 'name'],
    ["ali", "Ali"],
    ["hasan", "Hasan"]
]);
```

---

### Direct Table Insert

```php
core::insert($array, 'user');
```

---

# 🧩 6. Object Operations

## 📦 `->get_vars($valued = true)`

Returns object properties as an array.

---

## 📥 `->load($array)`

Loads array data into object properties.

```php
$user = new User();
$user->load($data);
```

---

## 🔍 `->value_of($propertyName)`

Returns a single property value.

```php
$user->value_of('name');
```

---

## 🧹 `->clear()`

Clears all properties.

---

## ⚙️ `->set()`

Loads data and optionally queries database.

```php
$user->set(username: "ali")->reverse();
```

---

## 🆔 `->set_byID()`

Loads object by ID.

```php
$user->set_byID(2);
```

---

## ✅ `->check()`

Checks if object exists in database.

```php
if ($user->check()) {
    // exists
}
```

---

## 💾 `->save($table = '', $newRow = false)`

Saves object to database.

* Insert if new
* Update if exists

```php
$user->save();
```

---

## 🗑️ `->delete($table = '')`

Deletes object from database.

```php
$user->delete();
```

---

## ✏️ `->change($attribute, $value, $ID = null, $table = null)`

Updates a single field.

```php
$user->change('name', 'Ali');
```

---

### ⚡ Shortcut Update

```php
$user->name("Ali");
```

---

# 🧱 7. Table Management

Each model property becomes a database column by default.

---

## 🏷️ Attributes

* `#[Unique]` → unique column
* `#[Index]` → indexed column
* `#[Length(type: Type::BIGINT)]`
* `#[Length(type: Type::VARCHAR, maxLength: 64)]` → defines the database column type and optional length. Available data types may vary depending on the database engine (MySQL, PostgreSQL, SQLite, etc.), and each database can support different type options or limits.
* `#[NoColumn]` → excluded column

---

## 🏗️ Table Operations

### Create

```php
User::table()->create();
```

---

### Update

```php
User::table()->update();
```

---

### Exists

```php
User::table()->exist();
```

---

### Rename

```php
User::table()->rename("user", "member");
```

---

### Delete

```php
User::table("member")->delete();
```

---

### Indexing

```php
User::table()->index('title')->update();
User::table()->index(['title', 'author'])->update();
```

---

Here is your requested **Section 8 - How to Use ORM** added in a clean, consistent, and polished style matching your documentation:

---

## 🔌 8. How to Use

To enable and use a specific ORM inside a model, you must import it using the `use` keyword.

Each ORM defines its own behavior and database driver implementation.

### 📌 Supported ORMs

```php
use PostgreSQL;
```

or

```php
use MySQL;
```

Once an ORM is imported, all ORM methods and behaviors automatically become available inside the class and its instances.

---

## 🧠 Concept

When you declare:

```php
use MySQL;
```

You are essentially telling the model:

> “This class should use MySQL as its database engine and ORM driver.”

This allows the framework to dynamically attach:

* Query builder behavior
* Schema handling
* Insert / update logic
* Type mapping rules

---

## 🧬 Example Model

```php
class Blog
{
    use PostgreSQL;
    
    public $ID;
    
    public $title;
    public $content;
    
    #[Unique]
    public $author;
    
    public ?bool $show = false;
    
    public ?int $publishTime = null;
    
    #[Length(type: Type::VARCHAR, maxLength: 4)]
    #[Unique]
    public $uri;
    
    #[NoColumn]
    public $comments;
}
```

---

## ⚙️ Type Mapping Behavior

One of the key features of the ORM system is **automatic type inference** from PHP property types.

### 📌 Rule

If a property is declared with a native PHP type:

* `int`
* `bool`
* `float`

then the ORM automatically maps it to the appropriate database type.

### 🧠 Important Note

This happens **even if `#[Length]` or explicit column attributes are not defined**.

### 📌 Example

```php
public ?bool $show = false;
public ?int $publishTime = null;
```

These will automatically be mapped to:

* `BOOLEAN` / `TINYINT(1)` (depending on driver)
* `INTEGER / BIGINT` (depending on driver)

without requiring manual schema definition.

---

## 🚀 Summary

Using `use ORM` inside a model:

* Activates the database engine
* Enables query and schema behavior
* Automatically binds model to ORM system
* Supports multiple database drivers (MySQL, PostgreSQL, etc.)
* Enables automatic type mapping from PHP types

This makes models fully **self-aware database entities** inside AreaCore.

## ⚙️ 9. Developer Suggestion (Development Mode)

During development, it is strongly recommended to enable a dedicated development mode to keep ORM models and database schemas automatically synchronized with the application code.

---

### 📌 Enable Development Mode

In `config.php`, define the following constant:

```php
const developMode = true;
```

This flag enables additional development-time behaviors across the framework.

---

### 🚀 Bootstrapping in `index.php`

Inside the main entry file, load a development helper module conditionally:

```php
if (developMode) {
    require "develop-mode.php";
}
```

This ensures that development utilities are only executed in non-production environments.

---

### 🧠 `develop-mode.php`

The `develop-mode.php` file is responsible for automatically synchronizing database schema changes during development.

It should trigger schema updates for the required models using:

```php
User::table()->update();
Blog::table()->update();
Post::table()->update();
```

---

### 🔄 Purpose of This Approach

This mechanism ensures:

* Database schema stays synchronized with model definitions
* New properties are automatically reflected in tables
* Indexes and constraints remain up to date during development
* No manual migration steps are required while iterating quickly

---

### ⚠️ Important Notice

This mode must **never be enabled in production environments**, because:

* `::table()->update()` may modify database structure dynamically
* It can override or alter existing schema definitions

It is intended strictly for:

* Local development
* Rapid prototyping
* Active schema evolution during development cycles


🧠 Summary

The AreaCore ORM is designed to be:

* ⚡ Fast and lightweight
* 🔗 Fully chainable
* 🧩 Flexible for complex queries
* 🧠 Close to natural language usage
* 🛠️ Fully integrated with models and schema system

It provides both **low-level control** and **high-level abstraction** without sacrificing performance.
