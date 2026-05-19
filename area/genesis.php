<?php
namespace AreaCore;

const _ = null;
const AreaCore = "v2.0.1";

if (php_sapi_name() == "cli"){ // CLI MODE
    define('EOL', PHP_EOL);
    define("protocol", __DIR__);
} else{ // Web MODE
    define('EOL', '<br>');
    define("protocol", (@$_SERVER['REQUEST_SCHEME']?:strtolower(explode('/', $_SERVER['SERVER_PROTOCOL'])[0]))."://");
}

if (version_compare(PHP_VERSION, '8.1', '<')) {
    die("PHP version is not supported. (" . PHP_VERSION . ")");
}

Genesis::init();

class Genesis
{
    static function init()
    {
        self::load_orm();
        self::load_extensions();
        self::load_model();
    }
    
    static function load_orm(){
        $ormDir = __DIR__.'/ORM/';
        if (is_dir($ormDir)) {
            foreach (glob($ormDir . '*.orm.php') as $ormFile) {
                // Use require_once to avoid double-including the same file
                require_once $ormFile;
            }
        }
    }
    
    static function load_extensions()
    {
        $extensionsDir = __DIR__.'/extensions/';
        if (is_dir($extensionsDir)) {
            foreach (glob($extensionsDir . '*.ext.php') as $extensionFile) {
                // Use require_once to avoid double-including the same file
                require_once $extensionFile;
            }
        }
    }
    
    static function load_model()
    {
        spl_autoload_register(function ($class) {
            $normalizedClass = str_replace("\\", "/", $class); // For supporting namespace
            $path = __DIR__ . "/../model/";
            $extensions = [".php", ".module", ".inc", ".class.php"];
            // Find by main filename if exist
            foreach ($extensions as $ext) {
                if (file_exists($file = $path . $normalizedClass . $ext)) {
                    include_once $file;
                    return;
                }
            }
            // Also lowercase check because Linux/Unix system are sensitive-case
            $lowerClass = strtolower($normalizedClass);
            foreach ($extensions as $ext) {
                if (file_exists($file = $path . $lowerClass . $ext)) {
                    include_once $file;
                    return;
                }
            }
        });
    }
}
