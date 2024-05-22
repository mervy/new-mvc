<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../vendor/autoload.php');

$routes = [
    'GET' => [
        '/' => 'HomeController@index',
        '/blog' => 'BlogController@index',
        '/blog/show' => 'BlogController@show',
        '/login' => 'LoginController@index',
        '/blog/cat/:category' => 'BlogController@category',
        '/blog/item/:id' => 'BlogController@item',
        '/show/:category/:title/:id' => 'ShowController@detail',
        '/test' => 'TestController@index::auth',
        '/dashboard' => 'DashBoardController@index::auth',
    ],
    'POST' => [
        '/dashboard/update' => 'DashBoardController@update::auth',
        '/dashboard/insert' => 'DashBoardController@insert::auth',
    ],
    'DELETE' => [
        '/dashboard/delete' => 'DashBoardController@delete::auth',
    ]
];

class RouteHelper
{
    protected $routes;
    protected $method;
    protected $path;
    protected $params = [];
    protected $matchedRoute = null;

    public function __construct($routes)
    {
        $this->routes = $routes;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = $this->getPath();
    }

    public function getPath()
    {
        return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    }

    private function matchRoute($route, $path)
    {
        $routeParts = explode('/', $route);
        $pathParts = explode('/', $path);

        if (count($routeParts) !== count($pathParts)) {
            return false;
        }

        $params = [];
        foreach ($routeParts as $index => $part) {
            if (strpos($part, ':') === 0) {
                $params[substr($part, 1)] = $pathParts[$index];
            } elseif ($part !== $pathParts[$index]) {
                return false;
            }
        }

        $this->params = $params;
        $this->matchedRoute = $route;
        return true;
    }

    public function getControllerAction()
    {
        if (isset($this->routes[$this->method])) {
            foreach ($this->routes[$this->method] as $route => $controllerAction) {
                $isAuthRequired = strpos($controllerAction, '::auth') !== false;
                $controllerAction = str_replace('::auth', '', $controllerAction);
                if ($this->matchRoute($route, $this->path)) {
                    return ['controllerAction' => $controllerAction, 'auth' => $isAuthRequired];
                }
            }
        }
        return null;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getMatchedRoute()
    {
        return $this->matchedRoute;
    }

    public function getPathParts()
    {
        return explode('/', $this->path);
    }

    public function getRouteParts()
    {
        return $this->matchedRoute ? explode('/', $this->matchedRoute) : [];
    }
}

class Controller
{
    protected $routes;
    protected $url;

    public function __construct($routes)
    {
        $this->routes = $routes;
        $this->url = $this->getURL();
    }

    private function getURL()
    {
        return parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    }

    private function getRoutes()
    {
        return $this->routes;
    }

    public function run()
    {
        return [
            'url' => $this->url,
            'routes' => $this->getRoutes()
        ];
    }
}

class HomeController extends Controller
{
    public function index()
    {
        echo "Home Controller";
        return $this->run();
    }
}

class TestController extends Controller
{
    public function index()
    {
        echo "Test Controller com AUTH";
        return $this->run();
    }
}

class BlogController extends Controller
{
    public function index()
    {
        echo "Blog Index";
    }

    public function show()
    {
        echo "Blog Show";
    }

    public function category($category)
    {
        echo "Blog Category: " . htmlspecialchars($category);
    }

    public function item($id)
    {
        echo "Blog Item ID: " . htmlspecialchars($id);
    }

    public function insert()
    {
        echo "Insert Blog Item";
    }
}

class DashBoardController extends Controller
{
    public function index()
    {
        echo "Dashboard";
    }
    public function update()
    {
        echo "Update Dashboard";
    }

    public function delete()
    {
        echo "Delete from Dashboard";
    }
}

class LoginController extends Controller
{
    public function index()
    {
        echo '<form method="POST" action="/login">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username"><br><br>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password"><br><br>
                <input type="submit" value="Login">
              </form>';
    }

    public function authenticate($users)
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            $_SESSION['user'] = $username;
            echo "Login successful!";
            header("Location: /dashboard");
        } else {
            echo "Invalid username or password.";
        }
    }
}

class ShowController extends Controller
{
    public function detail($category, $title, $id)
    {
        echo "Show Details: Category - " . htmlspecialchars($category) . ", Title - " . htmlspecialchars($title) . ", ID - " . htmlspecialchars($id);
    }
}

// Users array with hashed passwords
$users = [
    'user1' => password_hash('password1', PASSWORD_DEFAULT),
    'user2' => password_hash('password2', PASSWORD_DEFAULT),
    'admin' => password_hash('admin123', PASSWORD_DEFAULT)
];

// Start session
session_start();

// Check authentication
function isAuthenticated()
{
    return isset($_SESSION['user']);
}

// Usage
try {
    $routeHelper = new RouteHelper($routes);
    $controllerActionData = $routeHelper->getControllerAction();
    $params = $routeHelper->getParams();

    if ($controllerActionData) {
        $controllerAction = $controllerActionData['controllerAction'];
        $isAuthRequired = $controllerActionData['auth'];

        if ($isAuthRequired && !isAuthenticated()) {
            $loginController = new LoginController($routes);
            $loginController->index();
            exit;
        }

        list($controller, $action) = explode('@', $controllerAction);
        if (!class_exists($controller)) {
            throw new Exception("Controller '$controller' not found.");
        }
        // Instantiate the controller and call the action
        $controllerInstance = new $controller($routes);
        if (!method_exists($controllerInstance, $action)) {
            throw new Exception("Method '$action' not found in controller '$controller'.");
        }
        call_user_func_array([$controllerInstance, $action], $params);
    } else {
        throw new Exception("Route not found.");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Debugging output
echo '<br><b>Current URL:</b>', $routeHelper->getPath();
echo '<br><b>Matched Route:</b>', $routeHelper->getMatchedRoute();
var_dump('URL Parts:', $routeHelper->getPathParts());
var_dump('Route Parts:', $routeHelper->getRouteParts());
echo '<br><b>HTTP Method:</b></b>', $routeHelper->getMethod();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) === '/login') {
    (new LoginController($routes))->authenticate($users);
}