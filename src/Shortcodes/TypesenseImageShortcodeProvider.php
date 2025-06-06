<?php

namespace SilverStripe\Assets\Shortcodes;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\View\Parsers\ShortcodeHandler;
use SilverStripe\View\Parsers\ShortcodeParser;

/**
 * Class ImageShortcodeProvider
 *
 * @package SilverStripe\Forms\HtmlEditor
 */
class TypesenseImageShortcodeProvider extends ImageShortcodeProvider
{
    /**
     * A whitelist of attributes which are allowed in the resultant markup.
     *
     * @config
     */
    private static array $attribute_whitelist = [
        'alt',
        'class',
        'height',
        'loading',
        'src',
        'title',
        'width',
    ];

    /**
     * Override handle_shortcode to only return alt and title text without converting/decoding the image
     *
     * @param array $args Arguments passed to the parser
     * @param string $content Raw shortcode
     * @param ShortcodeParser $parser Parser
     * @param string $shortcode Name of shortcode used to register this handler
     * @param array $extra Extra arguments
     * @return string Result of the handled shortcode
     */
    public static function handle_shortcode($args, $content, $parser, $shortcode, $extra = [])
    {
        // Find appropriate record, with fallback for error handlers
        $record = static::find_shortcode_record($args, $errorCode);
        if ($errorCode) {
            $record = static::find_error_record($errorCode);
        }
        if (!$record) {
            return null; // There were no suitable matches at all.
        }

        // Extract only alt and title attributes
        $alt = isset($args['alt']) ? $args['alt'] : '';
        $title = isset($args['title']) ? $args['title'] : '';

        // If no alt is provided but title exists, use title as alt
        if (empty($alt) && !empty($title)) {
            $alt = $title;
        }

        // If no title is provided but alt exists, use alt as title
        if (empty($title) && !empty($alt)) {
            $title = $alt;
        }

        // Return only the text with alt and title
        if (!empty($alt) || !empty($title)) {
            return trim("$alt $title");
        }

        return '';
    }
}
