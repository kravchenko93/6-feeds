<?php

namespace App\Services;

use App\Dto\Xml\ElementXml;
use App\Dto\Xml\Attribute;

class GenerationXmlRulesService
{
    private const OP_CONST = '!!CONST::';
    private const OP_VALUE = '!!VALUE::';
    private const OP_DATE_NOW = '!!DATE_NOW::';
    private const OP_EACH_FLATS = '!!EACH_FLATS';
    private const OP_EACH_VALUE = '!!EACH_VALUE';
    private const OP_EACH_EXPLODE = '!!EACH_EXPLODE::';
    private const OP_VALUE_REGEX = '!!VALUE_REGEX::';

    public static function generate($arrayRules): ElementXml {
        $tags = [];
        foreach (array_reverse($arrayRules) as $row) {
            $tagNameIndex = null;
            foreach ($row as $index => $value) {
                if ($value) {
                    $tagNameIndex = $index;
                    break;
                }
            }
            $tagName = $row[$tagNameIndex];

            if (self::OP_EACH_FLATS === $tagName) {
                if (1 === count($tags) && $tags[$tagNameIndex] instanceof ElementXml) {
                    $tags[$tagNameIndex]->isEachFlats = true;
                    continue;
                }
                throw new \Exception('incorrect rules: ' . self::OP_EACH_FLATS);
            } elseif (self::OP_EACH_VALUE === $tagName) {
                if ($tags[$tagNameIndex] instanceof ElementXml) {
                    $tags[$tagNameIndex]->isEachValue = true;
                    continue;
                } elseif (end($tags[$tagNameIndex]) instanceof ElementXml) {
                    end($tags[$tagNameIndex])->isEachValue = true;
                    continue;
                }

                throw new \Exception('incorrect rules: ' . self::OP_EACH_VALUE);
            }
            $tagValue = $row[$tagNameIndex + 1] ?? null;

            $children = [];

            if ($tagValue) {
                if (self::OP_CONST=== substr($tagValue, 0, 9)) {
                    $tagType = ElementXml::TYPE_STRING;
                    $tagValue = substr($tagValue, 9);
                } elseif (self::OP_DATE_NOW=== substr($tagValue, 0, 12)) {
                    $tagType = ElementXml::TYPE_STRING;
                    $tagValue = date(substr($tagValue, 12));
                } elseif (self::OP_EACH_EXPLODE=== substr($tagValue, 0, 16)) {
                    $tagType = ElementXml::TYPE_EACH_EXPLODE;
                    $tagValue = substr($tagValue, 16);
                } elseif (self::OP_VALUE === substr($tagValue, 0, 9)) {
                    $tagType = ElementXml::TYPE_CELL_VALUE;
                    $tagValue = substr($tagValue, 9);
                } elseif (self::OP_VALUE_REGEX === substr($tagValue, 0, 15)) {
                    $tagType = ElementXml::TYPE_REGEX;
                    $tagValue = substr($tagValue, 15);
                } else {
                    throw new \Exception('incorrect rules: ' . $tagValue);
                }


            } else {
                $tagType = ElementXml::TYPE_CHILDREN;
                if (isset($tags[$tagNameIndex + 1])) {
                    if (is_array($tags[$tagNameIndex + 1])) {
                        $children = $tags[$tagNameIndex + 1];
                    } else {
                        $children = [$tags[$tagNameIndex + 1]];
                    }
                    unset($tags[$tagNameIndex + 1]);
                }
            }

            $attrName = $row[$tagNameIndex + 2] ?? null;
            $attrVal = $row[$tagNameIndex + 3] ?? null;
            $attr = null;
            if ($attrName && $attrVal) {
                if ( self::OP_CONST=== substr($attrVal, 0, 9)) {
                    $attrType = Attribute::TYPE_STRING;
                    $attrVal = substr($attrVal, 9);
                } elseif (self::OP_VALUE=== substr($attrVal, 0, 9)) {
                    $attrType = Attribute::TYPE_CELL_VALUE;
                    $attrVal = substr($attrVal, 9);
                } else {
                    throw new \Exception('incorrect rules: ' . $attrVal);
                }
                $attr = new Attribute($attrType, $attrName, $attrVal);
            }

            $tag = new ElementXml(
                $tagName,
                $tagType,
                $tagValue,
                array_reverse($children),
                isset($attr) ? [$attr] : []
            );
            if (isset($tags[$tagNameIndex])) {
                if (!is_array($tags[$tagNameIndex])) {
                    $tags[$tagNameIndex] = [$tags[$tagNameIndex]];
                }
                $tags[$tagNameIndex][] = $tag;
            } else {
                $tags[$tagNameIndex] = $tag;
            }
        }
        if (1 !== count($tags)) {
            throw new \Exception('incorrect rules ');
        }
        return $tags[0];
    }

}
