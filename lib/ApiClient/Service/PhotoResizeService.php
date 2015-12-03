<?php

namespace IngatlanCom\ApiClient\Service;

use Guzzle\Http\Client;
use IngatlanCom\ApiClient\Service\Image\ImageGD;
use IngatlanCom\ApiClient\Service\Image\ImageImagick;
use IngatlanCom\ApiClient\Service\Image\ImageInterface;

/**
 * Class PhotoResizeAbstractService
 *
 * Képátméretezés közös részei, pl. file műveletek
 *
 * @package IngatlanCom\ApiClient\Service
 */
class PhotoResizeService implements PhotoResizeServiceInterface
{
    /**
     * Use GD
     */
    const LIB_GD = 1;

    /**
     * Use ImageMagick
     */
    const LIB_IMAGICK = 2;

    protected $minWidth = 800;
    protected $minHeight = 600;

    protected $maxWidth = 800;
    protected $maxHeight = 600;

    /**
     * @var integer PhotoResizeService::LIB_GD vagy PhotoResizeService::LIB_IMAGICK osztálykonstansok
     */
    protected $imageLibrary;

    /**
     * Konstruktor
     *
     * @param integer $imageLibrary PhotoResizeService::LIB_GD vagy default:PhotoResizeService::LIB_IMAGICK osztálykonstansok
     */
    public function __construct($imageLibrary = null)
    {
        if (null === $imageLibrary) {
            $imageLibrary = extension_loaded('imagick') ? static::LIB_IMAGICK : static::LIB_GD;
        }

        $this->imageLibrary = $imageLibrary;
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
            $contents = $this->downloadFileContents($path);
        } else {
            $contents = file_get_contents($path);
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
     * @throws \Exception
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
                    throw new \Exception($msg);
                }
            } else {
                throw new \Exception('Ön 800x600 pixelnél kisebb képet próbált meg feltölteni. Kérjük, hogy töltsön fel legalább 800x600 pixel felbontású képet.');
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
            $img->destroy();
            $img = $resizedImg;
        }

        $imageData = $img->getJpegBytes();

        $img->destroy();

        return $imageData;
    }

}
