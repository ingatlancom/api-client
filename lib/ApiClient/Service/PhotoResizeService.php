<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.13.
 * Time: 11:42
 */

namespace IngatlanCom\ApiClient\Service;


use Guzzle\Http\Client;

class PhotoResizeService
{
    private $minWidth = 800;
    private $minHeight = 600;

    private $maxWidth = 800;
    private $maxHeight = 600;

    /**
     * @param $location
     * @return string
     * @throws \Exception
     */
    public function getResizedPhotoData($location)
    {
        if ('http' == substr(strtolower($location), 0, 4)) {
            $contents = $this->downloadFileContents($location);
        } else {
            $contents = file_get_contents($location);
        }

        $fileData = $this->resizePhoto($contents);

        return $fileData;
    }

    private function downloadFileContents($url)
    {
        $client = new Client();
        $response = $client->get($url)->send();

        return $response->getBody(true);
    }


    private function resizePhoto($imageData)
    {
        //getimagesizefromstring for php 5.3
        list($width, $height) = getimagesize('data://application/octet-stream;base64,'  . base64_encode($imageData));

        if ($width < $this->minWidth && $height < $this->minHeight) {
            throw new \Exception('Ön 800x600 pixelnél kisebb képet próbált meg feltölteni. Kérjük, hogy töltsön fel legalább 800x600 pixel felbontású képet.');
        }

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            //TODO: megirni gd-ben is
            $imagick = new \Imagick();
            $imagick->readImageBlob($imageData);
            $imagick->resizeImage($this->maxWidth, $this->maxHeight, \Imagick::FILTER_LANCZOS, 1, true);
            $imageData = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();
        }

        return $imageData;
    }

}
