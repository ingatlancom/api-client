# Change Log
Az ingatlancom/api-client csomag minden változását ebben a leírásban kell rögzíteni.

Az ingatlancom/api-client csomag a [Szemantikus verziózás](http://semver.org/) irányelveit követi.

A Changelog a [Keep a Changelog](http://keepachangelog.com) formátumában íródik.

## [2.2.0] - 2017-04-13

### Bekerült
- verzió küldése headerben

### Javítva
- képek letöltésének javítása

## [2.1.0] - 2017-04-06

### Bekerült
- a JWT tokent a lejárati idejéig cacheeljük
- token lekérése mindig a cache-ből, illetve ha ott már lejárt, akkor automatikusan új token kérése a szervertől autentikációval

### Javítva
- Stash\Pool cache használatának javítása
