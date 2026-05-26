<?php
/**
 * Global extension for common functions and utilities
 * This contains all AreaCore v1 functions for compatibility with older software
 */

if (php_sapi_name() != "cli"){ // JUST FOR WEB MODE
    ob_start();
    session_name("AREA_CORE");
    session_set_cookie_params(
        ['path' => (base ?: "/"),
            // 'domain'=> parse_url("https://".siteAddress, PHP_URL_HOST),
            // 'secure' => true,  // Use just with https
            // 'httponly'=> true,
            // 'samesite'=> 'Strict', // Strict=> Self Domain, Lax=> Just for method==get, None=>all method and domain are allowed
        ]
    );
    session_start(['cookie_lifetime' => 86400]);
}


function set_cookie($name, $value, $expire = 0, $path = (base?:"/"), $domain=siteAddress, $secure=true, $httponly=true, $samesite="Strict"): bool
{
    if (!preg_match('~^https?://~i', $domain))
        $domain ='https://'.$domain;
    $cookieOptions = [
        'expires' => $expire,
        'path' => $path,
        // 'domain' => parse_url($domain, PHP_URL_HOST), // leading dot for compatibility or use subdomain
        // 'secure' => $secure,     // true or false
        // 'httponly' => $httponly,    // true or false
        // 'samesite' => $samesite // None || Lax || Strict
    ];
    return setcookie($name, $value, $cookieOptions);
}

$get = $_GET;
$post = $_POST;

### Parse the url
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