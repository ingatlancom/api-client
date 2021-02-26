<?php
namespace IngatlanCom\ApiClient\Enum;

use ReflectionClass;
use ReflectionException;

/**
 * A lehetséges fotó címkéket tartalmazó osztály
 */
class PhotoLabelEnum
{
    /** alaprajz */
    const ALAPRAJZ = 1;
    /** térkép */
    const TERKEP = 2;
    /** erkély */
    const ERKELY = 3;
    /** terasz */
    const TERASZ = 4;
    /** tetőterasz */
    const TETOTERASZ = 5;
    /** kilátás */
    const KILATAS = 6;
    /** kert */
    const KERT = 7;
    /** kívülről */
    const KIVULROL = 8;
    /** bejárat */
    const BEJARAT = 9;
    /** parkoló */
    const PARKOLO = 10;
    /** környék */
    const KORNYEK = 11;
    /** lépcsőház */
    const LEPCSOHAZ = 12;
    /** tetőtér */
    const TETOTER = 13;
    /** padlás */
    const PADLAS = 14;
    /** pince */
    const PINCE = 15;
    /** konyha */
    const KONYHA = 16;
    /** hall */
    const HALL = 17;
    /** nappali */
    const NAPPALI = 18;
    /** előtér */
    const ELOTER = 19;
    /** folyosó */
    const FOLYOSO = 20;
    /** wc */
    const WC = 21;
    /** fürdőszoba */
    const FURDOSZOBA = 22;
    /** étkező */
    const ETKEZO = 23;
    /** szuterén */
    const SZUTEREN = 24;
    /** hálószoba */
    const HALOSZOBA = 25;
    /** gardrób */
    const GARDROB = 26;
    /** garázs */
    const GARAZS = 27;
    /** bejárat */
    const BEJARAT2 = 28;
    /** kamra */
    const KAMRA = 29;
    /** tároló */
    const TAROLO = 30;
    /** egyéb helyiség */
    const EGYEB_HELYISEG = 31;

    /** @var array $valueCache */
    public static $valueCache = [];

    /**
     * @param int $value
     * @return bool
     * @throws ReflectionException
     */
    public static function validate(int $value): bool
    {
        if (!isset(self::$valueCache) || count(self::$valueCache) <= 0) {
            $class = get_called_class();
            $ref = new ReflectionClass($class);
            self::$valueCache = $ref->getConstants();
        }

        return (!isset($value) || in_array($value, self::$valueCache, true));
    }
}
