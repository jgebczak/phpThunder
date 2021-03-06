<?php

class PTResponse
{

//----------------------------------------------------------------------------------------------------------------------

    static function returnSuccess($data=NULL)
    {
        return array('status'=>1, 'data' => $data);
    }

//----------------------------------------------------------------------------------------------------------------------

    static function returnError($msg='')
    {
        return array('status'=>0, 'msg' => $msg);
    }

//----------------------------------------------------------------------------------------------------------------------

    static function ajaxError($msg,$code=400)
    {
        header("HTTP/1.1 $code $msg");
        die();
    }

//----------------------------------------------------------------------------------------------------------------------

    static function abort($code,$msg=NULL)
    {
        header("HTTP/1.1 $code $msg");
        die();
    }

//----------------------------------------------------------------------------------------------------------------------


    static function ajaxSuccess($data=NULL)
    {
        header("HTTP/1.1 200 OK");
        echo($data);
        die();
    }

//----------------------------------------------------------------------------------------------------------------------


    static function jsonSuccess($data=NULL)
    {
        header("HTTP/1.1 200 OK");
        echo json_encode(PTResponse::returnSuccess($data));
        die();
    }

//----------------------------------------------------------------------------------------------------------------------


    static function jsonError($msg,$code=400)
    {
        header("HTTP/1.1 $code $msg");
        echo json_encode(PTResponse::returnError($msg));
        die();
    }

//----------------------------------------------------------------------------------------------------------------------

}

?>