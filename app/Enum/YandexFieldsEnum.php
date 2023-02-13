<?php

namespace App\Enum;

class YandexFieldsEnum
{
    public const INTERNAL_ID = 'internal-id';
    public const TYPE = 'type';
    public const CATEGORY = 'category';
    public const PROPERTY_TYPE = 'property-type';
    public const COMMERCIAL_TYPE = 'commercial-type';
    public const URL = 'url';
    public const VAS = 'vas';
    public const LOCATION_COUNTRY = 'location_country';
    public const LOCALITY_NAME = 'locality-name';
    public const SUB_LOCALITY_NAME = 'sub-locality-name';
    public const APARTMENT = 'apartment';
    public const ADDRESS = 'address';
    public const METRO = 'metro';
    public const METRO_TIME_ON_TRANSPORT = 'metro-time-on-transport';
    public const DIRECTION = 'direction';
    public const DISTANCE = 'distance';
    public const DEAL_STATUS = 'deal-status';
    public const ROOM_FURNITURE = 'room-furniture';

    const REQUIRED_FIELDS = [
        self::INTERNAL_ID,
        self::TYPE,
        self::CATEGORY,
        self::LOCATION_COUNTRY,
        self::LOCALITY_NAME,
        self::ADDRESS,
    ];

    const REQUIRED_FIELDS_FOR_COMMERCIAL = [
        self::COMMERCIAL_TYPE,
    ];

    const REQUIRED_FIELDS_FOR_LIVING = [
        self::PROPERTY_TYPE,
        self::DEAL_STATUS,
    ];
}
