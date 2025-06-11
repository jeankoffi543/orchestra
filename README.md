# 3kjos-command for Laravel

# OVERVIEW
3kjos Command is a Laravel package that provides a powerful command-line tool to quickly generate a complete API structure, including routes, controllers, models, form requests, resources, migrations, and tests. With just one command, you can scaffold an entire API module, significantly reducing development time and maintaining consistency across your project.

# Features

### Automatic API Generation
 - Adds API route to ``api.php`` (index, show, store, put, delete)
 - Generates a ``controller`` with all CRUD methods.
 - Add  ``resource class`` to structure API responses.
 - Builds a ``model`` with its table name, fillable fields, and relationships.
 - Generates a ``form request class`` with validation rules.
 - Creates a ``migration`` with predefined fields.
 - Generates ``feature tests`` for the API.

### Error Handling with ``errorHandler`` (Optional)
 - Centralizes error management across controllers.
 - Handles ``ModelNotFoundException``, ``QueryException``, and other errors.
 - Ensures proper HTTP responses: ``404``, ``403``, ``422``, ``500``.

### Centralized Controller Logic (Optional)
 - Uses a ``Central`` service to handle CRUD operations.
 - Reduces code duplication by managing common logic in one place.

### Factory Generation

 - Creates a ``factory`` for the model with relevant attributes.
 - Simplifies database seeding and testing.


# Installation

```composer require kjos/command```


# Usage

```php artisan kjos:make:api name```

 - Replace ``name`` with the desired API entity (e.g., ``user``, ``product``).
 - This will create all necessary files and append routes automatically.

# Available Options
| Option            | Alias | Description |
|:-----------------:|:-----:|-------------|
| `--force`         | `-f`  | Overwrites existing files if they exist. |
| `--errorhandler`  | `-er` | Enables centralized error handling for controller methods. |
| `--centralize`    | `-c`  | Uses a central class to manage CRUD operations. |
| `--factory`       |       | Generates a model factory with sample data. |
| `--test`          |       | Generates tests files relative to a madel. |

# Example with Options

```php artisan kjos:make:api User --force --errorhandler --centralize --factory --test```

This command will:
✅ Overwrite existing files.
✅ Enable centralized error handling.
✅ Use a central CRUD management system.
✅ Generate a factory for the User model.

# Example Generated Code

### Controller with Error Handling (--errorhandler or -er)
```php
 public function store(UserRequest $request)
{
    return $this->errorHandler(function () use ($request) {
      return new UserResource(User::create($request->validated()));
    });
}
```

**Error Handling with errorHandler Method**
The `errorHandler` method is a utility function designed to execute a callable while handling various exceptions that may occur. It provides robust error handling and ensures that different types of errors are appropriately managed.

- **ModelNotFoundException**: If a model is not found, the method returns a `404 Not Found` response.

- **QueryException**: For security, reasons catches database query errors and returns a 404 Not Found response.

- **General Exceptions**: The method checks the exception code and handles it as follows:
  - `404`: Returns a `404 Not Found` response.
  - `403`: Returns a `403 Forbidden` response with the error message.
  - `422`: Returns a `403 Forbidden` response with the error message.
  - `Other errors`: A generic `500 Internal Server Error` response is returned with the exception message.

This approach ensures that your application responds to errors with proper HTTP status codes, making error handling more predictable and user-friendly.


### Controller with Centralized Logic (--centralize or -c)
```php
 public function store(Request $request)
{
   return $this->errorHandler(function () use ($request) {
      return Central::store(User::class, UserResource::class, $request->validated());
  });
}
```

Centralized management of `index`, `show`, `store`, `update` and `delete` methods


### -Generated Factory (--factory)
```php
public function definition(): array
{
   return [
      'client_id' => 11,
      'price' => 6765610,
      'partner_id' => 25,
   ];
}
```

# Why Use 3kjos Command?
🚀 Save Development Time – Automates repetitive tasks.
✅ Consistency – Ensures a structured and uniform API architecture.
🔧 Customizable – Offers multiple options for error handling, centralization, and testing.
📦 Scalable – Easily extendable for future enhancements.

# License
This package is open-source and available under the MIT License. 🚀
# orchestra
