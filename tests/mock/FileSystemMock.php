<?php
use Stash\Driver\AbstractDriver;

/**
 * Stash filesystem driver emulator
 */
class FileSystemMock extends AbstractDriver
{
    /**
     * @var array $store
     */
    protected $store;

    /**
     * @inheritdoc
     */
    protected function setOptions(array $options = array())
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getData($key)
    {
        $keyString = self::makeKey($key);
        if (isset($this->store[$keyString])) {
            return $this->store[$keyString];
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function storeData($key, $data, $expiration)
    {
        $apckey = $this->makeKey($key);
        $store = array('data' => $data, 'expiration' => $expiration);
        $this->store[$apckey] = $store;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($key = null)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        return true;
    }

    /**
     * Turns a key array into a string.
     *
     * @param  array  $key
     * @return string
     */
    protected function makeKey($key)
    {
        $keyString = md5(__FILE__) . '::'; // make it unique per install

        foreach ($key as $piece) {
            $keyString .= $piece . '::';
        }

        return $keyString;
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent()
    {
        return true;
    }
}
