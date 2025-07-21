<?php

    namespace Hamaka\Typesense\Extensions;

    use SilverStripe\Core\Environment;
    use SilverStripe\View\TemplateGlobalProvider;

    class TemplateGlobalProviderExtension implements TemplateGlobalProvider
    {

        public static function TypesenseSearchKey()
        {
            return Environment::getEnv('TYPESENSE_SEARCH_KEY');
        }

        public static function TypesenseServer()
        {
            return Environment::getEnv('TYPESENSE_SERVER');
        }

        public static function get_template_global_variables()
        {
            return [
                'TypesenseSearchKey',
                'TypesenseServer'
            ];
        }
    }
