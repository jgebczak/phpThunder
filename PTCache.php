<?php

class PTCache
{

//----------------------------------------------------------------------------------------------------------------------

    static function cache($key, $action, $expires=0)
    {
        $method = PT::get('cache.method');
        $key    = PT::slug($key);

        if (!$key)
        {
                PT::error("Cache key cannot be empty",PT_ERROR_INTERNAL);
                return;
        }

        if ($method=='memcache')
        {
            if (!class_exists('Memcached'))
            {
                PT::error('Memcache is not installed.',PT_ERROR_INTERNAL);
                return;
            }
            $mc = new Memcached();
            $mc->addServer(PT::get('cache.memcache.server'), PT::get('cache.memcache.port'));
            $val = $mc->get($key);

            if (!$val)
            {
                $val = PT::invokeHandler($action);
                $mc->set($key,$val,$expires);
            }
            return $val;
        }

        if ($method=='session')
        {
            $val = $_SESSION['cache'][$key];
            if (!$val)
            {
                $val = PT::invokeHandler($action);
                $_SESSION['cache'][$key] = $val;
            }
            return $val;
        }

        if ($method=='disk')
        {
            $path =  rtrim(PT::get('cache.disk.location'),'/');
            if (!file_exists($path))
            {
                PT::error("Cache folder '$path' does not exist",PT_ERROR_INTERNAL);
                return;
            }
            $key = md5($key);
            $f = $path.'/'.$key;

            if (file_exists($f))
            {
                $val = file_get_contents($f);
                return $val;
            }
            $val = PT::invokeHandler($action);
            file_put_contents($f, $val);
            return $val;
        }

        if ($method=='memory')  // shared memory = /dev/shm
        {
            $path =  '/dev/shm';
            if (!file_exists($path))
            {
                PT::error("/dev/shm does't exist. Not a linux/unix OS or shared memory not accessible.",PT_ERROR_INTERNAL);
                return;
            }

            // it's shared memory so there may be multiple webapps writing there. Using hostname will avoid conflicts.
            $key = md5($key);
            $host = $_SERVER['HTTP_HOST'];

            if (!file_exists($path.'/pt-cache'))
                mkdir($path.'/pt-cache');

            if (!file_exists($path.'/pt-cache/'.$host))
                mkdir($path.'/pt-cache/'.$host);

            $f = "$path/pt-cache/$host/$key";

            if (file_exists($f))
            {
                $val = file_get_contents($f);
                return $val;
            }
            $val = PT::invokeHandler($action);
            file_put_contents($f, $val);
            return $val;
        }

    }

//----------------------------------------------------------------------------------------------------------------------


    static function clearCache()
    {
        $method = PT::get('cache.method');

        if ($method=='memcache')
        {
            if (!class_exists('Memcached'))
            {
                PT::error('Memcache is not installed.',PT_ERROR_INTERNAL);
                return;
            }
            $mc = new Memcached();
            $mc->addServer(PT::get('cache.memcache.server'), PT

                ::get('cache.memcache.port'));
            $mc->flush();
        }

        if ($method=='session')
        {
            unset($_SESSION['cache']);
        }

        if ($method=='disk')
        {
            $path =  rtrim(PT::get('cache.disk.location'),'/');
            if (!file_exists($path))
            {
                PT::error("Cache folder '$path' does not exist",PT_ERROR_INTERNAL);
                return;
            }

            if ($handle = opendir($path)) {
                while (false !== ($file = readdir($handle)))
                {
                    if ($file != "." && $file != "..") {
                        unlink($path.'/'.$file);
                    }
                }
            }
        }

        if ($method=='memory')  // shared memory = /dev/shm
        {
            $path =  '/dev/shm';
            if (!file_exists($path))
            {
                PT::error("/dev/shm does't exist. Not a linux/unix OS or shared memory not accessible.",PT_ERROR_INTERNAL);
                return;
            }

            // no pt-cache folder? no cache, nothing to delete!
            if (!file_exists($path.'/pt-cache'))
                return;

            // it's shared memory so there may be multiple webapps writing there. Using hostname will avoid conflicts.
            $host = $_SERVER['HTTP_HOST'];
            $fpath = "$path/pt-cache/$host";

            if ($handle = opendir($fpath)) {
                while (false !== ($file = readdir($handle)))
                {
                    if ($file != "." && $file != "..") {
                        unlink($fpath.'/'.$file);
                    }
                }
            }
        }

    }

//----------------------------------------------------------------------------------------------------------------------

}

?>