# QRUD - Query CRUD 🚀

A powerful and intuitive CRUD & Query Builder for PHP with Laravel-like syntax, featuring intelligent WHERE clauses and fluent API.

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue) ![License](https://img.shields.io/badge/License-MIT-green) ![Version](https://img.shields.io/badge/Version-2.0.0-orange)

## ✨ Features

- **🚀 Fluent Query Builder** - Laravel-like syntax
- **🎯 Intelligent WHERE** - Smart condition handling
- **🔒 Secure** - PDO prepared statements
- **🔄 Transactions** - Full transaction support
- **📊 Aggregations** - Count, Sum, Avg, Exists
- **🔗 JOINs** - All JOIN types supported
- **📄 Pagination** - Built-in pagination
- **🛠️ Subqueries** - Powerful subquery support
- **💡 Smart NULL/IN/BETWEEN** - Automatic condition detection

## 📦 Installation

```bash
composer require erilshk/qrud
```

## 🚀 Quick Start

### Basic Setup

```php
<?php

require_once 'vendor/autoload.php';

use Qrud\CRUD;

// Setup connection
$pdo = new PDO('mysql:host=localhost;dbname=test', 'username', 'password');
CRUD::registerConnection($pdo);

// Or use a callback for lazy connection
CRUD::registerConnection([DatabaseFactory::class, 'createConnection']);
```

### Basic CRUD Operations

```php
// Create
$userId = CRUD::table('users')->create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Read
$user = CRUD::table('users')->read($userId);
$allUsers = CRUD::table('users')->read();

// Update
CRUD::table('users')->update($userId, ['name' => 'Jane Doe']);

// Delete
CRUD::table('users')->delete($userId);
```

## 🔥 Query Builder Examples

### Intelligent WHERE Clauses

```php
use Qrud\CRUD;

// Simple equality
$users = CRUD::query('users')
    ->where('status', 'active')
    ->get();

// Comparisons
$users = CRUD::query('users')
    ->where('age', '>', 18)
    ->where('name', 'LIKE', '%John%')
    ->get();

// NULL checks (intelligent!)
$users = CRUD::query('users')
    ->where('deleted_at', null)        // IS NULL
    ->where('!updated_at', null)       // IS NOT NULL
    ->get();

// IN clauses (automatic!)
$users = CRUD::query('users')
    ->where('status', ['active', 'pending'])  // IN automatically
    ->where('!role', ['banned', 'blocked'])   // NOT IN automatically
    ->get();

// BETWEEN (intelligent syntax)
$orders = CRUD::query('orders')
    ->where('created_at', ['2024-01-01', '><', '2024-12-31'])
    ->get();

// OR conditions
$users = CRUD::query('users')
    ->where('status', 'active')
    ->orWhere('vip', 1)
    ->orWhere('!deleted_at', null)
    ->get();
```

### JOIN Operations

```php
// INNER JOIN
$results = CRUD::query('users')
    ->select('users.*, profiles.bio')
    ->joinInner('profiles', 'users.id = profiles.user_id')
    ->get();

// LEFT JOIN with alias
$results = CRUD::query(['users', 'u'])
    ->joinLeft(['profiles', 'p'], 'u.id = p.user_id')
    ->where('u.status', 'active')
    ->get();

// Multiple JOINs
$results = CRUD::query('orders')
    ->joinInner('users', 'orders.user_id = users.id')
    ->joinLeft('products', 'orders.product_id = products.id')
    ->get();
```

### Aggregations and Grouping

```php
// Count
$count = CRUD::query('users')
    ->where('active', 1)
    ->count();

// Sum and Average
$total = CRUD::query('orders')
    ->where('status', 'completed')
    ->sum('amount');

$avg = CRUD::query('products')
    ->avg('price');

// Group By with Having
$stats = CRUD::query('orders')
    ->select('user_id, COUNT(*) as order_count, SUM(amount) as total')
    ->groupBy('user_id')
    ->having('order_count', '>', 5)
    ->having('total', '>', 1000)
    ->get();
```

### Subqueries

```php
// WHERE subquery
$users = CRUD::query('users')
    ->whereSub('id', 'IN', function($query) {
        $query->where('active', 1)
              ->where('age', '>', 18);
    })
    ->get();

// Complex subquery
$highValueUsers = CRUD::query('users')
    ->whereSub('id', 'IN', function($query) {
        $query->select('user_id')
              ->from('orders')
              ->where('amount', '>', 1000)
              ->groupBy('user_id')
              ->having('COUNT(*)', '>', 3);
    })
    ->get();
```

### Pagination

```php
// Simple pagination
$page1 = CRUD::query('products')
    ->where('stock', '>', 0)
    ->paginate(15, 1);

// Full pagination data
$result = CRUD::query('articles')
    ->where('published', 1)
    ->orderBy('created_at', 'DESC')
    ->paginate(10, 2);

// Returns:
// [
//     'data' => [...],
//     'pagination' => [
//         'current_page' => 2,
//         'per_page' => 10,
//         'total' => 150,
//         'last_page' => 15
//     ]
// ]
```

### UNION Queries

```php
$importantUsers = CRUD::query('users')
    ->select('id, name, "active" as type')
    ->where('status', 'active')
    ->union(function($query) {
        $query->where('vip', 1)
              ->select('id, name, "vip" as type');
    })
    ->union(function($query) {
        $query->where('admin', 1)
              ->select('id, name, "admin" as type');
    })
    ->orderBy('name')
    ->get();
```

## 🔄 Transaction Support

```php
use Qrud\CRUD;

try {
    CRUD::beginTransaction();
    
    $orderId = CRUD::table('orders')->create([
        'user_id' => 1,
        'amount' => 99.99
    ]);
    
    CRUD::table('order_items')->create([
        'order_id' => $orderId,
        'product_id' => 123,
        'quantity' => 2
    ]);
    
    CRUD::table('inventory')->update(123, [
        'stock' => DB::raw('stock - 2')
    ]);
    
    CRUD::commit();
    echo "Transaction completed successfully!";
    
} catch (Exception $e) {
    CRUD::rollback();
    echo "Transaction failed: " . $e->getMessage();
}
```

## 🎯 Advanced Examples

### E-commerce Query

```php
$report = CRUD::query(['orders', 'o'])
    ->select('o.*, u.name, u.email, COUNT(oi.id) as item_count')
    ->joinInner(['users', 'u'], 'o.user_id = u.id')
    ->joinLeft(['order_items', 'oi'], 'o.id = oi.order_id')
    ->where('o.status', 'completed')
    ->where('o.created_at', ['2024-01-01', '><', '2024-12-31'])
    ->where('!o.cancelled', 1)
    ->groupBy('o.id')
    ->having('item_count', '>', 0)
    ->orderBy('o.created_at', 'DESC')
    ->paginate(20, 1);
```

### Complex Reporting

```php
$salesReport = CRUD::query('orders')
    ->select('DATE(created_at) as date, 
              COUNT(*) as order_count, 
              SUM(amount) as daily_revenue,
              AVG(amount) as avg_order_value')
    ->where('status', 'completed')
    ->whereBetween('created_at', '2024-01-01', '2024-01-31')
    ->groupBy('DATE(created_at)')
    ->having('daily_revenue', '>', 1000)
    ->orderBy('date', 'ASC')
    ->get();
```

## 🔧 Debugging

```php
// Get generated SQL
$query = CRUD::query('users')
    ->where('status', 'active')
    ->where('age', '>', 18);

echo $query->toSql(); 
// SELECT * FROM users WHERE status = ? AND age > ?

print_r($query->getBindings());
// ['active', 18]

// Or use mountSQL() for complete SQL with bindings
echo $query->mountSQL();
```

## 📚 API Reference

### CRUD Class Methods

- `table(string $name, string $refKey = "id")` - Create CRUD instance
- `create(array $data)` - Insert record
- `read(mixed $id = null, ?string $select = null)` - Read record(s)
- `update(mixed $id, array $data)` - Update record
- `delete(mixed $id)` - Delete record
- `select(?string $where, array $params = [], ?string $fields = '*')` - Custom SELECT
- `query(string|array $table, string $select = '*')` - Create QueryBuilder

### QueryCrud Methods

- `where() / orWhere()` - Intelligent conditions
- `join*()` - All JOIN types
- `groupBy() / having()` - Grouping
- `orderBy() / limit()` - Sorting & limits
- `count() / sum() / avg() / exists()` - Aggregations
- `paginate()` - Pagination
- `union()` - UNION queries
- `get() / first()` - Execution

## 🐛 Error Handling

```php
try {
    $user = CRUD::table('users')->read(123);
} catch (\Qrud\Exceptions\QueryException $e) {
    echo "Query failed: " . $e->getMessage();
} catch (\Qrud\Exceptions\ConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

## 🤝 Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by Laravel's Eloquent and Query Builder
- Built with PHP 8+ features
- Focus on developer experience and productivity
