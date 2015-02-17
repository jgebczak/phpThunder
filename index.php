<?php
require ('PT.php');

PT::route('*', function(){
    echo 'Hello World!';
});

PT::start();
?>