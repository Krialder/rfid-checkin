# Source Directory (src/)

This directory contains the modern MVC architecture implementation of the RFID Check-in System, built using comprehensive patterns and contemporary PHP practices.

## 📁 Directory Structure

```
src/
├── Application.php              # Main application bootstrap
├── Controllers/                 # Request controllers (MVC)
│   ├── BaseApiController.php    # Base API controller
│   ├── BaseFrontendController.php # Base frontend controller
│   ├── Api/                     # API controllers
│   │   ├── AuthController.php   # Authentication endpoints
│   │   ├── DashboardController.php # Dashboard API
│   │   ├── EventController.php  # Event management API
│   │   ├── RfidController.php   # RFID hardware integration
│   │   └── UserController.php   # User management API
│   └── Frontend/                # Web interface controllers
│       ├── DashboardController.php # Dashboard interface
│       ├── EventController.php  # Event management interface
│       ├── AuthController.php   # Authentication interface
│       └── AdminController.php  # Administrative interface
├── Models/                      # Data models (MVC)
│   ├── BaseModel.php           # Abstract base model
│   ├── User.php                # User entity model
│   ├── Event.php               # Event entity model
│   ├── Checkin.php             # Check-in record model
│   ├── RfidDevice.php          # RFID device model
│   └── UserGroup.php           # User group model
├── Services/                    # Business logic layer
│   ├── ConfigurationService.php # Configuration management
│   ├── DatabaseService.php     # Database operations
│   ├── AuthenticationService.php # Authentication logic
│   ├── EventService.php        # Event management logic
│   ├── RfidDeviceService.php   # RFID device management
│   ├── RfidHardwareService.php # Hardware integration
│   ├── SecurityService.php     # Security operations
│   ├── LoggingService.php      # Logging and monitoring
│   ├── PerformanceCacheService.php # Caching operations
│   ├── RealtimeService.php     # Real-time features
│   ├── AssetOptimizationService.php # Asset optimization
│   ├── DatabaseOptimizationService.php # DB optimization
│   └── SecurityHardeningService.php # Security hardening
├── Repositories/                # Data access layer
│   ├── BaseRepository.php      # Abstract repository
│   ├── UserRepository.php      # User data access
│   ├── EventRepository.php     # Event data access
│   ├── CheckinRepository.php   # Check-in data access
│   └── RfidDeviceRepository.php # Device data access
├── Middleware/                  # Request middleware
│   ├── MiddlewareManager.php   # Middleware orchestration
│   ├── AuthMiddleware.php      # Authentication middleware
│   ├── CorsMiddleware.php      # Cross-origin requests
│   ├── RateLimitMiddleware.php # Rate limiting
│   ├── SecurityMiddleware.php  # Security enforcement
│   └── LoggingMiddleware.php   # Request logging
├── Routing/                     # URL routing system
│   ├── Router.php              # Main router class
│   ├── Route.php               # Route definition
│   └── RouteGroup.php          # Route grouping
├── Views/                       # Template system
│   ├── BaseView.php            # Base view class
│   ├── layouts/                # Layout templates
│   ├── components/             # Reusable components
│   └── pages/                  # Page templates
└── Exceptions/                  # Custom exceptions
    ├── BaseException.php       # Base exception
    ├── ValidationException.php # Validation errors
    ├── AuthenticationException.php # Auth errors
    └── DatabaseException.php   # Database errors
```

### Design Patterns

**Model-View-Controller (MVC):**
- **Models**: Data representation and business entities
- **Views**: Presentation layer and templates
- **Controllers**: Request handling and response coordination

**Repository Pattern:**
- Abstracts data access logic
- Provides consistent interface for data operations
- Enables testing with mock repositories

**Service Layer:**
- Encapsulates business logic
- Coordinates between controllers and repositories
- Provides reusable business operations

**Dependency Injection:**
- Loose coupling between components
- Easier testing and maintenance
- Centralized dependency management

### SOLID Principles

**Single Responsibility Principle:**
- Each class has one reason to change
- Clear separation of concerns
- Focused functionality

**Open/Closed Principle:**
- Open for extension, closed for modification
- Use of interfaces and abstract classes
- Plugin architecture support

**Liskov Substitution Principle:**
- Derived classes are substitutable for base classes
- Consistent interface implementations
- Polymorphic behavior

**Interface Segregation Principle:**
- Clients depend only on methods they use
- Small, focused interfaces
- Reduced coupling

**Dependency Inversion Principle:**
- Depend on abstractions, not concretions
- High-level modules independent of low-level modules
- Flexible architecture

## 🚀 Application Bootstrap

### Application.php

The main application class orchestrates system initialization:

```php
class Application
{
    private ConfigurationService $config;
    private DatabaseService $database;
    private LoggingService $logger;
    private Router $router;
    private MiddlewareManager $middleware;
    
    public function __construct(string $configPath = null)
    {
        $this->initializeConfiguration($configPath);
        $this->initializeErrorHandling();
        $this->initializeServices();
        $this->initializeMiddleware();
        $this->initializeRouting();
    }
    
    public function run(): void
    {
        $this->startSession();
        $this->logRequest();
        $this->router->dispatch();
        $this->logResponse();
    }
}
```

**Bootstrap Features:**
- Service container initialization
- Error handling setup
- Middleware pipeline configuration
- Routing system setup
- Session management
- Request/response logging

### Service Container

Centralized dependency injection:

```php
class ServiceContainer
{
    private array $services = [];
    private array $singletons = [];
    
    public function register(string $abstract, callable $concrete): void
    {
        $this->services[$abstract] = $concrete;
    }
    
    public function singleton(string $abstract, callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }
    
    public function resolve(string $abstract): mixed
    {
        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }
        
        if (isset($this->services[$abstract])) {
            return $this->services[$abstract]();
        }
        
        throw new ServiceNotFoundException($abstract);
    }
}
```

## 🎮 Controllers

### Base Controllers

**BaseApiController** - Foundation for API endpoints:

```php
abstract class BaseApiController
{
    protected AuthenticationService $auth;
    protected ValidationService $validator;
    protected LoggingService $logger;
    
    protected function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    protected function errorResponse(string $message, int $status = 400): void
    {
        $this->jsonResponse([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ], $status);
    }
    
    protected function validateRequest(array $rules): array
    {
        $data = $this->getRequestData();
        return $this->validator->validate($data, $rules);
    }
}
```

**BaseFrontendController** - Foundation for web interfaces:

```php
abstract class BaseFrontendController
{
    protected ViewService $view;
    protected AuthenticationService $auth;
    protected FlashMessageService $flash;
    
    protected function render(string $template, array $data = []): void
    {
        $data['user'] = $this->auth->getCurrentUser();
        $data['flash'] = $this->flash->getMessages();
        
        $this->view->render($template, $data);
    }
    
    protected function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header("Location: $url");
        exit;
    }
    
    protected function requireAuth(): void
    {
        if (!$this->auth->isAuthenticated()) {
            $this->redirect('/auth/login');
        }
    }
}
```

### API Controllers

**AuthController** - Authentication endpoints:

```php
class AuthController extends BaseApiController
{
    public function login(): void
    {
        $data = $this->validateRequest([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);
        
        try {
            $user = $this->auth->authenticate($data['username'], $data['password']);
            
            $this->jsonResponse([
                'success' => true,
                'user' => $user,
                'token' => $this->auth->generateToken($user['id'])
            ]);
        } catch (AuthenticationException $e) {
            $this->errorResponse($e->getMessage(), 401);
        }
    }
    
    public function logout(): void
    {
        $this->auth->logout();
        $this->jsonResponse(['success' => true]);
    }
    
    public function refresh(): void
    {
        $token = $this->auth->refreshToken();
        $this->jsonResponse(['token' => $token]);
    }
}
```

**RfidController** - RFID hardware integration:

```php
class RfidController extends BaseApiController
{
    private RfidHardwareService $rfidService;
    
    public function checkin(): void
    {
        $data = $this->validateRequest([
            'rfid' => 'required|string|rfid_format',
            'device_id' => 'required|string'
        ]);
        
        try {
            $result = $this->rfidService->processCheckin(
                $data['rfid'],
                $data['device_id']
            );
            
            $this->jsonResponse([
                'success' => true,
                'action' => $result['action'],
                'user' => $result['user'],
                'event' => $result['event']
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage());
        }
    }
    
    public function registerTag(): void
    {
        $data = $this->validateRequest([
            'rfid' => 'required|string|rfid_format',
            'user_id' => 'required|integer'
        ]);
        
        $this->rfidService->registerTag($data['rfid'], $data['user_id']);
        $this->jsonResponse(['success' => true]);
    }
}
```

## 📊 Models

### Base Model

**BaseModel** - Foundation for all data models:

```php
abstract class BaseModel
{
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }
    
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }
    
    public function getAttribute(string $key): mixed
    {
        if (isset($this->attributes[$key])) {
            return $this->castAttribute($key, $this->attributes[$key]);
        }
        return null;
    }
    
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    public function toArray(): array
    {
        $data = $this->attributes;
        
        // Remove hidden attributes
        foreach ($this->hidden as $hidden) {
            unset($data[$hidden]);
        }
        
        return $data;
    }
    
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
```

### Entity Models

**User Model** - User entity representation:

```php
class User extends BaseModel
{
    protected array $fillable = [
        'username', 'email', 'first_name', 'last_name',
        'role', 'department', 'rfid_tag', 'is_active'
    ];
    
    protected array $hidden = ['password'];
    
    protected array $casts = [
        'is_active' => 'boolean',
        'email_verified' => 'boolean',
        'last_login' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    public function hasPermission(string $permission): bool
    {
        // Permission checking logic
        return in_array($permission, $this->getPermissions());
    }
    
    public function getPermissions(): array
    {
        // Return permissions based on role
        switch ($this->role) {
            case 'admin':
                return ['*']; // All permissions
            case 'moderator':
                return ['events.manage', 'users.view', 'reports.generate'];
            case 'user':
                return ['checkin', 'profile.edit'];
            default:
                return [];
        }
    }
}
```

**Event Model** - Event entity representation:

```php
class Event extends BaseModel
{
    protected array $fillable = [
        'name', 'description', 'location', 'event_type',
        'start_date', 'end_date', 'start_time', 'end_time',
        'is_recurring', 'recurrence_type', 'capacity', 'active'
    ];
    
    protected array $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'time',
        'end_time' => 'time',
        'is_recurring' => 'boolean',
        'active' => 'boolean',
        'capacity' => 'integer'
    ];
    
    public function isActive(): bool
    {
        return $this->active && $this->end_date >= date('Y-m-d');
    }
    
    public function isToday(): bool
    {
        return $this->start_date === date('Y-m-d');
    }
    
    public function getStatusAttribute(): string
    {
        if (!$this->active) return 'inactive';
        if ($this->start_date > date('Y-m-d')) return 'upcoming';
        if ($this->end_date < date('Y-m-d')) return 'completed';
        return 'active';
    }
}
```

## 🛠️ Services

### Core Services

**ConfigurationService** - Centralized configuration management:

```php
class ConfigurationService
{
    private static ?ConfigurationService $instance = null;
    private array $config = [];
    private string $environment;
    
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
    
    public function set(string $key, mixed $value): void
    {
        data_set($this->config, $key, $value);
    }
    
    public function has(string $key): bool
    {
        return data_get($this->config, $key) !== null;
    }
    
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    public function isDebugMode(): bool
    {
        return $this->get('app.debug', false);
    }
    
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
```

**DatabaseService** - Database operations and connection management:

```php
class DatabaseService
{
    private static ?DatabaseService $instance = null;
    private PDO $connection;
    private array $queryLog = [];
    private LoggingService $logger;
    
    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $executionTime = microtime(true) - $startTime;
            $this->logQuery($sql, $params, $executionTime);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logger->error('Database query failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new DatabaseException($e->getMessage(), 0, $e);
        }
    }
    
    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->connection->commit();
            return $result;
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
    
    public function getQueryCount(): int
    {
        return count($this->queryLog);
    }
}
```

**AuthenticationService** - User authentication and authorization:

```php
class AuthenticationService
{
    private UserRepository $userRepository;
    private SecurityService $security;
    private LoggingService $logger;
    
    public function authenticate(string $username, string $password): array
    {
        $user = $this->userRepository->findByUsername($username);
        
        if (!$user || !$this->security->verifyPassword($password, $user['password'])) {
            $this->logger->warning('Failed login attempt', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            throw new AuthenticationException('Invalid credentials');
        }
        
        if (!$user['is_active']) {
            throw new AuthenticationException('Account is disabled');
        }
        
        $this->updateLastLogin($user['user_id']);
        $this->startSession($user);
        
        return $user;
    }
    
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && $this->validateSession();
    }
    
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->userRepository->findById($_SESSION['user_id']);
    }
    
    public function logout(): void
    {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
}
```

## 🚪 Middleware

### Middleware System

**MiddlewareManager** - Coordinates middleware execution:

```php
class MiddlewareManager
{
    private array $middleware = [];
    private array $groups = [];
    
    public function add(string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }
    
    public function group(string $name, array $middleware): self
    {
        $this->groups[$name] = $middleware;
        return $this;
    }
    
    public function handle(Request $request, callable $next): Response
    {
        $middleware = $this->resolveMiddleware();
        
        return $this->executeMiddleware($middleware, $request, $next);
    }
    
    private function executeMiddleware(array $middleware, Request $request, callable $next): Response
    {
        if (empty($middleware)) {
            return $next($request);
        }
        
        $current = array_shift($middleware);
        
        return $current->handle($request, function($request) use ($middleware, $next) {
            return $this->executeMiddleware($middleware, $request, $next);
        });
    }
}
```

### Security Middleware

**AuthMiddleware** - Authentication enforcement:

```php
class AuthMiddleware implements MiddlewareInterface
{
    private AuthenticationService $auth;
    
    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->isAuthenticated()) {
            if ($request->isApiRequest()) {
                return new JsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            return new RedirectResponse('/auth/login');
        }
        
        return $next($request);
    }
}
```

**RateLimitMiddleware** - Request rate limiting:

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    private CacheService $cache;
    
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->generateKey($request);
        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $this->getLimit($request)) {
            return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
        }
        
        $this->cache->set($key, $attempts + 1, $this->getWindow());
        
        return $next($request);
    }
    
    private function getLimit(Request $request): int
    {
        // Different limits for different endpoints
        if ($request->isApiRequest()) {
            return 60; // 60 requests per minute for API
        }
        
        return 20; // 20 requests per minute for web
    }
}
```

## 🛣️ Routing

### Router System

**Router** - URL routing and dispatch:

```php
class Router
{
    private array $routes = [];
    private MiddlewareManager $middleware;
    
    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }
    
    public function group(array $attributes, callable $callback): void
    {
        $group = new RouteGroup($attributes);
        $callback($group);
        
        foreach ($group->getRoutes() as $route) {
            $this->routes[] = $route;
        }
    }
    
    public function dispatch(): void
    {
        $request = Request::createFromGlobals();
        $route = $this->matchRoute($request);
        
        if (!$route) {
            throw new RouteNotFoundException();
        }
        
        $response = $this->middleware->handle($request, function($request) use ($route) {
            return $route->handle($request);
        });
        
        $response->send();
    }
}
```

**Route Definition Examples:**

```php
// API routes
$router->group(['prefix' => '/api', 'middleware' => ['api', 'auth']], function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
    $router->post('/checkin', [RfidController::class, 'checkin']);
    $router->get('/events', [EventController::class, 'index']);
    $router->post('/events', [EventController::class, 'store']);
});

// Web routes
$router->group(['middleware' => ['web']], function($router) {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'show'])->middleware('auth');
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
});
```

## 📝 Views and Templates

### Template System

**BaseView** - Template rendering foundation:

```php
class BaseView
{
    private string $templatePath;
    private array $globals = [];
    
    public function render(string $template, array $data = []): void
    {
        $templateFile = $this->templatePath . '/' . $template . '.php';
        
        if (!file_exists($templateFile)) {
            throw new TemplateNotFoundException($template);
        }
        
        $data = array_merge($this->globals, $data);
        
        extract($data, EXTR_SKIP);
        include $templateFile;
    }
    
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }
    
    public function component(string $component, array $data = []): void
    {
        $this->render("components/{$component}", $data);
    }
}
```

## 🧪 Testing Integration

### Test Support

**TestCase Base Class:**

```php
abstract class TestCase
{
    protected Application $app;
    protected DatabaseService $db;
    
    protected function setUp(): void
    {
        $this->app = new Application();
        $this->db = DatabaseService::getInstance();
        $this->db->beginTransaction();
    }
    
    protected function tearDown(): void
    {
        $this->db->rollBack();
    }
    
    protected function actingAs(User $user): self
    {
        $_SESSION['user_id'] = $user->id;
        return $this;
    }
    
    protected function makeRequest(string $method, string $uri, array $data = []): Response
    {
        $request = new Request($method, $uri, $data);
        return $this->app->handle($request);
    }
}
```

## 🚀 Performance Optimization

### Caching Strategy

**Multi-level Caching:**

```php
class PerformanceCacheService
{
    private array $memoryCache = [];
    private FileCache $fileCache;
    private RedisCache $redisCache;
    
    public function get(string $key): mixed
    {
        // L1: Memory cache
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }
        
        // L2: Redis cache
        if ($this->redisCache->has($key)) {
            $value = $this->redisCache->get($key);
            $this->memoryCache[$key] = $value;
            return $value;
        }
        
        // L3: File cache
        if ($this->fileCache->has($key)) {
            $value = $this->fileCache->get($key);
            $this->redisCache->set($key, $value, 3600);
            $this->memoryCache[$key] = $value;
            return $value;
        }
        
        return null;
    }
    
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
}
```

### Database Optimization

**Query Builder with Optimization:**

```php
class QueryBuilder
{
    private DatabaseOptimizationService $optimizer;
    
    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    public function where(string $column, $operator, $value = null): self
    {
        $this->wheres[] = compact('column', 'operator', 'value');
        return $this;
    }
    
    public function get(): array
    {
        $sql = $this->toSql();
        $params = $this->getBindings();
        
        // Optimize query before execution
        $optimized = $this->optimizer->optimizeQuery($sql, $params);
        
        return $this->db->query($optimized['sql'], $optimized['params'])->fetchAll();
    }
}
```

## 📚 Development Guidelines

### Coding Standards

**PSR-12 Compliance:**
- Follow PSR-12 coding style
- Use proper namespacing
- Document all public methods
- Use type declarations

**SOLID Principles:**
- Single Responsibility Principle
- Open/Closed Principle
- Liskov Substitution Principle
- Interface Segregation Principle
- Dependency Inversion Principle

### Error Handling

**Custom Exceptions:**

```php
class ValidationException extends BaseException
{
    private array $errors;
    
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

### Security Best Practices

**Input Validation:**
```php
class ValidationService
{
    public function validate(array $data, array $rules): array
    {
        $validator = new Validator($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        
        return $validator->validated();
    }
}
```

---

**Modern Architecture**: Production Ready  
**Last Updated**: January 2025  
**PHP Version**: 8.0+  
**Architecture Pattern**: MVC + Service Layer + Repository Pattern