<?php

declare(strict_types=1);

namespace BackendBase\Shared\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMNodeList;
use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;
use function array_keys;
use function explode;
use function html_entity_decode;
use function in_array;
use function strip_tags;
use function strpos;
use function trim;

class TagAndAttributeRemover
{
    private DOMDocument $domHtml;
    private array $allowedTags;
    private array $currentTags;
    private array $allowedUrlPrefixes;
    private static array $urlAttributes = ['href', 'src'];

    private function __construct(DOMDocument $domHtml, array $currentTags, array $allowedTags, array $allowedUrlPrefixes)
    {
        $this->domHtml            = $domHtml;
        $this->currentTags        = $currentTags;
        $this->allowedTags        = $allowedTags;
        $this->allowedUrlPrefixes = $allowedUrlPrefixes;
    }

    public static function cleanHtml(string $html, string $allowedTagsAndAttributesList, ?string $allowedUrlPrefixes = '') : string
    {
        $allowedTagsAndAttributes    = self::extractAllowedTags($allowedTagsAndAttributesList);
        $domHtml                     = new DOMDocument();
        $domHtml->substituteEntities = true;
        $domHtml->formatOutput       = true;
        $html                        = '<?xml encoding="utf-8" ?>' . $html;
        $domHtml->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $domHtml->encoding = 'utf-8';

        $domHtml->normalizeDocument();
        $remover = new self(
            $domHtml,
            self::extractCurrentTags($domHtml),
            $allowedTagsAndAttributes,
            self::safeExplodeString(';', $allowedUrlPrefixes, false)
        );
        $remover->removeAttributes();

        return $remover->removeTags();
    }

    private static function safeExplodeString(string $delimiter, string $string, ?bool $allowEmptyArrayElement = true) : array
    {
        if (strpos($string, $delimiter) !== false) {
            return explode($delimiter, $string);
        }
        if ($string === '') {
            return [];
        }

        return $allowEmptyArrayElement ? [$string, ''] : [$string];
    }

    private static function extractAllowedTags(string $allowedTagsAndAttributes) : array
    {
        $allowedTagList = explode(';', $allowedTagsAndAttributes);

        $allowedTags = [];
        foreach ($allowedTagList as $tagConfig) {
            [$tagName, $attributes] = self::safeExplodeString('|', $tagConfig);
            $allowedTags[$tagName]  = self::safeExplodeString(',', $attributes);
        }

        return $allowedTags;
    }

    private static function extractCurrentTags(DOMDocument $domHtml) : array
    {
        $currentTags = [];
        foreach ($domHtml->getElementsByTagName('*') as $node) {
            $currentTags[$node->tagName] = 1;
        }

        return array_keys($currentTags);
    }

    private function removeAttributes() : void
    {
        foreach ($this->currentTags as $tag) {
            $this->scanNodes($this->domHtml->getElementsByTagName($tag));
        }
    }

    private function scanNodes(DOMNodeList $nodes) : void
    {
        foreach ($nodes as $node) {
            $this->scanNode($node);
        }
    }

    private function scanNode(DOMElement $node) : void
    {
        foreach ($this->getAttributesNames($node->attributes) as $attribute) {
            $this->removeNotAllowedAttributes($node, $attribute, $this->allowedTags[$node->tagName]);
        }
    }

    private function getAttributesNames(DOMNamedNodeMap $attributes) : array
    {
        $attributeNames = [];
        foreach ($attributes as $attribute) {
            $attributeNames[] = $attribute->nodeName;
        }

        return $attributeNames;
    }

    private function removeNotAllowedAttributes(DOMElement $node, string $attributeName, $allowedAttributes) : bool
    {
        $attribute = $node->getAttributeNode($attributeName);
        if (! in_array($attribute->name, $allowedAttributes, true)) {
            return $node->removeAttribute($attributeName);
        }
        if (in_array($attributeName, self::$urlAttributes, true)) {
            return $this->removeAttributesWithNotAllowedUrlPrefixes($node, $attribute);
        }

        return false;
    }

    private function removeAttributesWithNotAllowedUrlPrefixes(DOMElement $node, DOMAttr $attribute) : ?bool
    {
        $isAllowed = 0;
        foreach ($this->allowedUrlPrefixes as $allowedUrlPrefix) {
            if (strpos($attribute->nodeValue, $allowedUrlPrefix) === 0) {
                $isAllowed = 1;
                break;
            }
        }
        if ($isAllowed === 0) {
            return $node->removeAttribute($attribute->nodeName);
        }

        return false;
    }

    private function removeTags() : string
    {
        return trim(strip_tags(html_entity_decode($this->domHtml->saveHTML()), array_keys($this->allowedTags)));
    }
}
