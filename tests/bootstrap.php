<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/tests/mock/ClientFactoryMockService.php';
require dirname(__DIR__) . '/tests/PhotoResizeServiceTestAbstract.php';

if (!function_exists('getimagesizefromstring')) {
    function getimagesizefromstring($string_data)
    {
        $uri = 'data://application/octet-stream;base64,' . base64_encode($string_data);
        return getimagesize($uri);
    }
}
