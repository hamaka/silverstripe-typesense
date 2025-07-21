<?php

    namespace Hamaka\Typesense\Service;

    use Masterminds\HTML5;
    use SilverStripe\Assets\Shortcodes\TypesenseImageShortcodeProvider;
    use SilverStripe\CMS\Model\SiteTree;
    use SilverStripe\Control\Director;
    use SilverStripe\Core\Config\Configurable;
    use SilverStripe\Core\Injector\Injectable;
    use SilverStripe\ORM\DataObject;
    use SilverStripe\Security\Member;
    use SilverStripe\View\Parsers\ShortcodeParser;

    /**
     * Service class for Typesense integration
     * Provides methods to convert Silverstripe DataObjects to Typesense documents
     */
    class TypesenseService
    {
        use Configurable;
        use Injectable;

        /**
         * Whether the service is enabled
         *
         * @config
         * @var bool
         */
        private static $enabled = true;
        private static $target_dom_class = '';
        private static $strip_dom_tags = [
            'style',
            'script',
            'img',
            'svg',
            'noscript',
            'iframe',
            'object',
            'embed',
            'video',
            'audio',
            'canvas',
            'picture',
            'source',
            'link',
            'meta',
            'head',
            'template',
        ];
        private static $strip_dom_classes = [];

        /**
         * Render the page as a non-member in live mode and process the DOM
         *
         * @param DataObject $object The object to render
         *
         * @return string|false Processed page content or false if rendering failed
         */
        public function renderPageAsNonMember(SiteTree $object)
        {
            return Member::actAs(null, function () use ($object) {

                $shortcodeParser = ShortcodeParser::get_active();

                //Disable image shortcodes
                $shortcodeParser->unregister('image');
                ShortcodeParser::get('default')->register('image', [TypesenseImageShortcodeProvider::class, 'handle_shortcode']);

                try {
                    // Test the page in li  ve mode

                    $response = Director::test(Director::makeRelative($object->getAbsoluteLiveLink()));
                    if ($response->getStatusCode() !== 200) {
                        return false;
                    }

                    // Process the response as before
                    $html = $response->getBody();

                    // Load the HTML into a DOM document
                    $dom = new HTML5();
                    $dom = $dom->loadHTML($html, [LIBXML_HTML_NOIMPLIED, LIBXML_HTML_NODEFDTD]);

                    $oTypesenseConfig = $this->config();

                    $this->removeUnwantedElements($dom, $oTypesenseConfig->get('strip_dom_tags'));

                    $this->removeUnwantedElementsByClasses($dom, $oTypesenseConfig->get('strip_dom_classes'));

                    return $this->extractContentFromDom($dom, $oTypesenseConfig->get('target_dom_class'));
                } finally {

                }
            });
        }

        /**
         * Remove unwanted elements from the DOM
         *
         * @param \DOMDocument $dom The DOM document to process
         */
        public function removeUnwantedElements(\DOMDocument $dom, $aElements = [])
        {
            $xpath = new \DOMXPath($dom);

            foreach ($aElements as $Element) {
                $styleElements = $xpath->query("//" . $Element);
                foreach ($styleElements as $style) {
                    $style->parentNode->removeChild($style);
                }
            }
        }

        /**
         * Remove unwanted elements from the DOM by class
         *
         * @param \DOMDocument $dom The DOM document to process
         */
        public function removeUnwantedElementsByClasses(\DOMDocument $dom, $aClasses = [])
        {
            $xpath = new \DOMXPath($dom);

            foreach ($aClasses as $aClass) {
                $elementsToRemove = $xpath->query("//*[contains(@class, '" . $aClass . "')]");
                foreach ($elementsToRemove as $element) {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        /**
         * Extract content from the processed DOM
         *
         * @param \DOMDocument $dom The processed DOM document
         *
         * @return string Extracted content
         */
        public function extractContentFromDom(\DOMDocument $dom, $sTargetClass = 'typesense-extract')
        {
            $xpath    = new \DOMXPath($dom);
            $elements = $xpath->query("//*[contains(@class, '" . $sTargetClass . "')]");

            if ($elements->length > 0) {
                $content = preg_replace('/[\r\n\t\f\v ]+/', ' ', $elements->item(0)->textContent);
            }
            else {
                $content = preg_replace('/[\r\n\t\f\v ]+/', ' ', $dom->textContent);
            }

            return html_entity_decode(
                strip_tags($content),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        /**
         * /**
         * Initialize the Typesense data array with basic fields
         *
         * @param DataObject $object The object to initialize data for
         *
         * @return array Basic Typesense data
         */
        public function initializeTypesenseData(DataObject $object)
        {
            $aData               = [];
            $aData['id']         = (string)$object->ID;
            $aData['ClassName']  = $object->ClassName;
            $aData['Title']      = $this->convertHtmlToPlainText($object->Title);
            $aData['LastEdited'] = strtotime($object->LastEdited);
            $aData['Created']    = strtotime($object->Created);

            return $aData;
        }

        /**
         * Add fields with HTML converted to accessible text to the data array
         *
         * @param array      $aData  The data array to add fields to
         * @param DataObject $object The object to get field values from
         * @param array      $fields The fields to add
         *
         * @return array Updated data array
         */
        public function addStrippedHtmlFields($aData, DataObject $object, array $fields = ['Summary', 'Content'])
        {
            foreach ($fields as $field) {
                if ($object->hasField($field) && $object->$field) {
                    $aData[$field] = $this->convertHtmlToPlainText($object->$field);
                }
            }

            return $aData;
        }

        /**
         * Convert HTML to accessible text format
         *
         * @param string $sHtml The HTML content to convert
         *
         * @return string Accessible text representation
         */
        public function convertHtmlToPlainText($sHtml)
        {
            $dom = new HTML5();
            $dom = $dom->loadHTML(mb_convert_encoding($sHtml, 'HTML-ENTITIES', 'UTF-8'),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);

            $text = '';

            // Process different HTML elements
            $text .= $this->processImages($xpath);
            $text .= $this->processHeadings($xpath);
            $text .= $this->processLists($xpath);
            $text .= $this->processExternalLinks($xpath);
            $text .= $this->processTables($xpath);
            $text .= $this->processFormElements($xpath);
            $text .= $this->processIframes($xpath);
            $text .= $this->processAriaElements($xpath);
            $text .= $this->processTextNodes($xpath);

            // Clean up the output
            return $this->cleanupText($text);
        }

        /**
         * Process images with alt text or aria-label
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processImages(\DOMXPath $xpath)
        {
            $text   = '';
            $images = $xpath->query('//img');
            foreach ($images as $img) {
                $description = $img->getAttribute('alt') ?: $img->getAttribute('aria-label');
                if ($description) {
                    $text .= "[Afbeelding: $description]\n";
                }
            }

            return $text;
        }

        /**
         * Process headings with their hierarchy and aria-labels
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processHeadings(\DOMXPath $xpath)
        {
            $text = '';
            for ($i = 1; $i <= 6; $i++) {
                $headings = $xpath->query("//h$i");
                foreach ($headings as $heading) {
                    $headingText = $heading->getAttribute('aria-label') ?: $heading->textContent;
                    $text        .= "\n" . str_repeat('#', $i) . ' ' . trim($headingText) . "\n";
                }
            }

            return $text;
        }

        /**
         * Process lists with their context
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processLists(\DOMXPath $xpath)
        {
            $text  = '';
            $lists = $xpath->query('//ul | //ol');
            foreach ($lists as $list) {
                $listType = $list->tagName === 'ol' ? 1 : '•';
                $items    = $xpath->query('.//li', $list);
                foreach ($items as $index => $item) {
                    $marker = $listType === '•' ? $listType : ($index + 1) . '.';
                    $text   .= "$marker " . trim($item->textContent) . "\n";
                }
                $text .= "\n";
            }

            return $text;
        }

        /**
         * Process external links (with target="_blank")
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processExternalLinks(\DOMXPath $xpath)
        {
            $text  = '';
            $links = $xpath->query('//a[@target="_blank"]');
            foreach ($links as $link) {
                $href     = $link->getAttribute('href');
                $linkText = $link->getAttribute('aria-label') ?: $link->textContent;
                $text     .= trim($linkText) . " [externe link: $href]\n";
            }

            return $text;
        }

        /**
         * Process tables with captions, headers and data
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processTables(\DOMXPath $xpath)
        {
            $text   = '';
            $tables = $xpath->query('//table');
            foreach ($tables as $table) {
                // Table caption or summary
                $caption = $xpath->query('.//caption', $table)->item(0);
                if ($caption) {
                    $text .= "\n[Tabel: " . trim($caption->textContent) . "]\n";
                }
                elseif ($table->getAttribute('summary')) {
                    $text .= "\n[Tabel: " . $table->getAttribute('summary') . "]\n";
                }

                // Table headers
                $headers     = $xpath->query('.//th', $table);
                $headerTexts = [];
                foreach ($headers as $header) {
                    $headerTexts[] = trim($header->textContent);
                }
                if ( ! empty($headerTexts)) {
                    $text .= implode(" | ", $headerTexts) . "\n";
                    $text .= str_repeat("-", strlen(implode(" | ", $headerTexts))) . "\n";
                }

                // Table data
                $rows = $xpath->query('.//tr', $table);
                foreach ($rows as $row) {
                    $cells   = $xpath->query('.//td', $row);
                    $rowData = [];
                    foreach ($cells as $cell) {
                        $rowData[] = trim($cell->textContent);
                    }
                    if ( ! empty($rowData)) {
                        $text .= implode(" | ", $rowData) . "\n";
                    }
                }
                $text .= "\n";
            }

            return $text;
        }

        /**
         * Process form elements with labels
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processFormElements(\DOMXPath $xpath)
        {
            $text         = '';
            $formElements = $xpath->query('//input[@type!="hidden"] | //select | //textarea');
            foreach ($formElements as $element) {
                $label = '';

                // Find label via for attribute
                $id = $element->getAttribute('id');
                if ($id) {
                    $labelNode = $xpath->query("//label[@for='$id']")->item(0);
                    if ($labelNode) {
                        $label = trim($labelNode->textContent);
                    }
                }

                // Use aria-label as alternative
                if ( ! $label) {
                    $label = $element->getAttribute('aria-label') ?:
                        $element->getAttribute('placeholder') ?:
                            $element->getAttribute('name');
                }

                if ($label) {
                    $type = $element->getAttribute('type') ?: $element->tagName;
                    $text .= "[Formulierveld $type: $label]\n";
                }
            }

            return $text;
        }

        /**
         * Process iframes with descriptions
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processIframes(\DOMXPath $xpath)
        {
            $text    = '';
            $iframes = $xpath->query('//iframe');
            foreach ($iframes as $iframe) {
                $description = $iframe->getAttribute('aria-label') ?:
                    $iframe->getAttribute('title');
                if ($description) {
                    $text .= "[Ingesloten content: $description]\n";
                }
            }

            return $text;
        }

        /**
         * Process elements with aria-labels
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processAriaElements(\DOMXPath $xpath)
        {
            $text         = '';
            $ariaElements = $xpath->query('//*[@aria-label]');
            foreach ($ariaElements as $element) {
                if ($element->tagName !== 'img' && $element->tagName !== 'a') { // These are already processed
                    $label = $element->getAttribute('aria-label');
                    if ($label) {
                        $text .= "[" . $element->tagName . ": $label]\n";
                    }
                }
            }

            return $text;
        }

        /**
         * Process regular text nodes
         *
         * @param \DOMXPath $xpath The XPath object
         *
         * @return string Processed text
         */
        public function processTextNodes(\DOMXPath $xpath)
        {
            $text      = '';
            $textNodes = $xpath->query('//p | //div[not(.//p)]');
            foreach ($textNodes as $node) {
                $content = trim($node->textContent);
                if ($content) {
                    $text .= $content . "\n\n";
                }
            }

            return $text;
        }

        /**
         * Clean up the text output
         *
         * @param string $text The text to clean up
         *
         * @return string Cleaned text
         */
        public function cleanupText($text)
        {
            // Remove excessive empty lines
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            // Normalize whitespace
            $text = preg_replace('/[ \t]+/', ' ', $text);

            return trim($text);
        }
    }
