## Migráció 3-as verzióra

A PhotoSync osztály megszüntetésre került. Funkcióját az ApiClient osztály vette át. Ha korábban példányosított PhotoSyncet és hívta rajta a syncPhotos() metódust, akkor mostantól az ApiClient osztállyal tegye meg ugyanezt.

Az ApiClient::syncPhotos() metódus a 3-as verziótól kezdve PhotoSyncResult objektumot ad vissza, amelyen ugyanúgy használhatók a lekérdezések, mint a PhotoSync objektumon korábban. 

## Migráció 2-es verzióra

**FONTOS:** A függőségek frissítésével az api-client szükséges PHP verziója 5.3-ről **5.5**-re emelkedett.

Ezenkívül a két alábbi változást le kell követniük a partnereknek:

1. A Stash driver példányosítása a következő módra változott:
```php
$driver = new Stash\Driver\FileSystem(['path' => '/tmp/ingatlancom/']);
```

1. Küldési hibát elkapni a következő módon lehet:
```php
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        print_r($e->getResponse()->getBody()->getContents());
```

