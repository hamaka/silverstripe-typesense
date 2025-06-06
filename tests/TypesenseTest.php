<?php

namespace Hamaka\Typesense\Tests;

use Hamaka\Pages\NewsPost;
use Hamaka\Typesense\Service\TypesenseService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Test the Typesense integration
 */
class TypesenseTest extends SapphireTest
{
    /**
     * Test that a NewsPost can be converted to a Typesense document
     */
    public function testNewsPostToTypesenseDocument()
    {
        // Get a NewsPost
        $newsPost = NewsPost::get()->first();

        if (!$newsPost) {
            $this->markTestSkipped('No NewsPost found to test with');
        }

        // Get the Typesense document directly from the NewsPost
        $document = $newsPost->getTypesenseDocument();

        // Verify that the document contains the expected fields
        $this->assertArrayHasKey('id', $document);
        $this->assertArrayHasKey('ClassName', $document);
        $this->assertArrayHasKey('Title', $document);
        $this->assertArrayHasKey('LastEdited', $document);
        $this->assertArrayHasKey('Created', $document);
        $this->assertArrayHasKey('FullPage', $document);

        // Get the Typesense document using the service
        $service = Injector::inst()->get(TypesenseService::class);
        $serviceDocument = $service->getTypesenseDocument($newsPost);

        // Verify that both methods produce the same result
        $this->assertEquals($document, $serviceDocument);
    }

    /**
     * Test that HTML can be converted to accessible text
     */
    public function testHtmlToAccessibleText()
    {
        $service = Injector::inst()->get(TypesenseService::class);

        $html = '<h1>Test Heading</h1><p>This is a test paragraph.</p>';
        $text = $service->convertHtmlToAccessibleText($html);

        $this->assertStringContainsString('Test Heading', $text);
        $this->assertStringContainsString('This is a test paragraph', $text);
    }
}
