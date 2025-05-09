<?php
use Scriptmancer\Kiler\Container;

if (!function_exists('container')) {
    function container(): Container
    {
        return Container::getInstance();
    }
}
