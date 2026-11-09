# PostgreSQL JSONB ORM

This document explains how to use JSONB in AreaCore's PostgreSQL ORM.

JSONB support is connected to the main query builder through the `PostgreSQL_DataJson` class and works as a chainable part of the ORM.

---

## Define a JSONB column

Use the column type attribute to create a JSONB column:

```php
use ORM\PostgreSQL\Length;
use ORM\PostgreSQL\Type;

class Product
{
    use PostgreSQL;
    
    public $ID;
    public $title;
    
    #[Length(type: Type::JSONB)]
    public $meta;
}
```

During development, you can then synchronize the schema as you would for other models:

```php
Product::table()->update();
```

---

## Insert JSONB data

A JSONB value can be stored as a JSON string:

```php
$product = new Product();
$product->title = "Laptop";
$product->meta = json_encode([
    "brand" => "Lenovo",
    "tags" => ["work", "portable"],
    "stock" => ["count" => 12],
]);
$product->save();
```

For a direct insert, also pass the JSON value as a string:

```php
Product::insert([
    [
        "title" => "Laptop",
        "meta" => json_encode(["brand" => "Lenovo", "active" => true]),
    ],
]);
```

---

## Start a JSONB query

To filter a JSONB column, call `jsonb()` after `get()`:

```php
Product::get()
    ->jsonb("meta")->contains(["brand" => "Lenovo"])
    ->do;
```

Each JSONB method returns the main query builder, so you can continue chaining other ORM methods:

```php
Product::get()
    ->jsonb("meta")->has_key("brand")
    ->sort("#title")
    ->limit(10)
    ->do;
```

---

## Supported operations

### contains

Equivalent to PostgreSQL's `@>` operator:

```php
Product::get()
    ->jsonb("meta")->contains(["brand" => "Lenovo"])
    ->do;
```

At a nested path:

```php
Product::get()
    ->jsonb("meta")->contains(["count" => 12], "stock")
    ->do;
```

At a multi-level path:

```php
Product::get()
    ->jsonb("meta")->contains(["city" => "Tehran"], ["seller", "address"])
    ->do;
```

### contained_by

Equivalent to the `<@` operator:

```php
Product::get()
    ->jsonb("meta")->contained_by(["brand" => "Lenovo", "active" => true])
    ->do;
```

### has_key

Check whether a key exists in a JSONB object:

```php
Product::get()
    ->jsonb("meta")->has_key("brand")
    ->do;
```

At a nested path:

```php
Product::get()
    ->jsonb("meta")->has_key("count", "stock")
    ->do;
```

### has_any_key

Check whether at least one of the keys exists:

```php
Product::get()
    ->jsonb("meta")->has_any_key(["brand", "category", "tags"])
    ->do;
```

### has_all_keys

Check whether all of the keys exist:

```php
Product::get()
    ->jsonb("meta")->has_all_keys(["brand", "active"])
    ->do;
```

---

## Query a path

Use the path methods to read and compare values inside JSONB.

### path_equals

```php
Product::get()
    ->jsonb("meta")->path_equals("brand", "Lenovo")
    ->do;
```

At a multi-level path:

```php
Product::get()
    ->jsonb("meta")->path_equals(["seller", "city"], "Tehran")
    ->do;
```

### path_not_equals

```php
Product::get()
    ->jsonb("meta")->path_not_equals("brand", "Unknown")
    ->do;
```

### Numeric comparisons

The following methods cast the path value to `numeric`:

```php
Product::get()
    ->jsonb("meta")->path_greater(["stock", "count"], 10)
    ->do;

Product::get()
    ->jsonb("meta")->path_greater_or_equal(["stock", "count"], 10)
    ->do;

Product::get()
    ->jsonb("meta")->path_less(["stock", "count"], 50)
    ->do;

Product::get()
    ->jsonb("meta")->path_less_or_equal(["stock", "count"], 50)
    ->do;
```

### where_path

Use a custom operator:

```php
Product::get()
    ->jsonb("meta")->where_path("brand", "ILIKE", "%lenovo%")
    ->do;
```

Allowed operators:

```text
=, <>, !=, >, >=, <, <=, LIKE, ILIKE
```

### path_is_null and path_is_not_null

```php
Product::get()
    ->jsonb("meta")->path_is_null(["seller", "phone"])
    ->do;

Product::get()
    ->jsonb("meta")->path_is_not_null(["seller", "city"])
    ->do;
```

---

## JSONPath

PostgreSQL also supports `jsonpath`.

### path_exists

Equivalent to `@?`:

```php
Product::get()
    ->jsonb("meta")->path_exists("$.tags[*] ? (@ == \"work\")")
    ->do;
```

### path_match

Equivalent to `@@`:

```php
Product::get()
    ->jsonb("meta")->path_match("$.stock.count > 10")
    ->do;
```

---

## OR in JSONB conditions

Use `or()` for the next JSONB condition:

```php
Product::get(["title*" => "Laptop"])
    ->jsonb("meta")->or()->has_key("discount")
    ->do;
```

---

## Combine with other ORM methods

```php
Product::get(["title*" => "Laptop"])
    ->jsonb("meta")->contains(["active" => true])
    ->jsonb("meta")->path_greater(["stock", "count"], 0)
    ->select("ID", "title", "meta")
    ->sort("#title")
    ->limit(20)
    ->do;
```

---

## Conditions on two JSONB columns

After each JSONB condition, the main query builder is returned, so you can add a separate condition on another JSONB column:

```php
Product::get()
    ->jsonb("meta")->contains(["active" => true])
    ->jsonb("settings")->has_key("delivery")
    ->do;
```

Example using paths:

```php
Product::get()
    ->jsonb("meta")->path_equals("brand", "Lenovo")
    ->jsonb("settings")->path_greater(["delivery", "days"], 2)
    ->do;
```

---

## Important notes

* The column must be created with `#[Length(type: Type::JSONB)]`.
* Objects and arrays passed to JSONB methods are automatically converted to JSON.
* If you pass a string containing valid JSON, that string is used as-is.
* Path comparison values are sent as bound parameters.
* Column names are quoted and values are not placed directly into SQL.
* For better JSONB indexing, you can use a GIN index at the database level.
