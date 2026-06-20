<?php

class Router
{
    private ?string $method = "";
    private static ?array $def = [];
    private static ?array $path = [];
    public static ?array $var = [];
    public static bool $ALLOW_MULTIPLE = false;
    public static bool $RUN_ACTION = false;
    public static array $uri = [];
    public static string $requestMethod = "";
    public static array $allowedMethods = [];
    private array $tempVar = [];
    private bool $isMatch = true;
    
    ## Define named routes
    
    static function define($name, $routeUri): void
    {
        if (str_starts_with($name, ":")){ // for folder
            self::$path[$name] = $routeUri;
        }else{ // For url
            self::$def[$name] = $routeUri;
        }
        self::requested_uri();
    }
    
    
    static function prefix($name): string
    {
        return self::$def[$name];
    }
    
    ## Supported methods
    
    static function any($uri): Router
    {
        return self::build('any', $uri);
    }
    
    static function cli($uri): Router
    {
        return self::build('cli', $uri);
    }
    
    static function get($uri): Router
    {
        return self::build('get', $uri);
    }
    
    static function post($uri): Router
    {
        return self::build('post', $uri);
    }
    
    static function delete($uri): Router
    {
        return self::build('delete', $uri);
    }
    
    static function put($uri): Router
    {
        return self::build('put', $uri);
    }
    
    static function patch($uri): Router
    {
        return self::build('patch', $uri);
    }
    
    static function options($uri): Router
    {
        return self::build('options', $uri);
    }
    
    static function fallBack(): Router
    {
        $item = new self();
        $item->method = 'fallBack';
        return $item;
    }
    
    private static function build(string $method, $uri): self
    {
        if (!in_array($method, ["cli", "options"]))
            self::$allowedMethods[$uri][] = $method;
        
        $item = new self();
        
        if (self::$RUN_ACTION && !self::$ALLOW_MULTIPLE){
            // Allow to define actions but don't run them if already ran an action before
            $item->isMatch = false;
            return $item;
        }
        
        $item->method = $method;
        self::requested_uri();
        $item->isMatch = $item->is_match($uri);
        $item->query_parameter($uri);
        return $item;
    }
    
    ## variables
    private static function requested_uri()
    {
        if (self::$uri)
            return;
        $uri = $_SERVER['REQUEST_URI'] ?? "";
        if ($uri){
            self::$requestMethod = $_SERVER['REQUEST_METHOD'];
            $uri = urldecode($uri);
            $uri = substr($uri, mb_strlen(base));
            $uri = strtolower($uri);
            $uri = substr($uri, 0, strpos($uri, '?')?:strlen($uri));
            while (str_contains($uri, "//"))
                $uri = str_replace("//","/",$uri);
            $uri = explode("/", $uri);
        }else{ // CLI MODE
            self::$requestMethod = "CLI";
            $uri = $_SERVER["argv"] ?? [];
        }
        self::$uri = $uri;
    }
    
    private function resolve_definition($pattern)
    {
        return preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
            // $matches[0] equal `[foo]`
            // $matches[1] equal `foo`
            $key = $matches[1];
            if (in_array($matches[1], ['+', '*']))
                return $matches[0];
            // Chek if defined
            if (isset(self::$def[$key]))
                return self::$def[$key];
            
            // Return without change if not defined
            return $matches[0];
        }, $pattern);
    }
    
    private function resolve_paths($pattern)
    {
        return preg_replace_callback('/\:([a-zA-Z0-9_]+)/', function ($matches){
            // $matches[0] = :foo
            // $matches[1] = foo
            $key = $matches[0];
            // پیدا کردن مقدار در path (associative match)
            if (isset(self::$path[$key])) {
                return self::$path[$key];
            }
            
            // اگر تعریف نشده بود همان را برگردان
            return $matches[0];
        }, $pattern);
    }
    
    private function is_match($pattern): bool
    {
        if (!$this->isMatch)
            return false;
        
        $pattern = $this->resolve_definition($pattern);
        if (!str_starts_with($pattern, "/"))
            $pattern = "/".$pattern;
        // Ignore GET parameters on pattern, Actually, we'll check it out later
        $pattern = substr($pattern, 0, strpos($pattern, '?')?:strlen($pattern));
        $pattern = explode("/", $pattern);
        
        $hasWildcard = false;
        
        foreach ($pattern as $key=>$item){
            if ($key===0) // Don't check first uri because it is `index.php` in cli mode
                continue;
            
            $equivalent = @self::$uri[$key]; // Equivalent matched part in requested URI
            
            // Patter using variables {bar}
            if (str_starts_with($item, "{") && str_ends_with($item, "}")){
                $this->append_var($item, $equivalent);
            }elseif ($item == "[+]")
                continue;
            elseif ($item == "[*]"){
                $hasWildcard = true;
            } elseif (strcasecmp($item, $equivalent) !== 0 && !$hasWildcard){
                return false;
            }
        }
        return true;
    }
    
    private function query_parameter($pattern)
    {
        if (!$this->isMatch)
            return;
        
        $parse = parse_url($pattern);
        if (isset($parse['query'])){
            parse_str($parse['query'], $query);
            foreach ($query as $key=>$item){
                $this->append_var($item, $_GET[$key] ?? null);
            }
        }
    }
    
    private function append_var(string $varInPattern, mixed $equivalentValue): bool
    {
        $item = trim($varInPattern, "{}");
        if ($item == "")
            return false;
        
        $parts = explode(".", $item);
        
        // reference to root
        $current = &$this->tempVar;
        
        // ساخت لایه‌های میانی
        while (count($parts) > 1) {
            $part = array_shift($parts);
            
            // Generate array if not exist
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            
            // Goto next reference
            $current = &$current[$part];
        }
        
        // Set final value
        $lastPart = array_shift($parts);
        $current[$lastPart] = $equivalentValue;
        return true;
    }
    
    ## Actions
    public function page($fileAddress): Router
    {
        // Doesn't allow more than one action
        if (!$this->check_conditions())
            return $this;
        
        $fileAddress = $this->resolve_paths($fileAddress);
        
        if (!str_starts_with('/', $fileAddress) && !str_starts_with($fileAddress, "./") && !str_starts_with($fileAddress, "../")) {
            $fileAddress = "view/" . $fileAddress;
        }
        if (file_exists($fileAddress)) {
            include $fileAddress;
        }elseif (file_exists("$fileAddress.php")) {
            include "$fileAddress.php";
        }else{ // Not found
            if (function_exists('router_file_not_found')) {
                router_file_not_found();
                return $this;
            }else
                die("Could not load page file");
        }
        self::$RUN_ACTION = true;
        return $this;
    }
    public function call(callable $callback): Router
    {
        if (!$this->check_conditions())
            return $this;
        
        self::$RUN_ACTION = true;
        
        // CASE 1: string "Class::method"
        if (is_string($callback) && str_contains($callback, '::')) {
            
            [$class, $method] = explode('::', $callback);
            
            $ref = new ReflectionMethod($class, $method);
            $params = $this->resolve_params($ref);
            
            $ref->invoke(null, ...$params);
            return $this;
        }
        
        // CASE 2: closure or function
        if (is_string($callback) || $callback instanceof Closure) {
            
            $ref = new ReflectionFunction($callback);
            $params = $this->resolve_params($ref);
            
            $ref->invokeArgs($params);
            return $this;
        }
        
        // CASE 3: [object, method]
        if (is_array($callback)) {
            
            $ref = new ReflectionMethod($callback[0], $callback[1]);
            $params = $this->resolve_params($ref);
            
            $ref->invokeArgs($callback[0], $params);
            return $this;
        }
        
        // fallback
        $callback(self::$var);
        
        return $this;
    }
    
    private function resolve_params($ref): array
    {
        $params = [];
        
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            
            $params[] = self::$var[$name] ?? null;
        }
        
        return $params;
    }
    
    private function check_conditions(): bool
    {
        if ($this->method == "fallBack")
            return !self::$RUN_ACTION;
        
        $inCondition = true;
        if (!$this->isMatch)
            $inCondition = false;
        if (!in_array($this->method, ['any', 'cli']) && self::$requestMethod!="CLI" && $this->method != strtolower(self::$requestMethod))
            $inCondition = false;
        if ($this->method == 'cli' && php_sapi_name() != 'cli')
            $inCondition = false;
        
        if (!$inCondition){
            self::$var = [];
            return false;
        }
        self::$var = $this->tempVar;
        return true;
    }
    
    public static function allowed_methods(): array
    {
        $allowedMethods = [];
        foreach (Router::$allowedMethods as $url=>$methodsArray) {
            $item = new self();
            if ($item->is_match($url))
                array_push($allowedMethods, ...$methodsArray);
        }
        
        foreach ($allowedMethods as &$method) {
            if ($method == "any")
                array_push($allowedMethods, "GET", "POST", "DELETE", "PUT", "PATCH");
            else
                $method = strtoupper($method);
        }
        $allowedMethods = array_unique($allowedMethods);
        $allowedMethods = array_diff($allowedMethods, ['any']);
        return array_diff($allowedMethods, ['any']);
    }
}
