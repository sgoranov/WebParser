<?php
include '../vendor/autoload.php';

$loader = new \WebParser\CurlParser();
$loader->addCallable(function (string $response, string $url) {

    // parse single news
    $newsParser = new \WebParser\CurlParser();
    $newsParser->addCallable(function (string $response, string $url) {
        $document = new \DOMDocument;
        $document->preserveWhiteSpace = false;
        @$document->loadHTML($response);

        $xpath = new \DOMXPath($document);
        $entries = $xpath->query(\Zend\Dom\Document\Query::cssToXpath('h1.story-body__h1'));
        $title = $entries[0];
        $title = $title->textContent;

        $entries = $xpath->query(\Zend\Dom\Document\Query::cssToXpath('div.story-body__inner'));
        $content = $entries[0];
        $content = $content->ownerDocument->saveHTML($content);
        $content = Html2Text\Html2Text::convert($content);

        // TODO: save the title and the content to database
    });

    // get list with news
    $document = new \DOMDocument;
    $document->preserveWhiteSpace = false;
    @$document->loadHTML($response);

    $xpath = new \DOMXPath($document);
    $entries = $xpath->query(\Zend\Dom\Document\Query::cssToXpath('div.column--primary a.faux-block-link__overlay-link'));

    /** @var \DOMElement $entry */
    foreach ($entries as $entry) {
        // parse every single news
        $newsParser->start('https://www.bbc.com' . $entry->getAttribute('href'));
    }
});

$loader->start('https://www.bbc.com/news/world');
