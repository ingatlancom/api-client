<?php

namespace IngatlanCom\ApiClient\Service\Image;

/**
 * Interface ImageInterface
 *
 * Kép absztrakció interface
 *
 * @package IngatlanCom\ApiClient\Service\Image
 */
interface ImageInterface
{
    /**
     * Kép betöltése adatokból
     *
     * @param $imageBytes
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
     * @param $x
     * @param $y
     * @return array kulcsok: r, g, b
     */
    public function getPixelColor($x, $y);

    /**
     * Átméretezett kép elkészítése
     *
     * @param $maxWidth
     * @param $maxHeight
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
     *
     * Ezt követően már nem szabad az objektumot használni!
     *
     * @return mixed
     */
    public function destroy();
}
