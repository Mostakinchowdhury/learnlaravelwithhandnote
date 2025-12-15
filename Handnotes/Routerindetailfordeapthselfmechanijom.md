## 🟢 PART–1: Laravel কীভাবে route collect করে ও decide করে?

> 🔹 Laravel এ Route কোথায় জমা হয়?

তুমি যখন লেখো:

```php
Route::get('/users', [UserController::class, 'index']);
```

- 👉 এটা execute হয় না
- 👉 এটা শুধু route register করে

Laravel এ route গুলো জমা হয় এখানে 👇

> Illuminate\Routing\RouteCollection

### 🔹 Route::get() আসলে কী?

Route হলো Facade Facade → ভেতরে router object কে call করে

```pgsql
Route::get(...)
```

↓ Facade ↓ Illuminate\Routing\Router::get()

---

### 🔹 Router::get() এর ভিতরের simplified code

⚠️ এটা real Laravel code না, কিন্তু exact behavior একই

```php

class Router {

    protected $routes;

    public function __construct() {
        $this->routes = new RouteCollection();
    }

    public function get($uri, $action) {
        return $this->addRoute(['GET'], $uri, $action);
    }

    protected function addRoute($methods, $uri, $action) {
        $route = new Route($methods, $uri, $action);

        // Route collection এ জমা
        $this->routes->add($route);

        return $route;
    }
}
```

> 👉 এখানে execute কিছুই হচ্ছে না 👉 শুধু route object বানিয়ে list এ রাখছে

### 🔹 Route object এর ভিতরে কী থাকে?

```pgsql
Route {
   methods: ['GET'],
   uri: '/users',
   action: [
      'controller' => UserController::class,
      'method' => 'index'
   ],
   middleware: [],
}
```

🔥 তাহলে Laravel কখন decide করে কোন action চলবে?

👉 Request আসার পরে

---

### 🔹 Laravel Request Flow (deep)

```pgsql
index.php
 ↓
HttpKernel
 ↓
Global Middleware
 ↓
Router::dispatch($request)
 ↓
RouteCollection::match($request)
 ↓
Matched Route
 ↓
Route Middleware
 ↓
Controller Method
 ↓
Response
```

### 🔹 Route match করা হয় কীভাবে?

```php
foreach ($routes as $route) {
    if ($route->method == $request->method &&
        $route->uri == $request->path) {

        return $route;
    }
}

```

- 👉 match পেলেই থামে
- 👉 তারপর action call হয়

### 🟢 PART–2: Route::get("/route",[Controller::class,"method"]) কেন লাগে?

কারণ Laravel আলাদা করে রাখতে চায় 👇

জিনিস আলাদা কেন

Route URL mapping Controller Business logic Middleware Filter

---

### 🔹 Laravel Controller call ভিতরে কীভাবে হয়?

$controller = new UserController();
return $controller->index($request);

👉 Controller instance auto-create করে 👉 Dependency injection কাজ করে

---

🔵 PART–3: Express এ app.get() — app তো instance, router না?

🔥 এইখানেই আসল confusion

---

🔹 Express এ app কী?

const app = express();

👉 app = Application object 👉 এই app এর ভিতরেই আছে router

---

🔹 Express এর ভিতরের structure (simplified)

function createApp() { const app = function(req, res) { app.handle(req, res); };

    app._router = new Router();

    app.get = function(path, handler) {
        app._router.get(path, handler);
    };

    return app;

}

👉 app.get() আসলে 👉 app.\_router.get() call করে

---

🔹 তাহলে app.get() কেন router method?

কারণ:

> Express app নিজেই root router

---

### 🔥 Express Root Router Concept

app = ROOT ROUTER

আর তুমি চাইলে sub-router বানাতে পারো:

const router = express.Router();

router.get('/users', fn);

app.use('/api', router);

---

### 🔹 Express Routing Execution Flow

```pgsql
Request
 ↓
app (root router)
 ↓
global middleware (app.use)
 ↓
route middleware (app.get)
 ↓
handler
 ↓
response

```

- 🔹 app.get() কি global middleware?

❌ না

```pgsql
Type	app.use	app.get

Runs for all methods	✅	❌
Specific route	❌	✅
Acts as middleware	✅	✅

```

👉 সব route middleware, কিন্তু সব middleware route না

---

### 🔥 Express Router ভিতরের simplified code

```php
class Router {
    constructor() {
        this.stack = [];
    }

    get(path, handler) {
        this.stack.push({
            method: 'GET',
            path,
            handler
        });
    }

    handle(req, res) {
        for (let layer of this.stack) {
            if (layer.method === req.method &&
                layer.path === req.url) {

                return layer.handler(req, res);
            }
        }
    }
}
```

---

### 🧠 Laravel vs Express Routing Mindset

- Laravel বলে:

> “আগে সব রাস্তা লিখে রাখো, পরে আমি ঠিক করবো কোনটা চলবে”

- Express বলে:

> “আমি middleware line-by-line চালাবো, যেটা match করবে সেটাই চলবে”

---

## 🟢 এক লাইনে মনে রাখার ফর্মুলা

```pgsql
Laravel: Route → Match → Middleware → Controller
Express: Middleware → Middleware → Route Handler
```

---

### 🔴 Router::dispatch($request) — ভিতরে কী হয়?

Laravel এ Router class:

```php
class Router
{
    protected $routes; // RouteCollection

    public function dispatch($request)
    {
        // 1️⃣ request থেকে route match করো
        $route = $this->routes->match($request);

        // 2️⃣ matched route কে current route বানাও
        $request->setRouteResolver(fn () => $route);

        // 3️⃣ route চালাও
        return $this->runRoute($request, $route);
    }
}
```

- 👉 dispatch() নিজে কোনো route decide করে না
- 👉 decision নেয় RouteCollection::match()

## 🔴 RouteCollection::match($request) — আসল decision এখানেই

```php
class RouteCollection
{
    protected $routes = [];

    public function match($request)
    {
        $method = $request->getMethod(); // GET / POST
        $path   = $request->path();      // users/1

        foreach ($this->routes[$method] as $route) {

            if ($route->matches($path)) {
                return $route; // 🎯 matched route
            }
        }

        throw new NotFoundHttpException("Route not found");
    }
}
```

- 👉 এখানে Laravel বলে:

> “সব route ঘুরে দেখো, যেটা URL + Method match করে সেটাই নাও”

---

### 🔴 Route::matches() — URI match কীভাবে হয়?

```php
class Route
{
    protected $uri;

    public function matches($path)
    {
        return trim($this->uri, '/') === trim($path, '/');
    }
}
```

(Real Laravel এ regex, parameter, optional segment থাকে)

---

### 🟡 Router::runRoute() — route execute শুরু

```php
protected function runRoute($request, $route)
{
    return $this->runRouteWithinStack($route, $request);
}
```

### 🔴 runRouteWithinStack() — middleware pipeline

```php
protected function runRouteWithinStack($route, $request)
{
    $middleware = $route->gatherMiddleware();

    return (new Pipeline)
        ->send($request)
        ->through($middleware)
        ->then(function () use ($route, $request) {
            return $this->runRouteAction($route, $request);
        });
}
```

👉 এখানে:

Route middleware execute হয়

তারপর controller

---

### 🔴 runRouteAction() — Controller call

```php
protected function runRouteAction($route, $request)
{
    $action = $route->getAction();

    if (isset($action['controller'])) {
        return $this->runController($route, $request);
    }

    return $route->run(); // closure route
}
```

---

### 🔴 runController() — magic part

```php

protected function runController($route, $request)
{
    [$class, $method] = explode('@', $route->getActionName());

    $controller = app()->make($class); // DI container

    return $controller->$method($request);
}
```

### 👉 এখানেই:

Controller instance create হয়

Dependency injection কাজ করে

Method call হয়

---

🟢 পুরো Flow একসাথে (Trace করে)

```pgsql
Request আসে
 ↓
Router::dispatch()
 ↓
RouteCollection::match()
 ↓
Matched Route object
 ↓
Route middleware
 ↓
Controller method
 ↓
Response object

```

### 🔥 Static method না instance method কেন?

তুমি প্রশ্ন করেছিলে:

> Router::dispatch static নাকি?

- 👉 না, static না

```php
$router = new Router($routes);
$router->dispatch($request);

Facade (Route::get) static মনে হলেও
ভেতরে instance method call হয়
```

---

### 🧠 মনে রাখার Shortcut

```pgsql
Route::get()  →  route REGISTER
dispatch()   →  route EXECUTE
match()      →  route SELECT
```

### 🔥 Express vs Laravel (এক লাইনে)

- Laravel → “আগে route জমা, পরে match”

- Express → “middleware চালাতে চালাতে match”
