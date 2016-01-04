<?php

namespace IngatlanCom\ApiClient\Service;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\MultiTransferException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestInterface;
use IngatlanCom\ApiClient\Service\Image\ImageException;
use IngatlanCom\ApiClient\Service\Image\ImageGD;
use IngatlanCom\ApiClient\Service\Image\ImageImagick;
use IngatlanCom\ApiClient\Service\Image\ImageInterface;

/**
 * Képátméretezés közös részei, pl. file műveletek, file letöltés
 *
 * @package IngatlanCom\ApiClient\Service
 */
class PhotoResizeService
{
    /**
     * GD használata képméretezéshez
     */
    const LIB_GD = 1;

    /**
     * ImageMagick használata képméretezéshez
     */
    const LIB_IMAGICK = 2;

    protected $minWidth = 800;
    protected $minHeight = 600;

    protected $maxWidth = 800;
    protected $maxHeight = 600;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var integer PhotoResizeService::LIB_GD vagy PhotoResizeService::LIB_IMAGICK osztálykonstansok
     */
    protected $imageLibrary;

    /**
     * Konstruktor
     *
     * @param integer $imageLibrary PhotoResizeService::LIB_GD vagy default:PhotoResizeService::LIB_IMAGICK osztálykonstansok
     * @param ClientFactoryService $clientFactoryService Guzzle kliens factory
     */
    public function __construct($imageLibrary = null, ClientFactoryService $clientFactoryService = null)
    {
        if (null === $imageLibrary) {
            $imageLibrary = extension_loaded('imagick') ? static::LIB_IMAGICK : static::LIB_GD;
        }

        $this->imageLibrary = $imageLibrary;

        $clientFactoryService = null != $clientFactoryService ? $clientFactoryService : new ClientFactoryService();
        $this->client = $clientFactoryService->getClient();
    }

    /**
     * Fotó átméretezés
     *
     * @param string $path kép elérési útvonal
     * @return string kép byteok
     * @throws \Exception
     */
    public function getResizedPhotoData($path)
    {
        if ('http' == substr(strtolower($path), 0, 4)) {
            $response = $this->client->get($path)->send();
            $contents = $response->getBody(true);
        } else {
            $contents = file_get_contents($path);
            if (false === $contents) {
                throw new ImageException('Photo not found: ' . $path);
            }
        }

        $fileData = $this->resizePhoto($contents);

        return $fileData;
    }

    /**
     * Képek letöltése, átméretezése
     *
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @param bool $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return array fotó byte-ok|Exception-ok fotó saját azonosító szerint
     */
    public function getResizedPhotosData(array $photosByOwnId, $paralellDownload = false)
    {
        $results = array();
        $requests = array();

        foreach ($photosByOwnId as $ownId => $photo) {
            $path = $photo['location'];

            if ($paralellDownload && 'http' == substr(strtolower($path), 0, 4)) {
                $requests[$ownId] = $this->client->get($path);
            } else {
                try {
                    $results[$ownId] = $this->getResizedPhotoData($path);
                } catch (\Exception $e) {
                    $results[$ownId] = $e;
                }
            }
        }

        $results = $results + $this->getResizedPhotosDataByRequests($requests);

        return $results;
    }

    /**
     * Párhuzamos képletöltés
     *
     * @param RequestInterface[] $requests
     * @return array fotó byte-ok|Exception-ok fotó saját azonosító szerint
     */
    private function getResizedPhotosDataByRequests($requests)
    {
        $results = array();

        if (count($requests)) {
            try {
                $this->client->send($requests);
            } catch (MultiTransferException $e) {
            }

            /** @var Request $request */
            foreach ($requests as $ownId => $request) {
                if ($request->getResponse()->isError()) {
                    $results[$ownId] = BadResponseException::factory($request, $request->getResponse());
                } else {
                    try {
                        $results[$ownId] = $this->resizePhoto($request->getResponse()->getBody(true));
                    } catch (\Exception $e) {
                        $results[$ownId] = $e;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Szürkeárnyalatos-e a kép (pl. alaprajz "fekete-fehér-e"?)
     *
     * @param ImageInterface $img
     * @param float $tolerancePercentage default 95 (%)
     * @return boolean
     */
    private function isGrayScale(ImageInterface $img, $tolerancePercentage = 95.0)
    {
        $width = $img->getWidth();
        $height = $img->getHeight();
        $grayPixelCount = 0;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = $img->getPixelColor($x, $y);
                if ($color['r'] == $color['g'] && $color['r'] == $color['b']) {
                    $grayPixelCount++;
                }
            }
        }

        // Legalább $tolerance százalékban szürke-e?
        return $grayPixelCount > $width * $height * $tolerancePercentage / 100;
    }

    /**
     * Méretvalidációk
     *
     * @param ImageInterface $img
     * @throws ImageException
     * @return array $img, $width, $height
     */
    private function validate($img)
    {
        $width = $img->getWidth();
        $height = $img->getHeight();
        if ($width < $this->minWidth && $height < $this->minHeight) {
            if (450 == $width && 450 == $height) {
                if (!$this->isGrayScale($img)) {
                    $msg = '450x450 pixel méretű alaprajzokat kérjük, a http://alaprajz.ingatlan.com oldalon készítsen!';
                    throw new ImageException($msg);
                }
            } else {
                throw new ImageException('Ön 800x600 pixelnél kisebb képet próbált meg feltölteni. Kérjük, hogy töltsön fel legalább 800x600 pixel felbontású képet.');
            }
        }

        return array($img, $width, $height);
    }

    /**
     * Kép betöltése
     *
     * @param $imageData
     * @return \IngatlanCom\ApiClient\Service\Image\ImageInterface
     */
    private function loadImage($imageData)
    {
        if (static::LIB_IMAGICK == $this->imageLibrary) {
            $img = ImageImagick::createFromBytes($imageData);
        } else {
            $img = ImageGD::createFromBytes($imageData);
        }

        $this->validate($img);

        return $img;
    }

    /**
     * Kép átméretezése
     *
     * @param string $imageData forrás kép byteok
     * @return string átméretezett kép byteok
     */
    private function resizePhoto($imageData)
    {
        $img = $this->loadImage($imageData);
        $width = $img->getWidth();
        $height = $img->getHeight();

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $resizedImg = $img->createResizedMaximizedImage($this->maxWidth, $this->maxHeight);
            $img = $resizedImg;
        }

        $imageData = $img->getJpegBytes();

        return $imageData;
    }
}
