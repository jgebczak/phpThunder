<?php

class PTHelper
{

//----------------------------------------------------------------------------------------------------------------------

    static function param($name)
    {
        return $_REQUEST[$name];
    }

//----------------------------------------------------------------------------------------------------------------------

    static function redirect($url,$type=302)
    {
        if ($type == 301)
        {
            header("HTTP/1.1 301 Moved Permanently");
        }

        header("Location: $url");

        die();
    }

//----------------------------------------------------------------------------------------------------------------------

    static function isCLI()
    {
        return (php_sapi_name() == "cli") ? 1:0;
    }

//----------------------------------------------------------------------------------------------------------------------

    static function isAjax()
    {
        /*
        TODO: add this to client side too?
        xmlHttpRequestObject.open(“GET”,url,true);
        xmlHttpRequestObject.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        */
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' );
    }

//----------------------------------------------------------------------------------------------------------------------

    static function isMobile()
    {
        $user_agent = strtolower ( $_SERVER['HTTP_USER_AGENT'] );
        if ( preg_match ( "/phone|iphone|itouch|ipod|symbian|android|htc_|htc-|palmos|blackberry|opera mini|iemobile|windows ce|nokia|fennec|hiptop|kindle|mot |mot-|webos\/|samsung|sonyericsson|^sie-|nintendo/", $user_agent ) )
                return true;

        return false;
    }

//----------------------------------------------------------------------------------------------------------------------

    static function slug ($str, $replace=array(), $delimiter='-')
    {
        if( !empty($replace) ) {
            $str = str_replace((array)$replace, ' ', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

        return $clean;
    }

//----------------------------------------------------------------------------------------------------------------------

}

?>