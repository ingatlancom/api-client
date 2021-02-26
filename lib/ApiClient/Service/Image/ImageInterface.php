<?php

namespace IngatlanCom\ApiClient\Service\Image;

/**
 * Kép absztrakció interface
 */
interface ImageInterface
{
    /**
     * Kép betöltése adatokból
     *
     * @param string $imageBytes
     * @return ImageInterface
     * @throws ImageException
     */
    public static function createFromBytes($imageBytes);

    /**
     * Szélesség
     *
     * @return mixed
     */
    public function getWidth();

    /**
     * Magasság
     *
     * @return mixed
     */
    public function getHeight();

    /**
     * Pixel színe
     *
     * @param int $x
     * @param int $y
     * @return array kulcsok: r, g, b
     */
    public function getPixelColor($x, $y);

    /**
     * Átméretezett kép elkészítése
     *
     * @param int $maxWidth
     * @param int $maxHeight
     * @return ImageInterface
     */
    public function createResizedMaximizedImage($maxWidth, $maxHeight);

    /**
     * Jpeg reprezentáció
     *
     * @return string jpeg byteok
     */
    public function getJpegBytes();

    /**
     * Memória felszabadítás
     */
    public function __destruct();
}
