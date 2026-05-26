<?php

class Router
{
    private ?string $method = "";
    private static ?array $definition = [];
    public static ?array $var = [];
    public static bool $ALLOW_MULTIPLE = false;
    public static bool $RUN_ACTION = false;
    private static ?array $uri = null;
    private bool $isMatch = true;
    
    ## Define named routes
    
    static function define($name, $routeUri): void
    {
        self::$definition[$name] = $routeUri;
    }
    
    ## Supported methods
    
    static function any($uri)
    {
        return self::build('any', $uri);
    }
    
    static function cli($uri)
    {
        return self::build('any', $uri);
    }
    
    static function get($uri)
    {
        return self::build('any', $uri);
    }
    
    static function post($uri)
    {
        return self::build('any', $uri);
    }
    
    static function delete($uri)
    {
        return self::build('any', $uri);
    }
    
    static function put($uri)
    {
        return self::build('any', $uri);
    }
    
    static function patch($uri)
    {
        return self::build('any', $uri);
    }
    
    private static function build(string $method, $uri): self
    {
        $item = new self();
        
        if (self::$RUN_ACTION && !self::$ALLOW_MULTIPLE){
            // Allow to define actions but don't run them if already ran an action before
            $item->isMatch = false;
            return $item;
        }
        
        $item->method = $method;
        $item->requested_uri();
        $item->is_match($uri);
        $item->query_parameter($uri);
        return $item;
    }
    
    ## variables
    private function requested_uri()
    {
        if (!is_null(self::$uri))
            return;
        $uri = @$_SERVER['REQUEST_URI'];
        if ($uri){
            $uri = urldecode($uri);
            $uri = substr($uri, mb_strlen(base));
            $uri = strtolower($uri);
            $uri = substr($uri, 0, strpos($uri, '?')?:strlen($uri));
            while (str_contains($uri, "//"))
                $uri = str_replace("//","/",$uri);
            $uri = explode("/", $uri);
        }else // CLI MODE
            $uri = $argv ?? [];
        self::$uri = $uri;
    }
    
    private function resolve_definition($pattern)
    {
        return preg_replace_callback('/\[([^\]]+)\]/', function ($matches) {
            // $matches[0] equal `[foo]`
            // $matches[1] equal `foo`
            $key = $matches[1];
            
            // Chek if defined
            if (isset(self::$definition[$key]))
                return self::$definition[$key];;
            
            // Return without change if not defined
            return $matches[0];
        }, $pattern);
    }
    
    private function is_match($pattern)
    {
        $pattern = $this->resolve_definition($pattern);
        // Ignore GET parameters on pattern, Actually, we'll check it out later
        $pattern = substr($pattern, 0, strpos($pattern, '?')?:strlen($pattern));
        $pattern = explode("/", $pattern);
        
        foreach ($pattern as $key=>$item){
            $equivalent = @self::$uri[$key]; // Equivalent matched part in requested URI
            
            if (!$this->isMatch)
                return false;
            
            // Patter using variables {bar}
            if (str_starts_with($item, "{") && str_ends_with($item, "}")){
                $this->append_var($item, $equivalent);
            }elseif ($item != $equivalent){
                $this->isMatch = false;
                return false;
            }
        }
        return true;
    }
    
    private function query_parameter($pattern)
    {
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
        $current = &self::$var;
        
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
    public function page($fileAddress): void
    {
        // Doesn't allow more than one action
        if (!$this->isMatch)
            return;
        if (!str_starts_with('/', $fileAddress)) {
            $fileAddress = "view/" . $fileAddress;
        }
        if (file_exists($fileAddress)) {
            include $fileAddress;
        }elseif (file_exists("$fileAddress.php")) {
            include "$fileAddress.php";
        }
    }
    
    
}
