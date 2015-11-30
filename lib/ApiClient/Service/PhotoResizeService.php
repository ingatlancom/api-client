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
    /**
     * Use GD
     */
    const LIB_GD = 1;

    /**
     * Use ImageMagick
     */
    const LIB_IMAGICK = 2;

    private $minWidth = 800;
    private $minHeight = 600;

    private $maxWidth = 800;
    private $maxHeight = 600;

    /**
     * @var integer PhotoResizeService::LIB_GD, PhotoResizeService::LIB_IMAGICK
     */
    private $imageLibrary;

    public function __construct($imageLibrary = null)
    {
        if (null === $imageLibrary) {
            $imageLibrary = extension_loaded('imagick') ? static::LIB_IMAGICK : static::LIB_GD;
        }

        $this->imageLibrary = $imageLibrary;
    }

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

    /**
     * Szürkeárnyalatos-e a kép (pl. alaprajz "fekete-fehér-e"?)
     *
     * @param \Imagick|resource $img
     * @param float $tolerancePercentage default 95 (%)
     * @return boolean
     */
    private function isGrayScale($img, $tolerancePercentage = 95.0)
    {
        return ($img instanceof \Imagick) ? $this->isGrayScaleImagick($img, $tolerancePercentage) : $this->isGrayScaleGD($img, $tolerancePercentage);
    }

    /**
     * GD-vel: Szürkeárnyalatos-e a kép (pl. alaprajz "fekete-fehér-e"?)
     *
     * @param \Imagick $img
     * @param float $tolerancePercentage default 95 (%)
     * @return boolean
     */
    private function isGrayScaleImagick($img, $tolerancePercentage = 95.0)
    {
        $width = $img->getImageWidth();
        $height = $img->getImageHeight();
        $grayPixelCount = 0;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $colorObject = $img->getImagePixelColor($x, $y);
                $color = $colorObject->getColor();

                if ($color['r'] == $color['g'] && $color['r'] == $color['b']) {
                    $grayPixelCount++;
                }
            }
        }

        // Legalább $tolerance százalékban szürke-e?
        return $grayPixelCount > $width * $height * $tolerancePercentage / 100;
    }

    /**
     * ImageMagick-kel: Szürkeárnyalatos-e a kép (pl. alaprajz "fekete-fehér-e"?)
     *
     * @param resource $img
     * @param float $tolerancePercentage default 95 (%)
     * @return boolean
     */
    private function isGrayScaleGD($img, $tolerancePercentage = 95.0)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $grayPixelCount = 0;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($img, $x, $y);
                // extract each value for r, g, b
                $color = array(
                    'r' => ($rgb >> 16) & 0xFF,
                    'g' => ($rgb >> 8) & 0xFF,
                    'b' => $rgb & 0xFF
                );

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
     * @param \Imagick|resource $img
     * @throws \Exception
     * @return array $img, $width, $height
     */
    private function validate($img)
    {
        if (static::LIB_IMAGICK == $this->imageLibrary) {
            $width = $img->getImageWidth();
            $height = $img->getImageHeight();
        } else {
            $width = imagesx($img);
            $height = imagesy($img);
        }

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
     * @return \Imagick|resource|resource Imagick or GD resource
     * @throws \Exception
     */
    private function loadImage($imageData)
    {
        if (static::LIB_IMAGICK == $this->imageLibrary) {
            try {
                $img = new \Imagick();
                $img->readImageBlob($imageData);
            } catch (\Exception $we) {
                throw new \Exception('Hibás kép!');
            }
        } else {
            $img = \imagecreatefromstring($imageData);
            if (false === $img) {
                throw new \Exception('Hibás kép!');
            }
        }

        $result = $this->validate($img);

        return $result;
    }

    private function resizePhoto($imageData)
    {
        list($img, $width, $height) = $this->loadImage($imageData);

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            if ($img instanceof \Imagick) {
                $img->resizeImage($this->maxWidth, $this->maxHeight, \Imagick::FILTER_LANCZOS, 1, true);
                $imageData = $img->getImageBlob();
            } else {
                $newWidth = $width;
                $newHeight = $height;
                if ($newHeight > $this->maxHeight) {
                    $newWidth = ($this->maxHeight / $newHeight) * $newWidth;
                    $newHeight = $this->maxHeight;
                }
                if ($newWidth > $this->maxWidth) {
                    $newHeight = ($this->maxWidth / $newWidth) * $newHeight;
                    $newWidth = $this->maxWidth;
                }
                $newImg = imagecreatetruecolor($width, $height);
                imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($img);
                $img = $newImg;
                ob_start();
                imagejpeg($newImg);
                $imageData = ob_get_contents();
                imagedestroy($newImg);
            }
        }

        // Memória felszabadítása
        if (isset($img)) {
            if ($img instanceof \Imagick) {
                $img->clear();
                $img->destroy();
            } elseif (is_resource($img)) {
                imagedestroy($img);
            }
        }

        return $imageData;
    }

}
