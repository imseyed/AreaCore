<?php


/**
 * Replaces newline characters with HTML line breaks ("<br/>").
 *
 * @param string $text The input text to process.
 * @return string The text with newline characters converted to HTML line breaks.
 */
function enter_to_br(string $text): string
{
	return str_replace("\n", "<br/>", str_replace("\r", "<br/>", str_replace("\r\n", "<br/>", $text)));
}

/**
 * Removes newline characters from the input text.
 *
 * @param string $text The input text to process.
 * @return string The text with newline characters removed.
 */
function enter_to_null(string $text): string
{
	return str_replace("\n", "", str_replace("\r", "", str_replace("\r\n", "", $text)));
}

/**
 * Splits a string into an array based on the specified separator, handling different newline formats.
 *
 * @param string $string The input string to split.
 * @param string $separator The separator used to split the string (default is "\n").
 *
 * @return array An array containing the elements after splitting the string.
 */

function determiner(string $string = "", string $separator = "\n"): array
{
	// Normalize newline characters in the string
	if ($separator == "\r\n") {
		$separator = "\n";
	}
	if ($separator == "\r") {
		$separator = "\n";
	}
	
	$string = str_replace("\r\n", "\n", $string);
	$string = str_replace("\r", "\n", $string);
	
	// Filter and explode the string based on the specified separator
	return array_filter(explode($separator, $string));
}

/**
 * Search for a specific value in a 2D array based on a specified key.
 *
 * @param string $key The key to search for in the array (default is 'ID').
 * @param mixed $needle The value to search for.
 * @param array|null $array The 2D array to search within.
 * @param bool $DD Whether to export all existing rows in the result (default is false).
 * @param bool $reIndex Whether to reindex the result array (default is false).
 *
 * @return array The result of the search, filtered based on the specified key and value.
 */
function search_2D(string $key, mixed $needle, ?array $array, bool $DD = false, bool $reIndex = false): array
{
	if (!is_array($array) || count($array) == 0)
		return [];
	
	if (!$key)
		$key = 'ID';
	
	// $DD: 2D Export (all existing rows)
	$keys = array_keys(array_column($array, $key), $needle);
	$array = array_intersect_key($array, array_flip($keys));
	
	if (!$DD)
		return $array[array_key_first($array)] ?: [];
	
	if ($reIndex)
		return array_values($array);
	
	return $array;
}

use function search_2D as search_2D_array; // Create aliases function * Just work on this file

/**
 * @param $target
 * @param $original
 * @return void
 */
function func_alias($target, $original): void{
    eval("function $target() { \$args = func_get_args(); return call_user_func_array('$original', \$args); }");
}
func_alias('search_2D_array', 'search_2D');

/**
 * Alias for in_array()
 */
function in(...$vars): bool
{
	return in_array(...$vars);
}

/**
 * Use a function for array and string together
 * @param string|array $var
 * @return int
 */
function len(string|array $var): int
{
    if (is_array($var))
        return count($var);
    return strlen($var);
}

/**
 * Converts checkbox values that are null to '0' in a given form.
 *
 * @param array $form The form containing checkbox values.
 * @param string ...$vars The names of checkbox variables.
 * @return void
 */
function checkbox_null_to_0(array &$form, ...$vars): void
{
	foreach ($vars as $var) {
		if (!@$form[$var]) $form[$var] = "0";
	}
}

/**
 * Converts checkbox values that are null to an empty string in a given form.
 *
 * @param array $form The form containing checkbox values.
 * @param string ...$vars The names of checkbox variables.
 * @return void
 */
function checkbox_null_to_empty(array &$form, ...$vars): void
{
	foreach ($vars as $var) {
		if (!@$form[$var]) $form[$var] = "";
	}
}

/**
 * Checks if provided strings contain only English alphanumeric characters.
 *
 * @param string ...$vars The strings to check.
 * @return bool True if all strings are English, false otherwise.
 */
function is_english(...$vars): bool
{
	$vars = array_map(function($var){
		return preg_match('/^[0-9a-zA-Z]+$/', $var);
	}, $vars);
	
	$vars = array_unique($vars);
	
	return $vars[0] && count($vars) == 1;
}

/**
 * Converts Persian numbers to their equivalent English form in a given string.
 *
 * @param string $str The string containing Persian numbers.
 * @param string $decimalSeparator The decimal separator to use (default is '٫').
 */
function num_to_persian(string $str, string $decimalSeparator = '٫'): string {
	$num_a = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.');
	$key_a = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $decimalSeparator);
	return str_replace($num_a, $key_a, $str);
}

/**
 * Converts Persian and Arabic numbers to their equivalent English form in a given string.
 *
 * @param string $string The string containing Persian and Arabic numbers.
 * @return string The string with Persian and Arabic numbers replaced by English numbers.
 */
function persianNum_to_english(string $string): string
{
	$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	$arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
	
	$num = range(0, 9);
	$convertedPersianNums = str_replace($persian, $num, $string);
    return str_replace($arabic, $num, $convertedPersianNums);
}

/**
 * Displays the URL or path for an input image file.
 *
 * @param string $image The image file path or name.
 * @param bool $fullUrl Whether to include the full URL in the result (default is false).
 * @return string The URL or path for the input image file.
 */
function show_input_image($image, $fullUrl = false): string
{
	if (is_file('view/' . $image)) {
		if ($fullUrl)
			return protocol . siteAddress . '/view/' . $image;
		return base . '/view/' . $image;
	}
	return $image;
}

/**
 * Redirects to a specified destination URL with an optional delay.
 *
 * @param string $dist The destination URL.
 * @param int $delay The delay in seconds before redirecting (default is 0).
 *
 * @return void
 */
function redirect(string $dist, int $delay = 0): void
{
	if (str_starts_with($dist, base)){
		$dist = substr($dist, strlen(base));
	}
	$dist = ltrim($dist, "/");
	if ($delay) {
		header("Refresh: $delay;url=" . base . "/" . $dist);
	} else {
		header("Location: " . base . "/" . $dist);
	}
}

/**
 * Calculates the sum of numeric values, either passed as arguments or as an array as Alias for array_sum()
 *
 * @param mixed ...$nums The numeric values or an array of numeric values to sum.
 * @return int The sum of the numeric values.
 */
function sum(...$nums): int
{
	if (is_array(@$nums[0]))
		$nums = array_merge(...$nums);
	return array_sum($nums);
}

/**
 * Calculates the average of numeric values
 *
 * @param mixed ...$nums The numeric values or an array of numeric values to average.
 *
 * @return int|float The sum of the numeric values.
 */
function average(...$nums): int|float
{
	if (is_array(@$nums[0]))
		$nums = array_merge(...$nums);
	return array_sum($nums) / count($nums);
}


/**
 * Generate a random string based on the specified mode and length.
 *
 * @param int $length The length of the generated string (default is 10).
 * @param string $mode The mode of the generated string ('combine', 'letter', 'upperCase', 'number').
 * @return string The generated random string.
 */
function rand_string(int $length = 10, string $mode='combine'): string
{
    $mode = match ($mode){
        'combine'=> '0123456789abcdefghijklmnopqrstuvwxyz',
        'letter'=> 'abcdefghijklmnopqrstuvwxyz',
        'upperCase'=> 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'number'=> '0123456789',
	    default=>'0123456789abcdefghijklmnopqrstuvwxyz'
    };
    return substr(str_shuffle(str_repeat($mode, mt_rand(1,$length))), 1, $length);
}

/**
 * Upload an image file to the specified directory.
 *
 * @param array $file_uploaded The uploaded file data.
 * @param string $place The destination directory.
 * @param string|null $replace_with The file to replace (if any).
 * @param string|null $name The desired file name.
 * @param int|null $rand_name The number of random characters to append to the file name.
 * @param int $max_space The maximum allowed file size in bytes (default is 1048576 bytes).
 * @return string Result code:
 *    - 'a': File not found
 *    - 'b': Exceeds the allowed size limit
 *    - 'c': Invalid file extension
 *    - 'd': Directory not found
 *    - 'e': Not enough space
 */
function upload_image($file_uploaded,$place,$replace_with=null,$name=null,$rand_name=null,$max_space=1048576):string
{
    $error=false;
    if ($file_uploaded['error']==7)
        return "e"; // Not enough space
    if (isset($file_uploaded)&&($file_uploaded['size'])>"0") {
        $image = $file_uploaded;
        $image['name'] = strtolower($image['name']);
        $img_end = array("png", "jpg", "gif", "jpeg", "webp", "svg");
        $explode = explode(".", $image['name']);
        $end = end($explode);
        if ($image['size'] > $max_space) {
            return "b";$error=true;
        }
        if (!in_array($end, $img_end)) {
            return "c";$error=true;
        }
        if (empty($name)){
            if (empty($rand_name)){//نام خود فایل
                $file_name=$image['name'];
            }else{ // نام رندم
                $file_name="";
                for ($a=1;$a<=$rand_name;$a++){
                    $file_name = $file_name.rand_string(5)."-";
                }
                $file_name = substr($file_name,0,-1);
            }
	        $file_name = "$file_name.$end";
        }else{
            $file_name = $name;
        }
        if (!$error) {
            if (is_file($replace_with)) {
                unlink($replace_with);
            }
            if (is_dir($place)){
                move_uploaded_file($image["tmp_name"], "$place"."/".$file_name);
                return "$place"."/".$file_name;
            }else{return "d";}
        }
    }
    return "a";
	//a= File not found
	//b= Exceeds the allowed size limit
	//c= Invalid file extension
	//d= Directory not found

}

/**
 * Upload a file to the specified directory.
 *
 * @param array $file_uploaded The uploaded file data.
 * @param string $place The destination directory.
 * @param string|null $replace_with The file to replace (if any).
 * @param string|null $name The desired file name.
 * @param int|null $rand_name The number of random characters to append to the file name.
 * @param array|null $accept_ext Array of accepted file extensions (optional).
 * @param int $max_space The maximum allowed file size in bytes (default is 1048576 bytes).
 * @return string Result code:
 *    - 'a': File not found
 *    - 'b': Exceeds the allowed size limit
 *    - 'c': Invalid file extension
 *    - 'd': Directory not found
 */
function upload_file($file_uploaded, $place, $replace_with=null, $name=null, $rand_name=null, $accept_ext=null, $max_space=1048576):string
{
    if ($file_uploaded['error']==7)
        return "e"; // Not enough space
    if (isset($file_uploaded)&&($file_uploaded['size'])>"0") {
        $image = $file_uploaded;
        $image['name'] = strtolower($image['name']);
        $explode = explode(".", $image['name']);
        $end = end($explode);
        if ($image['size'] > $max_space) {
            return "b";
        }
        if ($accept_ext){
            if (!in_array(strtolower($end), $accept_ext)) {
                return "c";
            }
        }
        if (in_array(strtolower($end), array("php","php5","php7","php8","phtml"))) {
            return "c";
        }
		
        if (empty($name)){
            if (empty($rand_name)){//نام خود فایل
                $file_name = basename($image['name']);
            }else{ // نام رندم
                $file_name = "";
                for ($a=1;$a<=$rand_name;$a++){
                    $file_name = $file_name.rand_string(5)."-";
                }
                $file_name = substr($file_name,0,-1);
            }
        }else{
            $file_name = $name;
        }
        if (is_file($replace_with)) {
            unlink($replace_with);
        }
        if (is_dir($place)){
            move_uploaded_file($image["tmp_name"], "$place"."/".$file_name);
            return "$place"."/".$file_name;
        }else{return "d";}
    }
    return "a";
	//a= File not found
	//b= Exceeds the allowed size limit
	//c= Invalid file extension
	//d= Directory not found
	//e= Not enough space
}

/**
 * Recursively trims and applies htmlspecialchars to a value.
 *
 * @param mixed $value The value to process.
 * @return void
 */
function trim_and_special_chars(&$value): void
{
    if(is_array($value))
        foreach($value as &$item)
            trim_and_special_chars($item);
    else
        $value = trim(htmlspecialchars($value));
}

/**
 * Get form data from the specified method ($_POST, $_GET, $_FILES, or $_REQUEST).
 *
 * @param string $method The form data method ('post', 'get', 'files', or 'request').
 * @return array The sanitized and trimmed form data.
 */
function get_form_data(string $method = "post"): array
{
	$form = match (strtolower($method)){
        "post"=>$_POST,
        "get"=>$_GET,
        "files"=>$_FILES,
        "request"=>$_REQUEST
    };
    return array_map(function ($value){
	    trim_and_special_chars($value);
		return $value;
    },$form);
}

/**
 * Get form data from the specified method ($_POST, $_GET, $_FILES, or $_REQUEST).
 *
 * @param string $method The form data method ('post', 'get', 'files', or 'request').
 * @return array The form data as is, without sanitization.
 */
function get_form_data_html(string $method = "post"): array
{
    return match (strtolower($method)){
        "post"=>$_POST,
        "get"=>$_GET,
        "files"=>$_FILES,
        "request"=>$_REQUEST
    };
}


/**
 * Convert an array to a JavaScript list with specified quoting marks.
 *
 * @param array $data The array to convert.
 * @param string $mark The quoting marks to use (default is double quotes).
 *
 * @return string The JavaScript list as a string.
 */
function array_to_JS_list(array $data=array(), string $mark = '"' ):string
{
    $str = '[';
    foreach ($data as $item){
        if ($item===null){
            $str .= 'null, ';
            continue;
        }
        
        $str .= $mark.$item.$mark.', ';
    }
    $str = rtrim($str,', ');
    $str .= ']';
    return $str;
}

/**
 * Extract a substring between two specified strings.
 *
 * @param string $string The source string.
 * @param string $start The starting string.
 * @param string $end The ending string.
 * @return string The extracted substring.
 */
function string_between(string $string, string $start, string $end): string
{
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

/**
 * Recursively delete a folder and its contents.
 *
 * @param string $path The path to the folder.
 * @return bool True if deletion was successful, false otherwise.
 */
function delete_folder(string $path): bool
{
    if (!is_dir($path)) {
        return false; // Destination folder not exist
    }
    foreach (scandir($path) as $file) {
        if ($file === '.' || $file === '..')
            continue;
        $fullPath = "$path/$file";
        if (is_dir($fullPath)) {
            if (!delete_folder($fullPath))
                return false;
            @rmdir($fullPath);
        } else {
            if (!@unlink($fullPath))
                return false;
        }
    }
    return @rmdir($path);
}

/**
 * Recursively copy a folder and its contents.
 *
 * @param string $source The path to the source folder.
 * @param string $destination The path to the destination folder.
 * @return bool True if copying was successful, false otherwise.
 */
function copy_folder(string $source, string $destination): bool
{
    if (!is_dir($source))
        return false; // Destination folder not exist

    if (!is_dir($destination) && !mkdir($destination))
        return false; // Create destination folder is not successfully

    foreach (scandir($source) as $file) {
        if ($file === '.' || $file === '..')
            continue;
        $srcPath =  "$source/$file";
        $destPath = "$destination/$file";
        if (is_dir($srcPath)) {
            if (!copy_folder($srcPath, $destPath)) {
                return false;
            }
        } else {
            if (!@copy($srcPath, $destPath)) {
                return false;
            }
        }
    }
    return true;
}


/**
 * Get the contents of a web page using cURL.
 *
 * @param string $url The URL of the web page.
 * @return string The contents of the web page.
 */
function get_page(string $url):string
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * Format a file size into a human-readable format.
 *
 * @param int $bytes The file size in bytes.
 * @param int $decimal The number of decimal places (default is 2).
 * @param string $unit The desired unit (optional).
 * @return string The formatted file size.
 */
function size_unit_format(int $bytes, int $decimal = 2, string $unit = ''): string
{
	$units = ['TB' => 1099511627776, 'GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024];
	if ($unit && array_key_exists($unit, $units)) {
		return number_format($bytes / $units[$unit], $decimal) . ' ' . $unit;
	}
	foreach ($units as $key => $value) {
		if ($bytes >= $value) {
			return number_format($bytes / $value, $decimal) . ' ' . $key;
		}
	}
	return $bytes > 1 ? $bytes . ' bytes' : ($bytes == 1 ? $bytes . ' byte' : '0 bytes');
}

/**
 * Sort an array of associative arrays based on the 'priority' key.
 *
 * @param array $first The first array to compare.
 * @param array $second The second array to compare.
 * @return int The comparison result for sorting.
 */
function priority_sort(array $first, array $second): int
{
    return $first['priority']<=>$second['priority'];
}

/**
 * Create directories if they do not exist.
 *
 * @param string ...$folders Variable number of folder paths.
 */
function make_dirs_if_not_exist(...$folders):void
{
    foreach ($folders as $folder){
        $folder = trim($folder, '/');
        if (!is_dir(__DIR__."/../".$folder)){
            mkdir(__DIR__."/../".$folder, 0777, true);
        }
    }
}

/**
 * Normalize a file path by replacing backslashes (\) with forward slashes (/),
 * removing consecutive slashes (//), and trimming trailing slashes (end/).
 *
 * @param string $path The file path to normalize.
 * @return string The normalized file path.
 */
function normalize_path($path): string
{
	$path = str_replace("\\", "/", $path);
	$path = preg_replace("#/{2,}#", "/", $path);
	return rtrim($path, '/');
}

/**
 * Function convert string to second language
 * @param ...$echoString
 *
 * @return mixed|string
 */
function __(...$echoString): mixed
{
	global $localLanguage, $translate;
	if($localLanguage && !$translate && file_exists("view/languages/$localLanguage.json")){ // Load $translate global file at first function call
		$translate = json_decode(file_get_contents("view/languages/$localLanguage.json"), 1);
	}
    if ($localLanguage && !empty($translate)) {
        foreach ($echoString as &$item) {
            // if string contains Comma
            if (str_contains($item, ',') || str_contains($item, '،')) {
                $parts = preg_split('/[,،]\s*/u', $item);
                foreach ($parts as &$part) {
                    if (isset($translate[$part])) {
                        $part = $translate[$part];
                    }
                }
                $item = implode(', ', $parts);
            } else {
                if (isset($translate[$item])) {
                    $item = $translate[$item];
                }
            }
        }
    }
	
	if(count($echoString)>1)
		return sprintf(...$echoString);
	return $echoString[0];
}

/**
 * This variable will be used on `<<<HEREDOC` format for run functions
 *
 * **Example**:
 *
 *  `{$heredoc(date("Y/m/d H:i", $time))} `
 * @param $param
 *
 * @return mixed
 */
function HEREDOC($param): mixed{
	// just return whatever has been passed to us
	return $param;
}
$HEREDOC = 'HEREDOC';


/**
 * Limit the response to a specific HTTP method.
 * If the request method does not match the specified method, it sends a 405 Method Not
 * @param mixed ...$methods
 * @return void
 */
function limit_method(...$methods): void
{
    $methods = array_map('strtoupper', $methods);
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        ob_end_clean();
        ob_clean();
        ob_start();
        http_response_code(405); // Method Not Allowed
        exit('Method Not Allowed');
    }
}
