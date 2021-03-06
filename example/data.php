<?php
use IngatlanCom\ApiClient\Enum\PhotoLabelEnum;

$testAd1 = [
    'ownId'                 => 'x149395',
    'listingType'           => 1,
    'propertyType'          => 1,
    'propertySubtype'       => 2,
    'priceHuf'              => 17500000,
    'priceEur'              => 46755,
    'priceType'             => 1,
    'areaSize'              => 27,
    'lotSize'               => 0,
    'city'                  => 'Budapest',
    'street'                => 'Dévai utca',
    'district'              => 13,
    'zone'                  => 'Lőportárdűlő',
    'isStreetHidden'        => 1,
    'isStreetNumberHidden'  => 1,
    'houseNumber'           => '',
    'roomCount'             => 1,
    'smallRoomCount'        => 0,
    'buildingFloorCount'    => 8,
    'floor'                 => 11,
    'heatingType'           => 4,
    'comfortLevel'          => 3,
    'description'           => 'Ingatlan leírása',
    'descriptionEn'         => '',
    'descriptionDe'         => '',
    'elevatorType'          => 1,
    'parkingType'           => 2,
    'atticType'             => 5,
    'viewType'              => 1,
    'parkingPrice'          => 0,
    'conditionType'         => 5,
    'realEstateAgencyOwnId' => 'x149395',
    'balconySize'           => 3,
    'gardenSize'            => 0,
    'agenciesAccepted'      => 0,
    'energyPerformanceCert' => 5,
    'innerHeight'           => 1,
    'accessibility'         => 2,
    'panelprogram'          => 0,
    'gardenAccess'          => 2,
    'orientation'           => 5,
    'bathroomWithoutToilet' => 0,
    'airConditioner'        => 0,
    'isRentRight'           => 0,
    'agentId'               => 12111
];
$testAd2 = [
    'ownId'                 => 'x96602',
    'listingType'           => 1,
    'propertyType'          => 1,
    'propertySubtype'       => 1,
    'priceHuf'              => 20900000,
    'priceEur'              => 69973,
    'priceType'             => 1,
    'areaSize'              => 99,
    'lotSize'               => 0,
    'city'                  => 'Budapest',
    'street'                => 'Thököly út',
    'district'              => 7,
    'zone'                  => 'Külső-Erzsébetváros',
    'isStreetHidden'        => 1,
    'isStreetNumberHidden'  => 1,
    'houseNumber'           => '',
    'roomCount'             => 1,
    'smallRoomCount'        => 3,
    'buildingFloorCount'    => 2,
    'floor'                 => 4,
    'heatingType'           => 2,
    'comfortLevel'          => 3,
    'description'           => 'Ingatlan leírása',
    'descriptionEn'         => '',
    'descriptionDe'         => '',
    'elevatorType'          => 2,
    'viewType'              => 2,
    'parkingPrice'          => 0,
    'conditionType'         => 7,
    'realEstateAgencyOwnId' => 'x96602',
    'balconySize'           => 0,
    'gardenSize'            => 0,
    'agenciesAccepted'      => 2,
    'innerHeight'           => 0,
    'accessibility'         => 2,
    'gardenAccess'          => 0,
    'bathroomWithoutToilet' => 0,
    'airConditioner'        => 0,
    'isRentRight'           => 0,
    'agentId'               => 739
];
$testAds = [
    'x149395' => $testAd1,
    'x96602'  => $testAd2
];

$testPhotos = [
    'x149395' => [
        [
            'ownId'    => 'kep1',
            'order'    => 1,
            'title'    => 'Képfelirat',
            'location' => 'http://lorempixel.com/800/600/city/1/',
            'labelId'  => PhotoLabelEnum::KORNYEK
        ],
        [
            'ownId'    => 'kep2',
            'order'    => 2,
            'title'    => 'Képfelirat',
            'location' => 'http://lorempixel.com/800/600/city/2/',
        ]
    ],
    'x96602'  => []
];
