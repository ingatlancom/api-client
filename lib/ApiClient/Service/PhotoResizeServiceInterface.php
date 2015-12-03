<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 03/12/15
 * Time: 10:45
 */
namespace IngatlanCom\ApiClient\Service;

interface PhotoResizeServiceInterface
{
    /**
     * @param string $path
     * @return string
     * @throws \Exception
     */
    public function getResizedPhotoData($path);
}