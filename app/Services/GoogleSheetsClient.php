<?php

namespace App\Services;

class GoogleSheetsClient
{
    private static $instances = [];

    private $service;

    protected function __construct()
    {
        $client = new \Google_Client();
        $client->setApplicationName('6feedss');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');
        // credentials.json is the key file we downloaded while setting up our Google Sheets API
        $path = base_path('credentials.json');
        $client->setAuthConfig($path);

        // configure the Sheets Service
        $this->service = new \Google_Service_Sheets($client);
    }

    public static function getInstance(): GoogleSheetsClient
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static();
        }

        return self::$instances[$cls];
    }

    public static function getService()
    {
        return self::getInstance()->service;
    }

    /**
     * @return string[]
     */
    public static function getLists($spreadsheetId)
    {
        $sheetIds = [];
        $spreadSheet = self::getInstance()->service->spreadsheets->get($spreadsheetId);
        $sheets = $spreadSheet->getSheets();

        foreach($sheets as $sheet) {
            $sheetIds[] = strtolower($sheet->properties->title);
        }

        return $sheetIds;
    }


}
