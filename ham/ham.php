<?php
/**
 * @author  James Cleveland <jc@blackflags.co.uk>  Ryan Liu <azhai@126.com>
 * @version 0.2
 *
 * 简化自James Cleveland的Ham
 *
 * Usage:
 * route('/<int>', 'BlogView', array('get', 'post'));
 * run();
 *
 * class BlogView extends View
 * {
 *      public $blogs;
 *
 *      public function prepare()
 *      {
 *          $this->blogs = array();
 *      }
 *
 *      public function get($id=0)
 *      {
 *          $blog = isset($this->blogs[$id]) ? $this->blogs[$id] : null;
 *          return $blog;
 *      }
 *
 * }
 */


/*路由设置*/
function route($url, $handler, $methods=null)
{
    $router = Router::$current_router;
    if (is_null($router)) {
        $router = Router::detect();
    }
    $router->add($url, $handler, $methods);
}


/*页面跳转*/
function redirect($url, $code=301)
{
    if ($code == 301) { //永久跳转
        if (true) { //TODO:网站内部跳转
            return run(null, $url);
        }
        @header('HTTP/1.1 301 Moved Permanently');
    }
    @header('Location: ' . $url);
}


/*发送HTTP错误*/
function abort($code=500, $message='')
{
    if(php_sapi_name() != 'cli') {
        //@header('Content-Type: application/xhtml+xml; charset=UTF-8');
        @header("Status: {$code}", false, $code);
    }
    $response = "<h1>{$code}</h1><p>{$message}</p>";
    die($response);
}


/*运行程序，分发路由，输出内容*/
function run(Router $root=null, $url=false, $method=false)
{
    if (is_null($root)) {
        $root = Router::detect();
    }
    if ($url === false) {
        $url = $_SERVER['REQUEST_URI'];;
    }
    if ($method === false) {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
    }
    //找到URL对应的输出
    $url_pics = parse_url(str_replace($root->prefix, '', $url));
    $url = is_array($url_pics) ? $url_pics['path'] : '/';
    $response = $root->match($url, $method);
    if (! is_null($response)) {
        die($response);
    }
    else {
        abort(404);
    }
}


/**
 * 路由器
 */
class Router
{
    public static $route_types = array(
        '<int>' => '([0-9\-]+)',
        '<float>' => '([0-9\.\-]+)',
        '<string>' => '([a-zA-Z0-9\-_]+)',
        '<page>' => '([0-9]*)\/?([0-9]*)\/?',
        '<path>' => '([a-zA-Z0-9\-_\/])',
    );
    public static $current_router = null;
    protected static $modules = array();
    protected $routes = array();
    public $prefix = '';
    public $filename = '';
    
    public static function detect($filename=false)
    {
        if ($filename === false) {
            $filename = 'root';
        }
        if (! isset(self::$modules[$filename])) {
            $router = new self();
            $router->filename = $filename;
            self::$modules[$filename] = $router;
        }
        return self::$modules[$filename];
    }
    
    public function glob($directory)
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php'));
        foreach ($files as $filename) {
            if (is_file($filename)) {
                $prefix = '/' . basename($filename, '.php');
                $module = self::detect($filename);
                $this->addModule($prefix, $module);
            }
        }
        self::$current_router = $this;
        return $this;
    }
    
    public static function compileUrl($url, $wildcard=false)
    {
        $url = rtrim($url, '/');
        $url = str_replace('/', '\/', preg_quote($url));
        $keys = array_map('preg_quote', array_keys(self::$route_types));
        $values = array_values(self::$route_types);
        $url = str_replace($keys, $values, $url);
        $wildcard = ($wildcard === false) ? '' : '(.*)?';
        return '/^' . $url . '\/?' . $wildcard . '$/';
    }
    
    public function addModule($prefix, $module)
    {
        if (isset($module)) {
            $module->prefix = rtrim($prefix, '/');
            $prefix = str_replace('/', '\/', preg_quote($prefix));
            $route_key = '/^' . $prefix . '\/?(.*)?$/';
            $this->routes[$route_key] = $module;
            return $module;
        }
    }

    public function add($url, $handler, array $methods=null)
    {
        if (is_null($methods)) {
            if ($handler instanceof View) {
                $methods = array_diff(get_class_methods($handler), array(
                    '__construct', get_class($handler), 'prepare',
                ));
            }
            else {
                $methods = array('get', 'post', 'head');
            }
        }
        $methods = array_map('strtolower', $methods);
        $route_key = self::compileUrl($url);
        $this->routes[$route_key] = array($handler, $methods);
    }

    public function match($url, $method='get')
    {
        foreach ($this->routes as $route_key => $route) {
            if (preg_match($route_key, $url, $params) !== 1) {
                continue;
            }
            if ($route instanceof self) {
                return $this->matchRouter($route, $url, $method);
            }
            else {
                list($handler, $methods) = $route;
                if (is_null($methods) || in_array($method, $methods)) {
                    if (is_subclass_of($handler, 'View')) {
                        $handler = array($this->initView($handler), $method);
                    }
                    array_shift($params); //丢掉第一个元素，完整匹配的URL
                    if (empty($params)) { //保留函数默认值
                        return call_user_func($handler);
                    }
                    else {
                        return call_user_func_array($handler, $params);
                    }
                }
            }
        }
    }
    
    protected function matchRouter($route, $url, $method)
    {
        $inner_url = substr($url, strlen($route->prefix));
        if ($inner_url !== false) {
            self::$current_router = $route;
            require_once $route->filename;
            return $route->match($inner_url, $method);
        }
    }
    
    protected function initView($handler)
    {
        if (! is_object($handler)) {
            $handler = new $handler();
        }
        if (method_exists($handler, 'prepare')) {
            $handler->prepare();
        }
        return $handler;
    }
}


/**
 * 控制器
 */
class View
{
    public function prepare()
    {
    }
}

?>