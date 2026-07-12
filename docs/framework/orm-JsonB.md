# PostgreSQL JSONB ORM

این مستند نحوه استفاده از قابلیت JSONB در ORM PostgreSQL فریم‌ورک AreaCore را توضیح می‌دهد.

قابلیت JSONB از طریق کلاس `PostgreSQL_DataJson` به query builder اصلی وصل می‌شود و مثل بقیه ORM به صورت chainable کار می‌کند.

---

## تعریف ستون JSONB

برای ساخت ستون JSONB از attribute نوع ستون استفاده کنید:

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

سپس در حالت توسعه می‌توانید schema را مثل بقیه مدل‌ها sync کنید:

```php
Product::table()->update();
```

---

## درج داده JSONB

مقدار JSONB می‌تواند به شکل JSON string ذخیره شود:

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

در insert مستقیم هم مقدار JSON را به صورت string ارسال کنید:

```php
Product::insert([
    [
        "title" => "Laptop",
        "meta" => json_encode(["brand" => "Lenovo", "active" => true]),
    ],
]);
```

---

## شروع Query روی JSONB

برای فیلتر کردن یک ستون JSONB از متد `jsonb()` بعد از `get()` استفاده کنید:

```php
Product::get()
    ->jsonb("meta")->contains(["brand" => "Lenovo"])
    ->do;
```

هر متد JSONB در پایان، query builder اصلی را برمی‌گرداند؛ بنابراین می‌توانید بقیه متدهای ORM را ادامه دهید:

```php
Product::get()
    ->jsonb("meta")->has_key("brand")
    ->sort("#title")
    ->limit(10)
    ->do;
```

---

## عملیات پشتیبانی‌شده

### contains

معادل عملگر `@>` در PostgreSQL است.

```php
Product::get()
    ->jsonb("meta")->contains(["brand" => "Lenovo"])
    ->do;
```

روی مسیر داخلی:

```php
Product::get()
    ->jsonb("meta")->contains(["count" => 12], "stock")
    ->do;
```

مسیر چندسطحی:

```php
Product::get()
    ->jsonb("meta")->contains(["city" => "Tehran"], ["seller", "address"])
    ->do;
```

### contained_by

معادل عملگر `<@` است.

```php
Product::get()
    ->jsonb("meta")->contained_by(["brand" => "Lenovo", "active" => true])
    ->do;
```

### has_key

بررسی وجود یک key در object JSONB:

```php
Product::get()
    ->jsonb("meta")->has_key("brand")
    ->do;
```

روی مسیر داخلی:

```php
Product::get()
    ->jsonb("meta")->has_key("count", "stock")
    ->do;
```

### has_any_key

بررسی وجود حداقل یکی از کلیدها:

```php
Product::get()
    ->jsonb("meta")->has_any_key(["brand", "category", "tags"])
    ->do;
```

### has_all_keys

بررسی وجود همه کلیدها:

```php
Product::get()
    ->jsonb("meta")->has_all_keys(["brand", "active"])
    ->do;
```

---

## Query روی Path

برای خواندن و مقایسه مقدار داخل JSONB از متدهای path استفاده کنید.

### path_equals

```php
Product::get()
    ->jsonb("meta")->path_equals("brand", "Lenovo")
    ->do;
```

مسیر چندسطحی:

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

### مقایسه عددی

متدهای زیر مقدار path را به `numeric` تبدیل می‌کنند:

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

برای operator دلخواه:

```php
Product::get()
    ->jsonb("meta")->where_path("brand", "ILIKE", "%lenovo%")
    ->do;
```

operatorهای مجاز:

```text
=, <>, !=, >, >=, <, <=, LIKE, ILIKE
```

### path_is_null و path_is_not_null

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

PostgreSQL از `jsonpath` هم پشتیبانی می‌کند.

### path_exists

معادل `@?`:

```php
Product::get()
    ->jsonb("meta")->path_exists("$.tags[*] ? (@ == \"work\")")
    ->do;
```

### path_match

معادل `@@`:

```php
Product::get()
    ->jsonb("meta")->path_match("$.stock.count > 10")
    ->do;
```

---

## OR در شرط JSONB

برای شرط JSONB بعدی می‌توانید از `or()` استفاده کنید:

```php
Product::get(["title*" => "Laptop"])
    ->jsonb("meta")->or()->has_key("discount")
    ->do;
```

---

## ترکیب با بقیه ORM

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

## شرط روی دو ستون JSONB

بعد از هر شرط JSONB، query builder اصلی برمی‌گردد؛ بنابراین می‌توانید روی ستون JSONB دیگری هم شرط جدا بگذارید:

```php
Product::get()
    ->jsonb("meta")->contains(["active" => true])
    ->jsonb("settings")->has_key("delivery")
    ->do;
```

مثال با path:

```php
Product::get()
    ->jsonb("meta")->path_equals("brand", "Lenovo")
    ->jsonb("settings")->path_greater(["delivery", "days"], 2)
    ->do;
```

---

## نکات مهم

* ستون باید با `#[Length(type: Type::JSONB)]` ساخته شود.
* مقدارهای object و array در متدهای JSONB به صورت خودکار به JSON تبدیل می‌شوند.
* اگر مقدار را به شکل string بدهید و string معتبر JSON باشد، همان مقدار استفاده می‌شود.
* مقدارهای مقایسه path به صورت bind parameter ارسال می‌شوند.
* نام ستون quote می‌شود و مقدارها مستقیم داخل SQL قرار نمی‌گیرند.
* برای index بهتر روی JSONB می‌توانید در سطح دیتابیس از GIN index استفاده کنید.

