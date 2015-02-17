#phpThunder Reference

### Table of Contents

* [Hello World](#helloworld)
* [Features](#features)
* [Directory structure](#dir)
* [Routing](#routing)
* [MVC Structure](#mvc)
* [Database](#database)
* [Extending: Imports, Components and Mapping](#extending)
* [Views](#views)
* [Config](#config)
* [Caching](#cache)
* [Events](#events)
* [Localization](#localization)
* [Error and response handling](#error)
* [Debugging](#debugging)
* [Helper functions](#helpers)
* [Command line (commands)](#cli)

<a name='helloworld' class='anchor'></a>
***
## Hello World

Applications written in phpThunder can be as simple as this one:

```php
require('../framework/PT.php');

PT::route('*',function(){
    echo "Hello world!";
});

PT::start();

```
The whole framework is contained within one file.
For a fully functional website, there are only two mandatory steps:

- **Router:** calling `PT::route()` to specify how your website will respond to different requests. In this case all requests will show "Hello world"
- **Start:** calling `PT::start()` will initialize the framework and start processing requests.


<a name='features' class='anchor'></a>
***
## Features

* Compatible with MVC pattern
* Flexible pattern-based routing
* Flexible syntax and code structure
* Event Handling
* Caching
* Database support with prepared statements and bound parameters (PDO)
* Error and exception handling
* Extensible by components, imported classes, mapped functions
* View rendering with support for layouts
* Support for Ajax requests
* Command line scripts (commands)
* Global storage and config (settings)
* Easy config load with array

<a name='dir' class='dir'></a>
***
## Directory Structure

phpThunder does not require any particular directory structure. The simplest application can be contained even within a single file. There is however, a recommended directory structure you should follow to utilize MVC pattern and keep your project clean and organized.

The flexibility of phpThunder lies in the fact that directory structure of your project is fully
customizable. You can choose which directories to use depending on the size of your project.

```
/project1
    /public
        index.php
    /extensions
         (any third-party files)
    /app
        /commands
            TestCommand.php
        /controllers
            MainController.php
        /models
            MainModel.php
        /views
            login.php
            index.php
        /layouts
            main.php
            main_header.php
            main_footer.php

        /components
        /lang
            /en
                main.php
        /config
        /cache
        /logs
```

<a name='routing'></a>
***
## Routing

Routing matches URL **pattern** with a **callback**.

#### Callback: Simple

```php
function actionIndex()
{
    echo 'Hello!';
}

PT::route('/', actionIndex);
```

> **Good Practice:** Functions which serve data in response to a request URL should be prefixed with 'action'. This will distinquish them from "regular functions performing other tasks.

#### Callback: Closures

Instead of pointing to an existing function, you can use an anonymous function (closure):

```php
PT::route('/', function(){
    echo 'Hello';
});
```

#### Callback: Class methods

Callback can be a class method:

```php
PT::route('/', array('MyClass','actionHello'));
```
> **Note:** If provided method is not static, its class will be automatically instantiated first before calling that method.

Alternative syntax (class name and method name should be separated with a single dot):
```php
PT::route('/', 'MyClass.actionHello');
```

#### Patterns:

'/' (forward slash) matches the **home page**
```php
PT::route('/',actionIndex)
```
'&ast;' (asterisk) will match **all requests**:

```php
PT::route('/product/view',actionViewProduct)
```
In most cases you will specify the **exact path**:

```php
PT::route('/product/view',actionViewProduct)
```
Use '@' to define a **named parameter**. Its value will be appended to $_REQUEST superglobal.
All named parameters are required.

```php
PT::route('/product/@id',actionProduct)
```

You can also define a **optional named parameter**:

```php
PT::route('/products/(@sort)',actionProducts)
```
> **Note:** Only the last segment of the route can be an optional parameter.

To allow any content within a segment without treating is as a named parameter, use a **wildcard**:

```php
PT::route('/products/*/info',actionProductInfo)
```
To specify a route that **starts or ends** with a specific pattern use **ellipsis (triple dot)**:

```php
PT::route('/products/...',actionProducts)
```
this would match:<BR>
*/products/list*<BR>
*/products/view/1234*<BR>
*/products/view/1234/photo*<BR>
etc.

or
```php
PT::route('.../photo',actionPhotos)
```

> **Note:** If you put '/' right next to the dots, the part of URL in place of the dots will be requred. For example: `PT::route('/page/...',actionPages)`
will match both `/page/view` and `/page/product/123` but not just `/page`. If you want to make it optional, remove the '/': `PT::route('/page...',actionPages)`

#### Regular expressions

Use `PT::routeEx()` to define a pattern using regular expressions:
```php
PT::routeEx('/gallery/year/[0-9]{4}',actionGallery)
```
> **Note:** `PT::routeEx()` does not support any above concepts (named parameters, wildcards, ellipsis etc.).

#### Add multiple routes at once

Instead of calling `PT::route()` each time for each route, you can define them all passing an array to `PT::routes()`:

```php
PT::routes(array(
    '/' => 'actionIndex',
    '/about' => 'actionAbout',
    '/contact' => 'actionContact
))```

This will append define rules to the existing ones.

#### Custom router

If you want to route all requests through your own function use `PT::routeAll()`:
```php
function myRouter ($path)
{
    if ($path=='/') actionHomePage();
}

PT::routeAll('myRouter')
```

> **Note:** When `PT::routeAll()` is specified, routes declared with `PT::route()`,`PT::routes()` and `PT::routeEx()` will NOT be processed.

<a name='mvc' class='anchor'></a>
***
## MVC Structure

phpThunder encourages MVC structure for your web application, but it doesn't require it.
You have a full freedom in the way you organize and structure your code.

However, I strongly recommend few good MVC rules:

* split your PHP files into **models**, **controllers** and **views** (hence the name "MVC")*
* create **"controllers"**, **"views"** and **"models"** directories in your application directory and put each file in a respective folder
* **index.php** should be the only PHP code outside that structure
* **models** should the the only files that talk directly to the database to retrieve or manipulate the data
* **controllers** should manage the flow, apply business logic, retrieve data from **models** and pass it to the **views**
* **views** should only contain visual part of the website (html), using php only for conditional blocks or inserting variables (`<?=$name?>`)

<a name='database' class='anchor'></a>
***
## Database

phpThunder uses PDO to provide database access.

Start with installing a component which will be used to access database. You may install multiple database component to support different databases:

```php
PT::install('db','PTDatabase',array('mysql:host=localhost;dbname=database1','root','SecretPassword'));
```

Queries are defined in a form of prepared statement. Call `createCommand()` on your database component to create a new query:

```php
$products_query = PT::db()->createCommand('select * from products');
```

Now you can call a method on $products_query to execute it with a proper result:

- use `execute()` when query does not return any data (for example INSERT, DELETE, UPDATE, DROP, TRUNCATE etc.)
- use `queryAll()` to return all results in a form of array of rows (each row is an associative array)
- use `queryRow()` to return just one (first) row
- use `queryScalar()` to return a single value, not a whole row
- use `queryColumn()` to return an array containing only selected value from each row

**Examples**:

```php
$insert_query = PT::db()->createCommand('insert into product set name = "New Item"');
$insert_query->execute();

$products_query = PT::db()->createCommand('select * from products');
$products = $products_query->queryAll();

$last_product_query = PT::db()->createCommand('select * from products order by id desc limit 1');
$last_product = $last_product_query->queryRow();
```

#### Bound parameters:

Don't put variables directly into your query to avoid sql injection vulnerability.
Instead, use bound parameters:

```php
$product_name = $_REQUEST['name'];
$insert_query = PT::db()->createCommand('insert into product set name = :name');
$insert_query->bindParam(':name',$product_name)
$insert_query->execute();

```

Prepend each variable name in your query with a colon. You do not need to escape it or put single or double quotes around it. PDO will handle that.

> **Note:** You cannot replace SQL keywords, table or database names with bound parameters.

This example will **NOT** work:

```php
$table = 'products';
$insert_query = PT::db()->createCommand('select * from :table');
$insert_query->bindParam(':table',$table)
$insert_query->queryAll();

```

Use `bindParam()` when you want to inject a variable into a query. If you want to inject a specific value, use `bindValue()` instead.

Since phpThunder uses PDO internally, follow [PDO documentation](http://php.net/manual/en/class.pdostatement.php) for details.


#### Chaining:

Both `createCommand` and binding calls (`bindValue` and `bindParam`) return the query object itself, so you can chain them for increased readability:

```php
PT::db()->createCommand('insert into product set name = :name, sku = :sku')
    ->bindParam(':name',$product_name)
    ->bindValue(':sku',12345)
    ->execute();
```

<a name='extending' class='anchor'></a>
***
## Extending: Imports, Components and Mapping

phpThunder can be easily extended with your own custom classes and methods.

#### Importing

Importing will load specific classes to make them available anywhere in the code without having to call `require()`.

You can import a specific class...
```php
    PT::import('MyClass.php')
    $myObj = new MyClass();
    $myObj->sayHello();
```
...or all the classes within a location:
```php
    PT::import('controllers/*')
```

Imported classes can be accessed directly from the code.


#### Installing components

To install a component (say, "Red") call `install` :

```php
PT::install('red','RedClass');
```
Then you can use it in your code using `PT` class:
```php
PT::red()->sayHello();
```
You can call both static and non-static methods of your component.

First argument of `PT::install()` is the name you want to use when accessing your component, the second is its class name. To install a component, its class must be imported first(see "Importing" section). Component's class will be instantiated each time you access that component.

Third (optional) argument to `PT::install()` specifies additional data that should be passed to the constructor during each instantiation.
Take a look again at the example with database used above:

```php
PT::install('db','PTDatabase',array('mysql:host=localhost;dbname=database1','root','SecretPassword'));
```

#### Mapping custom methods

You can map your own function to the PT class:

```php
PT::map('hello'. function(){
    echo 'Hello!';
});

PT::hello();
```

Same as with routing, you can use an existing function, method, class method or closure.

> **Note:** If you have both a component and a mapped method with the same name, only component will be used.


<a name='views' class='anchor'></a>
***
## Views

**Views** are php files containing visual part of your web application. In most cases it will be HTML, CSS and client-side scripts (JavaScript).
phpThunder can render views, by retrieving their content and populating them with provided data.

To render a view names 'home.php' call:
```php
PT::render('home')
```
> **Note:** You do not need to provide .php extension at the end of the view's name, but you can.

To pass a data to a view, provide an array as a second argument:
```php
PT::render('home', array('name'=>'James','email'=>'test@example.com'))
```
This way both variables `$name` and `$email` will be accessible directly in the view's code.

#### Layouts

Most websites have some content which is shared across all pages within that websites. In most cases it will be header (containing elements like script/style declarations, top navigation etc.) and footer (copyright notes, additional navigation, etc.).

By default, `PT::render()` uses currently selected layout (if any).

To choose/set the layout, call:
```php
PT::layout('layout')
```

> **Note:** phpThunder will keep using the selected layout for all views rendered afterwards, or until you change it again in the code.


Example of layout.php:
```html
<html>
<head>
    <title>My Website</title>
</head>
<body>
    <?=$content?>
<footer>
    (C) 2014 John Smith
</footer>
</body>
</html>
```
> **Note:**  It's a common practice to have main layout file which later calls include() on separate footer and header files.

Note that your layout file **must** contain `$content` variable. That's where the actual content of the view will be placed when you call `PT::render()` with that layout selected.

When a layout is selected it will be loaded each time you call `PT::render()`. To render a view without using a layout (whether one is selected or not), use:
```php
PT::renderView('home')
```

> **Note:** `PT::renderView()` can be also called from inside the views or layout files. This way you can split your view into multiple, manageable elements (sidebar, navigation, logo etc).

#### Returning a content

By default calling `PT::render()` or `PT::renderView()` will show rendered content on the screen.
If you want to parse the view's content and store it instead, call:

```php
$home_page_content = PT::parseView('home')
```
`PT::parseView()` does not use layouts, same as `PT::renderView()`.


<a name='config' class='anchor'></a>
***
## Config

phpThunder offers centrally stored settings which control framework's behavior and can store your web application's settings.
Also, you can use that storage for data that needs to be accessible globally across files/classes. This is recommended solution instead of using global variables.

To set a global variable or setting, call:
```php
PT::set('myVariable',1)
```
to load it:

```php
$myVariable = PT::get('myVariable',1)
```

to clear (unset) the setting:

```php
PT::clear('myVariable',1)
```

to check whether a setting is set:
```php
PT::has('myVariable',1)
```

> **Note:** I recommend to name the settings in a standarized way, all lowercase, divided by sections, each one separated by dot. phpThunder internally uses same config to store its internal settings. To distinguish your settings from framework's settings, prefix them with "app":<BR>
Examples:<BR>
 *app.title*<BR>
 *app.login.max_attempts*<BR>
 *app.author.name*


<a name='cache' class='anchor'></a>
***
## Caching

Caching in phpThunder is implemented by only one method: `PT::cache()`.

```php
function loadNews()
{
    return PT::db()->createCommand('select * from news')->queryAll();
}

$news = PT::cache('news', loadNews);
```

Syntax:
`PT::cache($key,$callback,$expires)`

First argument is the unique **key** to store the data under. Second one is the function (**callback**) that will be called to get the data for the first time (if it's not cached yet). If the data is already in a cache, it will be returned directly without using callback.

Last argument specifies expiration time in seconds. It is supported only for memcache based cache.

#### Storage engines

phpThunder currently supports 4 different store engines which can be used for caching:

* **session** - php session (default). Data cached using session will expire when the session expires.
* **memcache** - using Memcached class. Recommened if memcache is available. Supports expiration.
* **disk** - using a file. Cache files are stored by default in a `cache` folder located in main project directory.
* **memory** - shared memory (using /dev/shm) - only on *nix platforms.

To set a caching method, call for example:
```php
PT::set('cache.method','memory');
```

Memcache can be further configured if port or server are different than default:

```php
PT::set('cache.memcache.port', 11211);
PT::set('cache.memcache.server', localhost);
```

For **disk** method, the location of cached files can be changed:
```php
PT::set('cache.disk.location', 'cache');
```

> **Note:** Again I recommend using standarized keys for the cached variables (lowercase,no special characters,each segment separated by dot). Choice of possible keys is restricted by caching method selected. **Memory** and **disk** cache can use any values for keys, since they are converted to a hash anyway. **session** Accepts anything what could be used as a key in a php array. **Memcache** has its own restrictions. Recommended naming should work for all 4 methods.


<a name='events' class='anchor'></a>
***
## Events

Event is a situation that may occur the during the application runtime. When an object "triggers" a specific event, all the "listening" objects should respond to it properly.

#### Listening for an event

To *start listening* for an event use:
```php
PT::on('error.404', actionPage404);
```
Whenever that event occurs, the callback will get executed.
Currently, you can have only one listener per event. Each time you declare a new listener, it will replace the old one.

To **check** if listener is **defined**:
```php
$is_404_handled = PT::isHandled('error.404');
```

To stop/resume listening for a **specific event**:
```php
PT::stopListening('error.404');
PT::resumeListening('error.404');
```
To stop/resume listening for **_all_ events**:

```php
PT::stopListening();
PT::resumeListening();
```

> **Note:** You do not have to "define" your events anywhere. To create your custom event, just come up with a unique name for it and set up a listener for it with `PT::on()`. Then, specify places in your code that will trigger that event under desired circumstances.

#### Triggering a custom event

Some events (like `error.404` mentioned above) are triggered internally by the phpThunder framework.

All your custom events have to triggered manually.

```php
PT::trigger('app.my.event');
```


<a name='localization' class='anchor'></a>
***
## Localization

phpThunder provides simplified interface for handling multiple languages.

####Setting current language####

```php
PT::set('app.language','en');
// or
PT::setLanguage('en');
```

####Adding translations####

To add a single English translation for a group 'main':

```php
PT::addTranslation('en','main', 'submit_button_label', 'Submit');
```

To add multiple translations to the group 'main':

```php
PT::addTranslations('en','main', array(
   'submit_button_label' => 'Submit',
   'cancel_button_label' => 'Cancel',
));

// or, if you prefer to load a whole translation group from external file:
PT::addTranslations('en','main', include ('app/lang/en/main.php'));
```

In the above example, we added 2 translations into **English** version of **Main** group.

To import all translations:

```php
PT::importTranslations('app/lang');
```

To be able to import all translations at once, the path must contain subdirectory for each supported language with php files for translation groups inside. For example, to load translations for two groups ("main" and "ui") for English and Spanish with the above example, create the following directory structure:

```
/app
    /lang
         /en
            main.php
            ui.php
         /es
            main.php
            ui.php
```




####Translating####

When getting a translation, the first segment is the group name (in our case "main"), the second one is the translation key ("submit_button_label"). If translation doesn't exist, the translation key will be returned instead ("main.submit_button_label" in our case).

```php
$button_label = PT::translate('main.submit_button_label');
```

You can use placeholders in your translations:

```php

PT::addTranslations('en','main', array(
   'welcome_message' => 'Hello, :name. Nice to meet you!',
));

$welcome_message = PT::translate('main.welcome_message', array('name'=>'John'));
```

####Fallback language####

If a translation is not found for a current language, you can specify a **fallback language** that will be used instead.

```php
PT::set('app.fallback_language','en');

//or
PT::setFallbackLanguage('en');
```


####Other useful functions####

Check if translation is defined for a particular word:

```php
$label_exists = PT::hasTranslation('main.submit_button_label');
```




<a name='error' class='anchor'></a>
***
## Error and response handling

#### Responses

##### Function responses

Often functions return a boolean value indicating if an operation went fine.  However, you may need more details than just knowing if operation failed or not. Instead of temporarily storing errors in various locations, phpThunder allows you to provide a full response from a function:

If everything went fine, you may want to pass some additional result (i.e. an ID of newly inserted database record):

```php
return PT::returnSuccess('123');
```

This will return an array:

```
array(
   'status' => 1,
   'data'   => 123
)
```

If something goes wrong, you can pass an error message to describe the issue. That message could later be display for the user:


```php
return PT::returnError('Incorrect login/password');
```

This will return an array:

```
array(
   'status' => 0,
   'data'   => 'Incorrect login/password'
)
```

##### AJAX requests

AJAX requests can also provide more details for both successful and failed requests.

```php
PT::ajaxError($msg,$code=400)
```
`ajaxError()` will terminate the script with proper error message and HTTP status code. If no status code is specified, 400 (Bad Request) will be returned by default.

Other popular status codes you could use for errors are:

- 401 Unauthorized (needs authorization)
- 403 Forbidden (can't perform an operation, whether authorized or not)
- 500 Internal Error (carefully with this one, is very vague, and may be confused with generic php exceptions or errors)
- 501 Not Implemented

4xx codes indicate client error (user provided something wrong), and 5xx codes indicate server error (user sent a valid request, but the server can't process it).

[See the list of all HTTP codes on Wikipedia](http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#4xx_Client_Error)

```php
PT::ajaxSuccess($data=NULL)
```

This will generate HTTP code 200 (Success). Optional data can be provided. That data will be returned as a plain text.

##### AJAX/JSON requests

If you AJAX request uses JSON to provide responses, use the following functions instead:

```php
PT::jsonSuccess($data=NULL);
PT::jsonError($msg,$code=400);
```

This will return same data as `PT::returnSuccess()` or `PT::returnError()` but in JSON format. Both calls terminate the script after response is provided.

#### Errors

phpThunder uses events that are triggered when common internal error occur, for example:

- when a route is defined incorrectly
- when a route callback is not a proper function or doesn't exist
- when a route cannot be resolved (no page found)
etc.

Using Events, you can listen for the errors:

- `error` - listen for any errors
- `error.internal` - listen for internal framework errors
- `error.general` - listen for your application related errors
- `error.404` - listen for a routing issue, where the URL request doesn't match any of the defined routes (page not found)


You can specify your own error handler and trigger your own errors, same as with events:

```php
function handleErrors($e)
{
    die('An error occured:'.$e['msg']);
}

PT::errorHandler(handleErrors);
```

From now on, any errors that occur will go to your handler function. It will receive an array with two fields: 'msg' and 'type'.

phpThunder comes with 2 types of errors already defined (as constants):
```php
const PT_ERROR_GENERAL = 0; // default error type
const PT_ERROR_INTERNAL = 1;
```

You should define your own types of errors.

To generate an error, call `PT::error()` with the error message and (optionally) its type:

```php
PT::error('Here comes error message', PT_MY_ERROR);
```

> **Tip:** Most common solution is to log the errors (along with any available details) and forward the user to an error page. Behavior may very based on the error type.<BR> For example:
>
> - general (`PT_ERROR_GENERAL`) errors will load an error page and show the error message
> - internal (`PT_ERROR_INTERNAL`) errors will load same error page, but with generic message. The actual message can be logged instead.

> **Good Practice:** When your web application is ready and published (production copy) do not provide too many details with the error messages. Especially be careful not to display details related to MySQL errors. Showing them can expose database design or queries you use, which can be used to perform SQL injection attack.



#### Exceptions

phpThunder will catch all exceptions for which you have not provided your own try/catch blocks.

To handle exception, listen to "exception" event:

```php
function myExceptionHandler($e)
{
    die('An exception occured: '.$e->getMessage());
}

PT::on('exception',myExceptionHandler);
```

If you do not specify your own handler, default one will be used.
If your project is in **debug mode**, the exception details along with context will be shown. Otherwise only the message will be displayed.


<a name='debugging' class='anchor'></a>
***
## Debugging

phpThunder can run your application in two different modes:

- **Debug mode** - in that mode, any errors or exceptions caught will provide you with additional debug information (execution stack, data provided, etc.). Use this mode for development and testing, all the way until your website is published.
- **Non-debug (production) mode** - error/exception output is minimal to protect potentially security-sensitive details. Use this setting at all times when you website is publicy accessible.

To enable/disable debug mode use:

```php
PT::debugMode(1);
// or
PT::debugMode(0);
```

Common practice is to enable debug mode based on the user's IP address. This way you can debug errors even on production environment:
```php
PT::debugMode(PT::ip()=='127.0.0.1'?1:0);
```
This setting will work only on locahost. For hosted websites, change the IP with your external IP address.

#### Debug breakpoints

If you want to debug a specific point in a code, you don't need to trigger an error or an exception.

```php
   PT::debug('James',1,2);
```

`PT::debug()` will terminate the script, dumping the data you have provided (any number of arguments can be passed to that function), along with source code and stack trace.

> **Note:**  `PT::debug()` works only in **Debug Mode**. Outside it, debug calls are ignored.

#### Logging

phpThunder has built-in logging capability.

```php
   PT::log(PT::LOG_ERROR,'Something went wrong!');
```
phpThunder supports 5 logging levels (`LOG_CRITICAL`,`LOG_ERROR`,`LOG_WARNING`,`LOG_INFO`,`LOG_DEBUG`)
You can also use one of the log functions for each level:

```php
   PT::logCritical ('critical error, cannot recover, script usually terminates');
   PT::logError ('important issue with significant impact, but can recover');
   PT::logWarning ('issue with lesser impact, script can continue');
   PT::logInfo ('store an information');
   PT::logDebug ('store verbose data (variables, session dumps etc.');
```



**Logfile location**

By default, all data is being logged to a file 'pt.log' in your website's root directory. You can change it in the options.

```php
   PT::set('log.file','logs/mylog.txt');
```

**Debug level**

In debug mode, all 5 logging levels are stored, in production mode only `LOG_WARNING` and above.
You can change debug level at any time.

```php
   PT::set('log.level',PT::LOG_INFO);
```

**Browser Console Interface**

phpThunder provides an interface to browser's console() object. Instead of logging events to files or database, you can display them directly in your JavaScript console.

```php
   PT::consoleLog('A = '.$a);
   PT::consoleError('An error occured!');
   PT::consoleInfo('I got here');
   PT::consoleDebug('Request data = ', $_REQUEST);
   PT::consoleWarning('We should not get here!');
```

All console functions accept any number of arguments and does not support logging levels. Both simple variables are supported, as well as arrays and objects which will be shown in JSON format.
Each entry will be prefixed with 'pt:' to stand out from regular javascript logging.

> **Note:** Console logging is disabled outside debug mode. Console object is not supported by some browsers. Latest Chrome is recommended.

Sometimes you may find it difficult to find your PHP debugging data when it's lost in tons of other javascript output. You can use your browser's Development Console to search for "pt:" prefix to filter out the data coming directly from phpThunder. Alternatively, you can enable colorful output which will make your data easily stand out from the crown:

```php
PT::set('debug.console_colors',1);
```


<a name='helpers' class='anchor'></a>
***
## Helper Functions

phpThunder provides a few helpful functions you can use in your project.

- `PT::ip()` - return user's IP
- `PT::param($name)` - get a parameter from $_REQUEST
- `PT::slug($string)` - convert `$string` into normalized string, which can be used for permanent URLs or as a config/cache key name.
- `PT::redirect($url,$type=302)` - will redirect a user to a provided url. Optional `type` parameter can differentiate between temporary redirect (302, default) or Permanent (301).
- `PT::isCLI()` - checks whether the script was called in Command Line Interface (CLI) instead of being served by web server.
- `PT::isMobile()` - checks if the user is using a mobile device (phone,tablet).
- `PT::isAjax()` - checks if the script was called directly (while navigating through pages or typing the URL) or if it originated from within another page as an AJAX request. This is experimental feature. Results may not be 100% accurate, especially when you script may be loaded in Ajax mode by external website (not yours).



<a name='cli' class='anchor'></a>
***
## Command line (commands)

In most cases your project will be accessible from the Web as a web application.
However, you can use phpThunder to write php scripts for command line, executable from a shell.

There are tasks which are better handled from command line or that should not be publicly accessible for example cron scripts, backup scripts etc.
Instead of creating a separate php file for each, all of them can share your project's environment by declaring them as a "command".

**Command** is a function that can be only called from command line.

To define a new command, type:

```php
PT::command('test',function($args){
    echo "I am a command!";
    echo "I got some data: ";
    var_dump($args);
});
```
The callback you define will receive an array (`$args` in our example) with all the arguments that were passed through command line.

To see defined commands, simply type in the terminal:

```
php index.php
```

To call a specific command, provide its name after `index.php`. Command name may be followed by optional arguments (each one separated by space):

```
php index.php test 1 2 3
```
