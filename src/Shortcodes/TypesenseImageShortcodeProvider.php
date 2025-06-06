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
}
