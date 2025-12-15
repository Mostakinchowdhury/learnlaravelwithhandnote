📘 Laravel Routing – Deep🔥

---

## 🧠 Big Picture (এক লাইনে)

> Laravel এ Route আগে REGISTER হয়, পরে REQUEST আসলে MATCH + EXECUTE হয়

## 🏗️ Laravel Routing Architecture (Bird’s Eye View)

```pgsql


Browser / Client
      ↓
public/index.php
      ↓
Bootstrap Application
      ↓
Service Providers Register
      ↓
Router (Singleton) Create
      ↓
Route Files Load (web.php, api.php)
      ↓
--- Application Ready ---
      ↓
Request আসে
      ↓
HttpKernel
      ↓
Global Middleware
      ↓
Router::dispatch()
      ↓
RouteCollection::match()
      ↓
Matched Route
      ↓
Route Middleware
      ↓
Controller Method
      ↓
Response


```

## 🔹 Router Instance – কখন, কয়টা, কীভাবে?

### ✅ Router কয়টা?

পুরো Laravel app এ Router instance মাত্র ১টা

এটা singleton

⏰ Router কখন তৈরি হয়?

new Application() এ না

RoutingServiceProvider register হওয়ার সময়

### 📍 কোথায় তৈরি হয়?

```php
Illuminate\Routing\RoutingServiceProvider

$app->singleton('router', function ($app) {
    return new Router($app['events'], $app);
});

```

---

### 🔹 Route::get() আসলে static না

```php
Route::get('/users', [UserController::class, 'index']);
```

ভিতরে যা হয়:

```php
Route::get()
   ↓ (Facade)
app('router')
   ↓ (same instance)
Router->get()
   ↓
RouteCollection->add()
```

## 👉 Route facade শুধু router instance বের করে method call করে

## 🔹 Route Register Phase (Request আসার আগে)

Route Register মানে কী?

URL execute হয় না

শুধু rule হিসেবে জমা থাকে

Router এর ভিতরের structure

```php

class Router {
    protected $routes; // RouteCollection

    public function __construct() {
        $this->routes = new RouteCollection();
    }
}
```

### 👉 এই RouteCollection-এই সব route জমা হয়

> 🔹 RouteCollection কী?

সব route object এর collection

Method অনুযায়ী group করা থাকে

```php
$routes = [
   'GET' => [Route, Route, Route],
   'POST' => [Route, Route]
];
```

---

### 🚦 Request আসার পর Execution Phase শুরু

> এখনই আসল কাজ 🔥

```php
🔴 Router::dispatch($request)
```

দায়িত্ব:

Request অনুযায়ী route নির্বাচন করা

Middleware চালানো

Controller execute করা

Simplified logic:

```php

public function dispatch($request)
{
    $route = $this->routes->match($request);

    return $this->runRoute($request, $route);
}

```

👉 dispatch নিজে route decide করে না

## 🔴 RouteCollectioninstance->match($request)

এই method-ই আসল decision maker

```php
public function match($request)
{
    $method = $request->getMethod();
    $path   = $request->path();

    foreach ($this->routes[$method] as $route) {
        if ($route->matches($path)) {
            return $route;
        }
    }

    throw new NotFoundHttpException();
}
```

👉 এখানে Laravel বলে:

> “এই URL + HTTP method এর জন্য কোন route?”

---

## 🔴 Routeinstance->matches($path)

URI match করার কাজ

```php
public function matches($path)
{
    return trim($this->uri, '/') === trim($path, '/');
}
```

(real Laravel এ regex, parameter support থাকে)

---

## 🔹 Matched Route পাওয়ার পর কী হয়?

```pgsql
Matched Route
   ↓
Route Middleware
   ↓
Controller Action

```

### 🔴 Middleware Pipeline

```php
(new Pipeline)
   ->send($request)
   ->through($routeMiddleware)
   ->then(function () {
       return controller_method();
   });
```

### 👉 Middleware:

```pgsql
Request modify করতে পারে

Block করতে পারে

Response modify করতে পারে
```

### 🔴 Controller Method Call

```php
$controller = app()->make(UserController::class);
return $controller->index($request);
```

👉 এখানে:

Controller instance auto-create হয়

Dependency Injection কাজ করে

## 🔁 Full Request → Response Trace

```pgsql

Request
 ↓
HttpKernel
 ↓
Global Middleware
 ↓
Router::dispatch
 ↓
RouteCollection::match
 ↓
Route::matches
 ↓
Matched Route
 ↓
Route Middleware
 ↓
Controller Method
 ↓
Response

```

## 🧠 Laravel Routing – Mental Model

- Laravel mindset:

> “আগে সব রাস্তা লিখে রাখো, আমি পরে decide করবো কোনটা চলবে”

```pgsql
গুরুত্বপূর্ণ Rule:

Route::get()  → REGISTER
match()       → SELECT
dispatch()    → EXECUTE
```

## 🔥 Express এর সাথে ১ লাইনের তুলনা

Express-> Laravel

- Router -> Singleton
- Route define -> Startup
- Matching -> RouteCollection
- Execution -> Dispatcher

## **Final Takeaway**

> 🔥 Laravel routing = Facade + Singleton Router + RouteCollection + Dispatcher
