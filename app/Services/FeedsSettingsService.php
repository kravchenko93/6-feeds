<?php

namespace App\Services;

use App\Dto\Xml\Attribute;
use App\Dto\Xml\ElementXml;
use App\Dto\SystemSettings\Settings;
use App\Dto\SystemSettings\Developer;
use \XMLWriter;

class FeedsSettingsService
{
    private const SYSTEM_LIST_DEVELOPERS = '!developers';
    private const SYS_LIST_DEV_ROW_DEVELOPER_NAME = 'DEVELOPER_NAME';
    private const SYS_LIST_DEV_ROW_SHEET_ID = 'SHEET_ID';

    private const SHEET_URL_TEMPLATE = 'https://docs.google.com/spreadsheets/d/%s/edit';
    private const PLATFORM_RULES_LIST_TEMPLATE = '!%s.rules';
    private const FLATS_SOURCE_LIST_TEMPLATE = '!%s.flats';
    private const COMPLEX_SOURCE_LIST_TEMPLATE = '!%s.complex';
    private const SEPARATOR_EACH_EXPLODE = ';';

    private const PLATFORM_RULES_LIST_PATTERN = '/^!(.+)\.rules$/';
    private const PLATFORM_LIST_PATTERN= '/^!(.+).flats$/';

    public static function getSystemSettings() {
        $warnings = [];
        $systemSheetIds = GoogleSheetsClient::getLists(env('SYSTEM_SPREAD_SHEET_ID'));
        $rulesSheetIds = self::getRulesSheetIds($systemSheetIds);

        if (!in_array(self::SYSTEM_LIST_DEVELOPERS, $systemSheetIds)) {
            throw new \Exception(
                'Ошибка в конфигурационном файле ' . sprintf(self::SHEET_URL_TEMPLATE, env('SYSTEM_SPREAD_SHEET_ID')) . '  Нет листа ' . self::SYSTEM_LIST_DEVELOPERS
            );
        }

        // Fetch the Developers
        $response = GoogleSheetsClient::getService()->spreadsheets_values->get(env('SYSTEM_SPREAD_SHEET_ID'), self::SYSTEM_LIST_DEVELOPERS);
        $rows = $response->getValues();
        // Remove the first one that contains headers
        $headers = array_shift($rows);
        // Combine the headers with each following row
        $developers = [];
        foreach ($rows as $row) {
            $diffCount = count($headers) - count($row);
            if ($diffCount) {
                for ($i = 1; $i <= $diffCount; $i++) {
                    $row[] = null;
                }
            }
            $developerData = array_combine($headers, $row);
            $allowedFeeds = [];
            $nameDeveloper = !empty($developerData[self::SYS_LIST_DEV_ROW_DEVELOPER_NAME]) ? strtolower($developerData[self::SYS_LIST_DEV_ROW_DEVELOPER_NAME]) : null;
            $sheetId = !empty($developerData[self::SYS_LIST_DEV_ROW_SHEET_ID]) ? $developerData[self::SYS_LIST_DEV_ROW_SHEET_ID] : null;

            if (null === $nameDeveloper) {
                $warnings[] = 'В конфигурационном файле в листе ' . self::SYSTEM_LIST_DEVELOPERS . ' в строке пусто ' . self::SYS_LIST_DEV_ROW_DEVELOPER_NAME;
            } else {
                if (null !== $sheetId) {
                    try {
                        $developerSheetIds = GoogleSheetsClient::getLists($sheetId);
                        $allowedFeeds = self::getAllowedFeeds($rulesSheetIds, $developerSheetIds);
                    } catch (\Exception $e) {
                        $warnings[] = 'В конфигурационном файле в листе ' . self::SYSTEM_LIST_DEVELOPERS . ' для ' . $nameDeveloper . ' не удалось открыть файл ' . $sheetId;
                    }
                } else {
                    $warnings[] = 'В конфигурационном файле в листе ' . self::SYSTEM_LIST_DEVELOPERS . ' в строке для ' . $nameDeveloper . ' пусто ' . self::SYS_LIST_DEV_ROW_SHEET_ID;
                }
            }

            $developers[] = new Developer(
                $nameDeveloper,
                $sheetId,
                $allowedFeeds,
                $warnings
            );
        }

        return new Settings(
            $developers,
            $rulesSheetIds
        );
    }

    /**
     * @param string[] $rulesSheetIds
     * @param string[] $developerSheetIds
     *
     * @return string[]
     */
    private static function getAllowedFeeds(array $rulesSheetIds, array $developerSheetIds): array {
        $allowedFeeds = [];
        foreach ($developerSheetIds as $sheetId) {
            if (
                1 === preg_match(self::PLATFORM_LIST_PATTERN, $sheetId, $matches) &&
                in_array($matches[1], $rulesSheetIds)
            ) {
                $allowedFeeds[] = $matches[1];
            }
        }
        return $allowedFeeds;
    }

    /**
     * @param string[] $sheetIds
     *
     * @return string[]
     */
    private static function getRulesSheetIds(array $sheetIds): array {
        $rulesSheetIds = [];
        $sheetIds = GoogleSheetsClient::getLists(env('SYSTEM_SPREAD_SHEET_ID'));
        foreach ($sheetIds as $sheetId) {
            if (
                1 === preg_match(self::PLATFORM_RULES_LIST_PATTERN, $sheetId, $platformRulesMatches)
            ) {
                $rulesSheetIds[] = $platformRulesMatches[1];
            }
        }
        return $rulesSheetIds;
    }

    public static function check(string $source, string $platform) {
        $source = strtolower($source);
        $platform = strtolower($platform);
        $systemSettings = self::getSystemSettings();

        foreach ($systemSettings->developers as $developer) {
            if ($developer->name === $source && in_array($platform, $developer->allowedFeeds)) {
                return ;
            }
        }
        throw new \Exception(
            'фид не найден'
        );
    }

    public static function get(string $source, string $platform)
    {
        $source = strtolower($source);
        $platform = strtolower($platform);

        $systemSettings = self::getSystemSettings();
        foreach ($systemSettings->developers as $developer) {
            if ($developer->name === $source && in_array($platform, $developer->allowedFeeds)) {
                break;
            }
        }

        $platformRuleListName = sprintf(self::PLATFORM_RULES_LIST_TEMPLATE, strtolower($platform));
        $flatsSourceListName = sprintf(self::FLATS_SOURCE_LIST_TEMPLATE, strtolower($platform));
        $complexSourceListName = sprintf(self::COMPLEX_SOURCE_LIST_TEMPLATE, strtolower($platform));

        $complex = [];
        $sourceFileLists = GoogleSheetsClient::getLists($developer->sheetId);

        if (in_array($complexSourceListName, $sourceFileLists)) {
            // Fetch the flats
            $response = GoogleSheetsClient::getService()->spreadsheets_values->get($developer->sheetId, $complexSourceListName);
            $rows = $response->getValues();

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!empty($row[0]) && !empty($row[1])) {
                        $complex[$row[0]] = $row[1];
                    }
                }
            }

        }

        // Fetch the rules
        $response = GoogleSheetsClient::getService()->spreadsheets_values->get(env('SYSTEM_SPREAD_SHEET_ID'), $platformRuleListName);

        $ruleXmlObj = GenerationXmlRulesService::generate($response->getValues());


        // Fetch the flats
        $response = GoogleSheetsClient::getService()->spreadsheets_values->get($developer->sheetId, $flatsSourceListName);
        $rows = $response->getValues();
        // Remove the first one that contains headers
        $headers = array_shift($rows);
        // Combine the headers with each following row
        $flats = [];
        foreach ($rows as $row) {
            $diffCount = count($headers) - count($row);
            if ($diffCount) {
                for ($i = 1; $i <= $diffCount; $i++) {
                    $row[] = null;
                }
            }
            $flats[] = array_combine($headers, $row);
        }

        return self::generateXml($flats, $ruleXmlObj, $complex)->outputMemory();
    }

    private static function generateXml(array $flats, ElementXml $ruleXmlObj, array $complex): XMLWriter {
        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->startDocument("1.0");
        self::writeElementXml($xw, $ruleXmlObj, $flats, null, $complex);
        $xw->endDocument();

        return $xw;
    }

    private static function writeElementXml(XMLWriter &$xw, ElementXml $ruleXmlObj, array $flats, ?array $flat, array $complex, ?string $eachValuePath = null): void {
        if ($ruleXmlObj->isEachFlats) {
            foreach ($flats as $flat) {
                self::writeOneElementXml($xw, $ruleXmlObj, $flats, $flat, $complex);
            }
        }elseif ($ruleXmlObj->isEachValue) {
            $indexes = self::getIndexesForEachValue($ruleXmlObj, $flat, $complex);
            foreach ($indexes as $index) {
                self::writeOneElementXml($xw, $ruleXmlObj, $flats, $flat, $complex, $ruleXmlObj->name . '.' . $index);
            }
        }  else {
            self::writeOneElementXml($xw, $ruleXmlObj, $flats, $flat, $complex, $eachValuePath);
        }
    }

    /**
     * @return int[]
     */
    private static function getIndexesForEachValue(ElementXml $ruleXmlObj, ?array $flat, array $complex, array &$indexes = [], ?string $rootPath = null): array
    {
        switch ($ruleXmlObj->type) {
            case ElementXml::TYPE_CELL_VALUE:
                if (null !== $flat) {
                    foreach ($flat as $key => $value) {
                        if (1 === preg_match('/^' . ($rootPath ?? $ruleXmlObj->name) . '.(\d+)' . '.' . $ruleXmlObj->value . '$/', $key, $matches) && !empty($value)) {
                            $indexes[] = $matches[1];
                        }
                    }
                }
                if (empty($indexes)) {
                    foreach ($complex as $key => $value) {
                        if (1 === preg_match('/^' . ($rootPath ?? $ruleXmlObj->name) . '.(\d+)' . '.' . $ruleXmlObj->value . '$/', $key, $matches) && !empty($value)) {
                            $indexes[] = $matches[1];
                        }
                    }
                }
                break;
            case ElementXml::TYPE_CHILDREN:
                if (null !== $rootPath) {
                    throw new \Exception('Invalid logic type ElementXml', (array) $ruleXmlObj);
                }
                foreach ($ruleXmlObj->children as $child) {
                    self::getIndexesForEachValue($child, $flat, $complex, $indexes, $ruleXmlObj->name);
                }
                break;

        }

        return array_unique($indexes);
    }

    private static function writeOneElementXml(XMLWriter &$xw, ElementXml $ruleXmlObj, array $flats, ?array $flat, array $complex, ?string $eachValuePath = null): void
    {
        switch ($ruleXmlObj->type) {
            case ElementXml::TYPE_CELL_VALUE:
                if (empty($ruleXmlObj->value)) {
                    throw new \Exception('Invalid logic type ElementXml', (array) $ruleXmlObj);
                }
                if ($eachValuePath) {
                    $value = ($flat[$eachValuePath . '.' . $ruleXmlObj->value] ?? null) ?? ($complex[$eachValuePath . '.' . $ruleXmlObj->value] ?? null);
                } else {
                    $value = ($flat[$ruleXmlObj->value] ?? null) ?? ($complex[$ruleXmlObj->value] ?? null);
                }
                // если тип тега - ElementXml::TYPE_CELL_VALUE и пустое значение в ячейке - пропускаем тег
                if (empty($value)) {
                    return;
                }
                $xw->startElement($ruleXmlObj->name);
                self::writeAttrs($xw, $ruleXmlObj->attributions, $flat, $complex, $eachValuePath);
                if ($eachValuePath) {
                    $value = ($flat[$eachValuePath . '.' . $ruleXmlObj->value] ?? $complex[$eachValuePath . '.' . $ruleXmlObj->value]) ?? '';
                } else {
                    $value = ($flat[$ruleXmlObj->value] ?? $complex[$ruleXmlObj->value]) ?? '';
                }
                $xw->text($value);
                $xw->endElement();
                break;
            case ElementXml::TYPE_EACH_EXPLODE:
                // если тип тега - ElementXml::TYPE_EACH_EXPLODE и пустое значение в ячейке - пропускаем тег
                if (empty($flat[$ruleXmlObj->value] ?? $complex[$ruleXmlObj->value]) ) {
                    return;
                }
                foreach (explode(self::SEPARATOR_EACH_EXPLODE, $flat[$ruleXmlObj->value] ?? $complex[$ruleXmlObj->value]) as $value) {
                    if ($value) {
                        $xw->startElement($ruleXmlObj->name);
                        $xw->text($value);
                        self::writeAttrs($xw, $ruleXmlObj->attributions, $flat, $complex);
                        $xw->endElement();
                    }
                }
                break;
            case ElementXml::TYPE_REGEX:
                $value = ($flat[$ruleXmlObj->value] ?? null) ?? ($complex[$ruleXmlObj->value] ?? null);
                if (empty($value)) {
                    return;
                }
                $xw->startElement($ruleXmlObj->name);
                self::writeAttrs($xw, $ruleXmlObj->attributions, $flat, $complex, $eachValuePath);
                if (false !== preg_match_all('/\[(\w+)\]/', $value, $matches)) {
                    foreach ($matches[1] as $key => $match) {
                        $replace = ($flat[$match] ?? null) ?? ($complex[$match] ?? null);
                        if (null !== $replace) {
                            $value = str_replace('[' . $match . ']', $replace, $value);
                        }
                    }
                }
                $xw->text($value);
                $xw->endElement();
                break;
            case ElementXml::TYPE_STRING:
                $xw->startElement($ruleXmlObj->name);
                self::writeAttrs($xw, $ruleXmlObj->attributions, $flat, $complex, $eachValuePath);
                $xw->text($ruleXmlObj->value);
                $xw->endElement();
                break;
            case ElementXml::TYPE_CHILDREN:
                // если тип тега - ElementXml::TYPE_CHILDREN и нет вложенных тегов - пропускаем
                if (empty($ruleXmlObj->children)) {
                    return;
                }
                // если тип тега - ElementXml::TYPE_CHILDREN и все вложенные теги пустые - пропускаем
                if (false === self::checkInnerTagsIsNotEmpty($ruleXmlObj->children, $flat, $complex, $eachValuePath)) {
                    return;
                }
                $xw->startElement($ruleXmlObj->name);
                self::writeAttrs($xw, $ruleXmlObj->attributions, $flat, $complex, $eachValuePath);
                foreach ($ruleXmlObj->children as $child) {
                    self::writeElementXml($xw, $child, $flats, $flat, $complex, $eachValuePath);
                }

                $xw->endElement();
                break;
            default:
                throw new \Exception('Invalid logic type ElementXml', (array) $ruleXmlObj);
        }
    }

    /**
     * @param Attribute[] $attributions
     */
    private static function writeAttrs(XMLWriter &$xw, array $attributions, ?array $flat, array $complex, ?string $eachValuePath = null): void
    {
        foreach ($attributions as $attribute) {
            switch ($attribute->type) {
                case Attribute::TYPE_CELL_VALUE;
                    if ($eachValuePath) {
                        $value = ($flat[$eachValuePath . '.' . $attribute->value] ?? '') ?? ($complex[$eachValuePath . '.' .$attribute->value] ?? '');
                    } else {
                        $value = ($flat[$attribute->value] ?? '') ?? ($complex[$attribute->value] ?? '');
                    }
                    if (!empty($value)) {
                        $xw->writeAttribute($attribute->name, $value);
                    }
                    break;
                case Attribute::TYPE_STRING;
                    if (!empty($attribute->value)) {
                        $xw->writeAttribute($attribute->name, $attribute->value);
                    }
                    break;
                default:
                    throw new \Exception('Invalid logic type ElementXml');
            }
        }
    }

    /**
     * @param ElementXml[] $ruleXmlObjects
     */
    private static function checkInnerTagsIsNotEmpty(array $ruleXmlObjects, ?array $flat, array $complex, ?string $eachValuePath): bool
    {
        foreach ($ruleXmlObjects as $ruleXmlObject) {
            switch ($ruleXmlObject->type) {
                case ElementXml::TYPE_CELL_VALUE:
                    if ($eachValuePath) {
                        if (
                            (null !== $flat && !empty($flat[$eachValuePath . '.' .$ruleXmlObject->value])) ||
                            !empty($complex[$eachValuePath . '.' .$ruleXmlObject->value])
                        ) {
                            return true;
                        }
                    } else {
                        if (
                            (null !== $flat && !empty($flat[$ruleXmlObject->value])) ||
                            !empty($complex[$ruleXmlObject->value])
                        ) {
                            return true;
                        }
                    }

                    break;
                case ElementXml::TYPE_STRING:
                case ElementXml::TYPE_REGEX:
                    if (!empty($ruleXmlObject->value)) {
                        return true;
                    }
                    break;
                case ElementXml::TYPE_CHILDREN:
                    if (true === self::checkInnerTagsIsNotEmpty($ruleXmlObject->children, $flat, $complex, $eachValuePath)) {
                        return true;
                    }
                    break;
            }
        }

        return false;
    }
}
