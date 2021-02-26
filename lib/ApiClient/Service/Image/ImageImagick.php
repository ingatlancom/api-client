<?php

namespace IngatlanCom\ApiClient\Service\Image;

use Exception;
use Imagick;

/**
 * ImageMagick kép wrapper
 */
class ImageImagick implements ImageInterface
{
    /**
     * @var Imagick
     */
    protected $img;

    /**
     * @param Imagick $img ImageMagick image
     */
    public function __construct(Imagick $img)
    {
        $this->img = $img;
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromBytes($imageBytes)
    {
        try {
            $img = new Imagick();
            $img->readImageBlob($imageBytes);
        } catch (Exception $we) {
            throw new ImageException('Hibás kép!');
        }

        return new static($img);
    }

    /**
     * {@inheritdoc}
     */
    public function getWidth()
    {
        return $this->img->getImageWidth();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeight()
    {
        return $this->img->getImageHeight();
    }

    /**
     * {@inheritdoc}
     */
    public function getPixelColor($x, $y)
    {
        $colorObject = $this->img->getImagePixelColor($x, $y);

        return $colorObject->getColor();
    }

    /**
     * {@inheritdoc}
     */
    public function createResizedMaximizedImage($maxWidth, $maxHeight)
    {
        $img = clone $this->img;
        $img->resizeImage($maxWidth, $maxHeight, Imagick::FILTER_LANCZOS, 1, true);

        return new static($img);
    }

    /**
     * {@inheritdoc}
     */
    public function getJpegBytes()
    {
        return $this->img->getImageBlob();
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        if ($this->img) {
            $this->img->destroy();
        }

        $this->img = null;
    }
}
