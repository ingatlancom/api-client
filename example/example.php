<?php
require_once(__DIR__ . '/../vendor/autoload.php');

/*
 * tesztadatok, ezeket az iroda rendszeréből kell előállítani
 */
require('data.php');

/*
 * létrehozunk egy Stash Pool-t a JWT token tárolására, hogy ne kelljen minden hívás előtt logint hívnunk az API-n
 */
$driver = new Stash\Driver\FileSystem(['path' => '/tmp/ingatlancom/']);
$pool = new Stash\Pool($driver);

/*
 * példányosítjuk a klienst
 */
$apiUrl = 'https://api.ingatlan.com';
$apiClient = new \IngatlanCom\ApiClient\ApiClient($apiUrl, $pool);

/*
 * bejelentkezés - ha megvan a token a pool-ban, akkor nem hív be az API-n.
 */
try {
    $apiClient->login('username', 'password');
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    die();
}

/*
 * törli az ingatlan.com rendszeréből azokat a hirdetéseket, amik az iroda hirdetései között már nem szerepelnek
 */
$adIds = array_keys($testAds);
$apiClient->syncAds($adIds);

/*
 * Összes hirdetés fetöltése/frissítése
 */
foreach ($testAds as $ownId => $ad) {
    try {
        /*
         * hirdetés feltöltése/frissítése
         */
        $icomAd = $apiClient->putAd($ad);

        /*
         * összehasonlíthatjuk, mely értékeket javította ki az API
         */
        $diff = array_diff_assoc($icomAd, $ad);
        echo "$ownId API-tól visszakapott értékek különbségei:\n";
        foreach ($diff as $key => $val) {
            if (isset($ad[$key])) {
                echo str_pad($key, 20, ' ') . "$ad[$key]\t" . var_export($val, true) . "\n";
            }
        }

        /*
         * hirdetés képeinek frissítése, hibák listázása
         */
        $photoSync = $apiClient->syncPhotos($ownId, $testPhotos[$ownId], false, $icomAd['photos'], true);
        $errors = $photoSync->getErrors();
        if (count($errors) > 0) {
            echo "Hibák történtek a képfeltöltés során:\n";
            foreach ($errors as $photoOwnId => $error) {
                echo $photoOwnId . ' : ' . $error['errorMessage'] . "\n";
            }
        } elseif (count($testPhotos[$ownId]) > 0) {
            echo "Fotók rendben feltöltve:\n";
            var_dump($photoSync->getPhotos());
        }
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        print_r($e->getResponse()->getBody()->getContents());
    } catch (\IngatlanCom\ApiClient\Exception\JSendException $e) {
        print_r($e->getJSendResponse());
    } catch (\Exception $e) {
        echo $ownId . ' ' . $e->getMessage() . "\n";
    }
}
