<?php
session_start();
ob_start();
ini_set('display_errors', 1);
error_reporting (E_ALL & ~E_NOTICE);


// Global stuff, ugly but convenient
//----------------------------------------------------------------------------------------------------------------------

if (!function_exists('is_closure'))
{
    function is_closure($t) {
        return is_object($t) && ($t instanceof Closure);
    }
}

if (!function_exists('debug'))
{
    function debug()
    {
        ob_end_flush();
        echo '<pre>';
        print_r (func_get_args());
        die();
    }
}

if (!function_exists('isDev'))
{
    function isDev()
    {
        return PT::$is_debug;
    }
}



class PT
{
//----------------------------------------------------------------------------------------------------------------------

    const VERSION = '0.4.8';



    // routing
    static $path;
    static $routes         = array();
    static $route;
    static $route_function;

    // imports and components
    static $imports        = array();
    static $components     = array();
    static $mapped_methods = array();

    // Localization (translations)
    static $langs          = array();

    // CLI commands
    static $commands       = array();

    // variables
    static $globals        = array();

    // events
    static $events         = array();
    static $disabled_events;  // array of events which are temporarily stopped (are not being listened to)
    static $events_on = 1;

    // errors
    static $error_function;

    // views and lyouts
    static $layout = '';

    // other
    static $is_debug = 0;     // debug mode, 0 by default


//----------------------------------------------------------------------------------------------------------------------

    const
      // all errors not specifically supported
      PT_ERROR_GENERAL  = 0,

      // internal issues: missing/incorrect file/view/class/method, usually coding problem
      PT_ERROR_INTERNAL = 1;



//----------------------------------------------------------------------------------------------------------------------
// ## DEBUGGING

    static function debugMode($value)
    {
        PT::$is_debug = $value;
    }

//----------------------------------------------------------------------------------------------------------------------
// ## ERRORS


    static function error($msg, $type=PT_ERROR_GENERAL)
    {
        PT::trigger('error');
        if ($type = PT_ERROR_GENERAL)  PT::trigger('error.general');
        if ($type = PT_ERROR_INTERNAL) PT::trigger('error.internal');

        if (isset(PT::$error_function))
        {
            PT::invokeHandler(PT::$error_function,array('msg'=>$msg, 'type'=>$type));
        }
        else
        {
            if (PT::$is_debug)
            {
                // go one level up, we don't want to show debug info for error() function
                $trace = debug_backtrace();
                $caller = array_shift($trace);
                PT::debugView ($msg, $caller['file'], $caller['line'],'Error',$trace);
            }
            else
            {
                echo($msg);
                die();
            }
        }
    }

    static function errorHandler($action)
    {
        PT::$error_function = $action;
    }



//----------------------------------------------------------------------------------------------------------------------
// ## ROUTING

    static function routeEx($rule, $action)
    {
        PT::$routes['regex:'.$rule] = $action;
    }


    static function route($rule, $action)
    {
        PT::$routes[$rule] = $action;
    }


    // Add append an array of routes, in a format ROUTE => HANDLER
    static function routes($routes)
    {
        PT::$routes += $routes;
    }

    // Set a "catch-all" routing function that will get all the requests as a callback
    static function routeAll($f)
    {
        $args = func_get_args();

        if (!$f)
            PT::error('PT::routeAll() requires parameter.',PT_ERROR_INTERNAL);

        // override router, either callback or string
        PT::$route_function = $f;
    }

//----------------------------------------------------------------------------------------------------------------------
// ## Module: INTERNAL
/*
    Accept function (handler) in a various ways and execute it.

    $h - route handler

    can be:
    a) array:    array('Class','method')
    b) string:   'Class.method'
    c) method/closure

    $args - pass optional parameter
*/


    static function invokeHandler($h,$args=NULL)
    {
             // passed function
             if (is_string($h) && is_callable($h))
             {
                if (!$args) $args=array();
                return call_user_func_array($h, $args);
             }

             // closure
             if (is_closure($h))
             {
                if (!$args) $args=array();
                return call_user_func_array($h, $args);
             }

            // String in "Class.method" format
             if (is_string($h) || is_array($h))
             {
                if (is_string($h))
                {
                    $route = explode('.',$h);
                }
                else
                {
                    $route = $h;
                }

                if ($route=='')
                    PT::error('Routing method cannot be an empty string',PT_ERROR_INTERNAL);

                $class = $route[0];
                $method = $route[1];

                // check the class
                if ($class=='' || is_null($class))
                    PT::error('Routing class cannot be empty',PT_ERROR_INTERNAL);

                if (!class_exists($class))
                    PT::error("Routing class doesn't exist: ".$class,PT_ERROR_INTERNAL);

                if (!$method)
                    PT::error("Routing method not specified.",PT_ERROR_INTERNAL);

                // check if method exists
                if (!method_exists($class,$method))
                    PT::error("Method $class.$method does not exist",PT_ERROR_INTERNAL);

                // use Reflection API to see if method is static, if necessary, instantiate the object
                $method_ref = new ReflectionMethod($class.'::'.$method);
                if ( $method_ref->isStatic() )
                {
                    // static call
                    if (!is_array($args)) $args = array($args);
                    return call_user_func_array(array($class,$method), $args);
                }
                else
                {
                    // non-static call (instantiate first)
                    $obj = new $class();
                    return $obj->$method($args);
                }
                return;
             }

             // Still not resolved?
             PT::error("Cannot resolve the route: ".PT::$path,PT_ERROR_INTERNAL);
    }


//----------------------------------------------------------------------------------------------------------------------
// ## Module: INTERNAL,ROUTING

    static function executeRouteFunction()
    {
            PT::invokeHandler(PT::$route_function,PT::$path);
    }


//----------------------------------------------------------------------------------------------------------------------

    static function ip()
    {
        return $_SERVER['REMOTE_ADDR'];
    }


//----------------------------------------------------------------------------------------------------------------------
// ## Module: INTERNAL,ROUTING

    static function dispatch()
    {
        foreach (PT::$routes as $route => $action)
        {
                // if (is_string($action))
                //     echo "$route => $action<BR>";

                $path = PT::$path;

                // simple (exact) match (and catch-all match)
                if ($path==$route || $route=='*')
                {
                    PT::invokeHandler($action);
                    return;
                }

                // is it regex rule (routeEx)?
                if (strpos($route, 'regex:') !== false)
                {
                    $route = substr($route,6);

                    // escape all "/" and prepare regex pattern
                    $route = '/' . str_replace('/', '\/', $route) . '/';
                    if (preg_match($route, $path))
                    {
                        PT::invokeHandler($action);
                        return;
                    }
                    continue;
                }

                // wildcards (replace one segment)
                $route = str_replace('*', '[^/]*', $route);

                // triple dot (end with anything after)
                $route = str_replace('...', '.*', $route);

                // get the names of named parameters (if any)
                $matches=array();
                preg_match_all('/(?<=\(@|@)[^\/\)]+(?=|\))/', $route, $matches);
                //preg_match_all('/\(?@[^\/\)]+\)?/', $route, $matches);
                $param_names = $matches[0];
                $params = array();

                // collect the values for named parameters (if provided)
                $route = preg_replace('/\(@[^\/\)]+\)/', '([^/]*)', $route);              // optional params
                $route = preg_replace('/(?<!\()@[^\/\)]+(?!\))/', '([^/]+)', $route);     // mandatory params
                //$route = preg_replace('/\(?@[^\/\)]+\)?/', '([^/]*)', $route);

                // convert it into proper regex
                $route = str_replace('/','\/', $route);
                $route = '/^'.$route.'$/i';

                // if path ends with wildcard, don't require "/" at the end
                $route = str_replace('\/[^\/]*$/i', '\/*[^\/]*$/i', $route);

                // same for optional parameters...
                $route = str_replace('\/([^\/]*)$/i', '\/*([^\/]*)$/i', $route);

                // find matches
                preg_match_all($route, $path,$matches);

                // now assign the values
                if ($param_names) foreach ($param_names as $i => $m) {
                    $params[$m] = $matches[$i+1][0];
                }

                //debug ($path, $route, $matches, $params, $matches[0]?'*****************':'');

                // match?
                if ($matches[0])
                {
                    $_REQUEST += $params;
                    PT::invokeHandler($action);
                    return;
                }
        }

        // No rules matched, trigger 404
        if (PT::isHandled('error.404'))
        {
            PT::trigger('error.404');
        }
        else
        {
            header("HTTP/1.0 404 Not Found");
            die('Page not found.');
        }
    }


//----------------------------------------------------------------------------------------------------------------------
// ## LOGGING

    const
        LOG_CRITICAL = 4,
        LOG_ERROR    = 3,
        LOG_WARNING  = 2,
        LOG_INFO     = 1,
        LOG_DEBUG    = 0;


//----------------------------------------------------------------------------------------------------------------------

    static function log ($level, $data)
    {
        // allow console logging only in debug mode
        if (!PT::$is_debug) return;

        // log to file
        $min_level = PT::get('log.level');
        $log_file  = PT::get('log.file');

        if (!$log_file)
            PT::error('log.file setting cannot be empty!',PT_ERROR_INTERNAL);

        if (is_object($data) || is_array($data))
            $data = json_encode($data);

        if ($level >= $min_level)
            file_put_contents($log_file,$data.PHP_EOL, FILE_APPEND);
    }

    // internal
    static function argsToString($args)
    {
        if (count($args)==1) $args = $args[0];

        if (is_array($args) || is_object($args))
        {
            return json_encode($args);
        }

        // if simple value, just escape it
        return addslashes($args);
    }

    static function logCritical ($data)
    {
        PT::log(PT::LOG_CRITICAL, $data);
    }

    static function logError ($data)
    {
        PT::log(PT::LOG_ERROR, $data);
    }

    static function logWarning ($data)
    {
        PT::log(PT::LOG_WARNING, $data);
    }

    static function logInfo ($data)
    {
        PT::log(PT::LOG_INFO, $data);
    }

    static function logDebug ($data)
    {
        PT::log(PT::LOG_DEBUG, $data);
    }

    // internal
    static function consoleOutput($type,$data,$style=null)
    {
        // allow console output only in debug mode
        if (!PT::$is_debug) return;
        $colors = PT::get('debug.console_colors',0);

        if ($colors)
            echo "<script>console.".$type."('%c".$data."','".$style."');</script>";
        else
            echo "<script>console.".$type."('pt:".$data."');</script>";
    }

    static function consoleLog ()
    {
        $data  = PT::argsToString(func_get_args());
        $style = 'background:#ccc;padding:2px;color:black';
        PT::consoleOutput('log',$data,$style);
    }

    static function consoleDebug ()
    {
        $data  = PT::argsToString(func_get_args());
        $style = 'background:yellow;padding:2px;color:black';
        PT::consoleOutput('debug',$data,$style);
    }

    static function consoleError ()
    {
        $data  = PT::argsToString(func_get_args());
        $style = 'background:red;padding:2px;color:white';
        PT::consoleOutput('error',$data,$style);
    }

    static function consoleInfo ()
    {
        $data  = PT::argsToString(func_get_args());
        $style = 'background:#99CFE0;padding:2px;color:black';
        PT::consoleOutput('info',$data,$style);
    }

    static function consoleWarning ()
    {
        $data  = PT::argsToString(func_get_args());
        $style = 'background:orange;padding:2px;color:black';
        PT::consoleOutput('warn',$data,$style);
    }

    static function consoleAssert ($expression,$label=null)
    {
        // allow console output only in debug mode
        if (!PT::$is_debug) return;
        $exp = ($expression ? 'true':'false');
        echo "<script>console.assert($exp, '$label');</script>";
    }

    static function consoleClear ()
    {
        // allow console output only in debug mode
        if (!PT::$is_debug) return;
        echo "<script>console.clear();</script>";
    }

    static function consoleGroup ($name,$collapsed=0)
    {
        // allow console output only in debug mode
        if (!PT::$is_debug) return;
        $name = addslahes($name);
        if ($collapsed)
            echo "<script>console.groupCollapsed('$name');</script>";
        else
            echo "<script>console.group('$name');</script>";
    }

    static function consoleGroupEnd ($name)
    {
        // allow console output only in debug mode
        if (!PT::$is_debug) return;
        $name = addslahes($name);
        echo "<script>console.groupEnd();</script>";
    }


//----------------------------------------------------------------------------------------------------------------------
// ## LOCALIZATION


    static function addTranslations($lang, $group, $translations)
    {
        PT::$langs[$lang][$group] = $translations;
    }

    static function addTranslation($lang, $group, $key, $value)
    {
        PT::$langs[$lang][$group][$key] = $value;
    }

    static function importTranslations($path)
    {
        $files = scandir ($path);

        if ($files) foreach ($files as $lang) {
            if ($lang[0]=='.') continue;

            // import language
            $groups = scandir($path.'/'.$lang);
            if ($groups) foreach ($groups as $group)
            {
                if ($group[0]=='.') continue;
                PT::addTranslations($lang,basename($group,'.php'),include ($path.'/'.$lang.'/'.$group));
            }
        }
    }

    static function setLanguage($lang)
    {
        PT::set('app.language',$lang);
    }

    static function setFallbackLanguage($lang)
    {
        PT::set('app.fallback_language',$lang);
    }

    static function hasTranslation($path)
    {
        $lang = PT::get('app.language','en');
        $p = explode('.', $path);
        $group = $p[0];
        $word  = $p[1];

        return (PT::$langs[$lang][$group][$word]) ? 1 : 0;
    }

    static function translate($path, $replacements=array())
    {
        $lang = PT::get('app.language','en');
        $p = explode('.', $path);
        $group = $p[0];
        $word  = $p[1];
        $translation = PT::$langs[$lang][$group][$word];

        // if no translation found, use fallback language if specified
        $fallback = PT::get('app.fallback_language', 'en');
        if (!$translation)
        {
            $translation = PT::$langs[$fallback][$group][$word];
        }

        // if no translation is found, return the whole translation key
        if (!$translation) return $path;

        if ($replacements) foreach ($replacements as $old => $new) {
            $translation = str_replace(':'.$old, $new, $translation);
        }

        return $translation;
    }


//----------------------------------------------------------------------------------------------------------------------
// ## EVENTS

    static function on($event, $action)
    {
        PT::$events[$event] = $action;
    }


    // if no event name provided, listening for all events will be suspended
    static function stopListening($event=NULL)
    {
        if ($event)
            PT::$disabled_events[$event] = 1;
        else
            PT::$events_on = 0;
    }

    // if no event name provided, listening for all events will be resumed
    static function resumeListening($event=NULL)
    {
        if ($event)
            unset(PT::$disabled_events[$event]);
        else
            PT::$events_on = 1;
    }


    static function trigger($event, $args=NULL)
    {
        $action = PT::$events[$event];
        if ($action && PT::$events_on && !PT::$disabled_events[$event])
            PT::invokeHandler($action,$args);
    }


    static function isHandled($event)
    {
        $action = PT::$events[$event];
        return $action?1:0;
    }

//----------------------------------------------------------------------------------------------------------------------
// ## INTERNAL

static function closure_dump(Closure $c) {
    $str = 'function (';
    $r = new ReflectionFunction($c);
    $params = array();
    foreach($r->getParameters() as $p) {
        $s = '';
        if($p->isArray()) {
            $s .= 'array ';
        } else if($p->getClass()) {
            $s .= $p->getClass()->name . ' ';
        }
        if($p->isPassedByReference()){
            $s .= '&';
        }
        $s .= '$' . $p->name;
        if($p->isOptional()) {
            $s .= ' = ' . var_export($p->getDefaultValue(), TRUE);
        }
        $params []= $s;
    }
    $str .= implode(', ', $params);
    $str .= '){' . PHP_EOL;
    $lines = file($r->getFileName());
    for($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
        $str .= $lines[$l];
    }
    return $str;
}


    static function debug()
    {
        if (!PT::$is_debug) return;

        // go one level up, we don't want to show debug info for debug() function
        $trace     = debug_backtrace();
        $caller    = array_shift($trace);
        $data      = func_get_args();
        $date_text = '<pre>'.var_export($data,1).'</pre>';

        PT::debugView ($date_text, $caller['file'], $caller['line'],'Debug Breakpoint',$trace);
    }


    static function debugView($msg,$file,$line,$class,$trace)
    {
        // show source code context
        function source ($file, $ln, $context=10)
        {
            $f = file($file);
            $start = max($ln - $context,0);
            $end = min($ln + $context, count($f));
            echo '<pre style="width:100%; border:1px solid #aaa;">';

            for ($i=$ln-$context-1; $i<$ln+$context ; $i++) {
                if ($i>0 && $i<count($f)-1)
                {
                    if ($i+1 == $ln) echo '<div style="background:#ddd">';
                    echo ($i+1).': '.$f[$i];
                    if ($i+1 == $ln) echo '</div>';
                }
            }
            echo '</pre>';
        }

            echo '<html><script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>';
            echo "<h1>$class</h1>";

            echo "<div style='width:100%; background:#eee; padding:20px; box-sizing:border-box'>";
            echo $msg;
            echo "</div>";

            echo "<h3>$file:$line</h3>";
            source($file, $line);

            echo '<BR><BR><h3>Stack trace:</h3>';

            if ($trace) foreach ($trace as $i => $t)
            {
                $file     = $t['file'];
                $line     = $t['line'];
                $function = $t['function'];
                $class    = $t['class'];
                $type     = $t['type'];
                $args     = $t['args'];
                $arglist  = array();

                if ($args)
                {
                    // filter out closures which cannot be converted to a string
                    foreach ($args as $arg)
                    {
                         if (is_closure($arg))
                         {
                            $src = PT::closure_dump ($arg);
                            //$arglist[] = 'Closure';
$arglist[] = <<<HEREDOC
<a href='javascript:void(0)' onclick="\$(this).next('pre').toggle();">Closure</a>
<pre class='closure_src' style='background:#f1f1f1; padding:10px; display:none'>
$src
</pre>
HEREDOC;
                         }
                         else
                             $arglist[] = var_export($arg,true);
                    }
                }
                $arglist = implode(',',$arglist);

                echo "[#$i] ";
                if ($file)
                    echo "$file ($line)";
                else
                    echo '(no file)';

                if ($class)
                    echo ": <strong>$class$type$function</strong>($arglist)";
                else
                    echo ": <strong>$function</strong>($arglist)";

                echo '<BR>';

                if ($file && $line)
                    source($file, $line, 5);

                echo '<BR>';
            }
    }



    static function processErrors($err,$msg,$file,$line,$details)
    {
        // only in debug mode!
        if (!PT::$is_debug)
             return false;

        // ignore notices...
        if ($err == E_NOTICE) return false;

        function get_error_class($errno)
        {
            switch ($errno) {
                  case 1:     $e_type = 'E_ERROR'; break;
                  case 2:     $e_type = 'E_WARNING'; break;
                  case 4:     $e_type = 'E_PARSE'; break;
                  case 8:     $e_type = 'E_NOTICE'; break;
                  case 16:    $e_type = 'E_CORE_ERROR'; break;
                  case 32:    $e_type = 'E_CORE_WARNING'; break;
                  case 64:    $e_type = 'E_COMPILE_ERROR'; break;
                  case 128:   $e_type = 'E_COMPILE_WARNING'; break;
                  case 256:   $e_type = 'E_USER_ERROR'; break;
                  case 512:   $e_type = 'E_USER_WARNING'; break;
                  case 1024:  $e_type = 'E_USER_NOTICE'; break;
                  case 2048:  $e_type = 'E_STRICT'; break;
                  case 4096:  $e_type = 'E_RECOVERABLE_ERROR'; break;
                  case 8192:  $e_type = 'E_DEPRECATED'; break;
                  case 16384: $e_type = 'E_USER_DEPRECATED'; break;
                  case 30719: $e_type = 'E_ALL'; break;
                  default:    $e_type = 'E_UNKNOWN'; break;
            }
            return $e_type;
        }

        // We can only catch: E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE, default (others)
        $trace = debug_backtrace();
        array_shift($trace);

        $class = 'PHP Error ('.get_error_class($err).')';

        PT::debugView ($msg,$file,$line,$class,$trace);
        die();
    }



    static function processException($e)
    {
         // call handler if defined
         if (PT::isHandled('exception'))
         {
               PT::trigger('exception',$e);
               return;
         }

         // minimal output in production mode
         if (!PT::$is_debug)
         {
              die('PHP Exception: '.$e->getMessage());
         }

            $class = get_class($e);
            $msg   = $e->getMessage();
            $file  = $e->getFile();
            $line  = $e->getLine();
            $trace = $e->getTrace();

            PT::debugView ($msg,$file,$line,$class,$trace);
    }


//----------------------------------------------------------------------------------------------------------------------
// ## VIEWS


    static function layout($layout)
    {
        PT::$layout = $layout;
    }

    // parse view and return its content
    static function parseView ($view, $data=null)
    {
        if ($data)
            extract ($data);

        ob_start();

        if (file_exists($view))
            require ($view);
        elseif (file_exists($view.'.php'))
            require ($view.'.php');
        else
        {
            PT::error("View '$view' does not exist", PT_ERROR_INTERNAL);
            return;
        }
        return ob_get_clean();
    }

    // parse view and load it
    static function renderView ($view, $data=null)
    {
        if ($data)
            extract ($data);

        if (file_exists($view))
            require ($view);
        elseif (file_exists($view.'.php'))
            require ($view.'.php');
        else
        {
            PT::error("View '$view' does not exist", PT_ERROR_INTERNAL);
            return;
        }
    }

    // render with or without layout
    // return not supported
    static function render($view,$data)
    {
        if (PT::$layout)
        {
            $content = PT::parseView ($view, $data, true);
            //if ($return) ob_start();

            if (file_exists(PT::$layout))
                require(PT::$layout);
            elseif (file_exists(PT::$layout.'.php'))
                require(PT::$layout.'.php');
            else
            {
                PT::error("Layout ".PT::$layout." does not exist", PT_ERROR_INTERNAL);
                return;
            }
            //if ($return) return ob_get_clean();
        }
        else
            PT::renderView ($view, $data);
    }



//----------------------------------------------------------------------------------------------------------------------
// ## VARIABLES

    static function set($name,$value)
    {
        PT::$globals[$name] = $value;
    }

    static function has($name)
    {
        return isset(PT::$globals[$name]);
    }

    static function get($name, $default=null)
    {
        $v = PT::$globals[$name];
        if (!$v) return $default;
        return $v;
    }

    static function clear($name=NULL)
    {
        if ($name)
           unset(PT::$globals[$name]);
        else
           PT::$globals = array();
    }


//----------------------------------------------------------------------------------------------------------------------
// ## COMMANDS

    static function command($name,$action)
    {
        PT::$commands[$name] = $action;
    }


//----------------------------------------------------------------------------------------------------------------------
// ## EXTENDING

    static function import($path)
    {
        PT::$imports[] = $path;
    }


    static function install($name,$class,$args=NULL)
    {
        if (!$class) $class = $name;
        PT::$components[$name] = array('class' => $class, 'args' => $args);
    }


    static function map($name, $action)
    {
        PT::$mapped_methods[$name] = $action;
    }



//----------------------------------------------------------------------------------------------------------------------
// ## Module: EXTENDING

    public static function __callStatic($name, $args=NULL)
    {
        $c = PT::$components[$name];

        // check if there is a component with this name
        if ($c)
        {
            // instantiate the component with default arguments (if provided)
            return new $c['class']($c['args']);
        }

        // otherwise, look for mapped method
        $f = PT::$mapped_methods[$name];

        if ($f)
        {
           return PT::invokeHandler($f, $args);
        }

        // nothing found?
        PT::error("$name is not an installed component, mapped method or internal method.",PT_ERROR_INTERNAL);
        return;
    }


//----------------------------------------------------------------------------------------------------------------------
// ## Module: INTERNAL

    static function autoLoader($class)
    {
        $f = $class.'.php';

        if (PT::$imports) foreach (PT::$imports as $path)
        {
            // exact match
            if (basename($path) == $f && file_exists($path))
            {
                require($path);
                return;
            }

            // import the whole folder
            if (basename($path) == '*')
            {
                // remove * from the end and apply file name for matching
                $full_path = substr($path,0,-1).$f;
                if (file_exists($full_path))
                {
                    require($full_path);
                    return;
                }
            }
        }
    }


//----------------------------------------------------------------------------------------------------------------------
// ## Module: GENERAL


    static function cmdMode()
    {
            echo "\n";
            echo "phpThunder ".PT::VERSION." (C)2014 J.Gebczak \n";
            echo "----------------------------------\n\n";

            global $argv;

            // list commands
            if (!$argv[1])
            {
                // list available commands
                if (PT::$commands) {
                    $count = count(PT::$commands);
                    echo "Available commands ($count):\n\n";
                    foreach (PT::$commands as $cmd => $action) {
                        echo "- $cmd\n";
                    }
                    echo "\n\n";
                }
                else
                {
                    echo "There are no available commands to run in command line mode.\n\n";
                }
            }
            else // command name provided, run it!
            {
                $cmd = $argv[1];
                $args = $argv;
                array_shift($args);
                array_shift($args);

                if (PT::$commands[$cmd])
                {
                    echo "Running command '$cmd'...\n\n";
                    // run the associated action passing the arguments
                    PT::invokeHandler(PT::$commands[$cmd],array($args));
                }
                else
                {
                    PT::error("Command $cmd doesn't exist.",PT_ERROR_INTERNAL);
                    return;
                }
            }
    }


//----------------------------------------------------------------------------------------------------------------------


static function loadDefaultSettings()
{
    // set default values for each setting (unless already set)
    $defaults = array(
        'cache.method'          => 'session',  // (memcache,disk,session,memory)
        'cache.memcache.port'   => 11211,
        'cache.memcache.server' => 'localhost',
        'cache.disk.location'   => 'cache',

        'log.level'             => (PT::$is_debug ? PT::LOG_DEBUG : PT::LOG_WARNING),
        'log.file'              => 'pt.log'
    );

    // set defaults only if no other values were set
    foreach ($defaults as $key => $value) {
        if (!PT::has($key)) PT::set($key, $value);
    }
}


//----------------------------------------------------------------------------------------------------------------------
// ## CONFIG LOADER
// Load all things from one array instead of calling methods individuallly
// You can also pass config name to start() function


    static function loadConfig($config)
    {
        if (!$config) return;
        if (!is_array($config)) return;

        // routes
        if ($config['routes'])
            PT::$routes += array_merge(PT::$routes, $config['routes']);

        if ($config['regex_routes'])
        foreach ($config['regex_routes'] as $route => $action) {
            PT::routeEx($route, $action);
        }

        // imports
        if ($config['imports'])
            PT::$imports += array_merge(PT::$imports, $config['imports']);

        // components
        if ($config['components'])
        foreach ($config['components'] as $name => $c) {
            PT::install($name,$c['class'],$c['settings']);
        }

        // mapped_methods
        if ($config['mapped_methods'])
            PT::$mapped_methods += array_merge(PT::$mapped_methods, $config['mapped_methods']);

        // globals (settings)
        if ($config['settings'])
            PT::$globals += array_merge(PT::$globals, $config['settings']);

        // commands
        if ($config['commands'])
            PT::$commands += array_merge(PT::$commands, $config['commands']);

        // events
        if ($config['events'])
            PT::$events += array_merge(PT::$events, $config['events']);

    }


    static function start($config=NULL)
    {
        // debug settings
        if (PT::$is_debug)
        {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting (E_ALL & ~E_NOTICE);
        }
        else
        {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting (E_ALL & ~E_DEPRECATED & ~E_STRICT);
        }


        if ($config) PT::loadConfig($config);
        PT::loadDefaultSettings();

        // import own components
        PT::import(__DIR__.'/*');

        set_exception_handler (array('PT','processException'));
        spl_autoload_register (array('PT','autoLoader'));
        set_error_handler     (array('PT','processErrors'));

        // map Response handling
        PT::map('returnSuccess',array('PTResponse','returnSuccess'));
        PT::map('returnError',  array('PTResponse','returnError'));
        PT::map('ajaxError',    array('PTResponse','ajaxError'));
        PT::map('ajaxSuccess',  array('PTResponse','ajaxSuccess'));
        PT::map('jsonSuccess',  array('PTResponse','jsonSuccess'));
        PT::map('jsonError',    array('PTResponse','jsonError'));
        PT::map('abort',        array('PTResponse','abort'));

        // map Cache
        PT::map('cache',        array('PTCache','cache'));
        PT::map('clearCache',   array('PTCache','clearCache'));

        // map Helper
        PT::map('param',        array('PTHelper','param'));
        PT::map('redirect',     array('PTHelper','redirect'));
        PT::map('isCLI',        array('PTHelper','isCLI'));
        PT::map('isAjax',       array('PTHelper','isAjax'));
        PT::map('isMobile',     array('PTHelper','isMobile'));
        PT::map('slug',         array('PTHelper','slug'));

        // Command mode
        if (PT::isCLI())
        {
            PT::cmdMode();
            die();
        }

        // save path for routing (with query string stripped)
        PT::trigger('pt.start');

        $r = rtrim ($_SERVER['REQUEST_URI'],'/');
        PT::$path = strtok($r,'?');

        // do we have routing set up at all?
        if (!isset(PT::$route_function) && !isset(PT::$routes))
            PT::error('Routing is not set up. Use PT::route() at least once.',PT_ERROR_INTERNAL);

        PT::trigger('pt.beforeRouting');

        // do we have router overriden?
        if (isset(PT::$route_function))
            PT::executeRouteFunction();
        else
            PT::dispatch();
    }

//----------------------------------------------------------------------------------------------------------------------

}


?>