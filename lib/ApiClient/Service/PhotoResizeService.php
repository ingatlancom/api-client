<?php
namespace IngatlanCom\ApiClient\Service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use IngatlanCom\ApiClient\Service\Image\ImageException;
use IngatlanCom\ApiClient\Service\Image\ImageGD;
use IngatlanCom\ApiClient\Service\Image\ImageImagick;
use IngatlanCom\ApiClient\Service\Image\ImageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Képátméretezés közös részei, pl. file műveletek, file letöltés
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
     * @param integer              $imageLibrary PhotoResizeService::LIB_GD vagy default:PhotoResizeService::LIB_IMAGICK osztálykonstansok
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
     * @throws Exception|GuzzleException
     */
    public function getResizedPhotoData(string $path)
    {
        if ('http' == substr(strtolower($path), 0, 4)) {
            $response = $this->client->request('GET', $path);
            $contents = $response->getBody();
        } else {
            $contents = file_get_contents($path);
            if (false === $contents) {
                throw new ImageException('Photo not found: ' . $path);
            }
        }

        return $this->resizePhoto($contents);
    }

    /**
     * Képek letöltése, átméretezése
     *
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @param bool  $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return array fotó byte-ok|Exception-ok fotó saját azonosító szerint
     */
    public function getResizedPhotosData(array $photosByOwnId, bool $paralellDownload = false)
    {
        $results = [];
        $requests = [];

        foreach ($photosByOwnId as $ownId => $photo) {
            $path = $photo['location'];

            if ($paralellDownload && 'http' == substr(strtolower($path), 0, 4)) {
                $requests[$ownId] = new Request('GET', $path);
            } else {
                try {
                    $results[$ownId] = $this->getResizedPhotoData($path);
                } catch (Exception $e) {
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
    private function getResizedPhotosDataByRequests(array $requests)
    {
        $results = [];
        $promises = [];

        if (count($requests) > 0) {
            try {
                foreach ($requests as $key => $request) {
                    $promises[$key] = $this->client->sendAsync($request);
                }
            } catch (TransferException $e) {
            }

            Each::of(
                $promises,
                function ($value, $idx) use (&$results): void {
                    $results[$idx] = ['state' => PromiseInterface::FULFILLED, 'value' => $value];
                },
                function ($reason, $idx) use (&$results): void {
                    $results[$idx] = ['state' => PromiseInterface::REJECTED, 'value' => $reason];
                }
            )->wait();
        }

        foreach ($results as $ownId => $response) {
            if ($response['state'] == PromiseInterface::REJECTED) {
                $results[$ownId] = $response['value'];
            } else {
                try {
                    $results[$ownId] = $this->resizePhoto($response['value']->getBody());
                } catch (Exception $e) {
                    $results[$ownId] = $e;
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
    private function isGrayScale(ImageInterface $img, float $tolerancePercentage = 95.0)
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
     * @param string $imageData
     * @return ImageInterface
     * @throws ImageException
     */
    private function loadImage(string $imageData)
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
     * @throws ImageException
     */
    private function resizePhoto(string $imageData)
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
