<?php

namespace IngatlanCom\ApiClient\Service\Image;

/**
 * Class ImageGD
 *
 * GD kép wrapper
 *
 * @package IngatlanCom\ApiClient\Service\Image
 */
class ImageGD implements ImageInterface
{
    /**
     * GD image
     *
     * @var resource
     */
    protected $img;

    /**
     * Konstruktor
     *
     * @param resource $img GD image
     */
    public function __construct($img)
    {
        $this->img = $img;
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromBytes($imageBytes)
    {
        $img = imagecreatefromstring($imageBytes);
        if (false === $img) {
            throw new \Exception('Hibás kép!');
        }

        return new static($img);
    }

    /**
     * {@inheritdoc}
     */
    public function getWidth()
    {
        return imagesx($this->img);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeight()
    {
        return imagesy($this->img);
    }

    /**
     * {@inheritdoc}
     */
    public function getPixelColor($x, $y)
    {
        $rgb = imagecolorat($this->img, $x, $y);
        $color = array(
            'r' => ($rgb >> 16) & 0xFF,
            'g' => ($rgb >> 8) & 0xFF,
            'b' => $rgb & 0xFF
        );

        return $color;
    }

    /**
     * {@inheritdoc}
     */
    public function createResizedMaximizedImage($maxHeight, $maxWidth)
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        $newWidth = $width;
        $newHeight = $height;
        if ($newHeight > $maxHeight) {
            $newWidth = ($maxHeight / $newHeight) * $newWidth;
            $newHeight = $maxHeight;
        }
        if ($newWidth > $maxWidth) {
            $newHeight = ($maxWidth / $newWidth) * $newHeight;
            $newWidth = $maxWidth;
        }

        $img = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($img, $this->img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return new static($img);
    }

    /**
     * {@inheritdoc}
     */
    public function getJpegBytes()
    {
        ob_start();
        imagejpeg($this->img);

        return ob_get_contents();
    }

    /**
     * {@inheritdoc}
     */
    public function destroy()
    {
        if ($this->img) {
            imagedestroy($this->img);
        }

        $this->img = null;
    }
}
