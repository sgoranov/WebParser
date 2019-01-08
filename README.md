# Web Parser

CURL based web crawler written in PHP.

## Installation

Add the following repository in yours composer.json file:

```
"repositories": [
        {
            "url": "https://github.com/sgoranov/WebParser.git",
            "type": "git"
        }
    ],
```
Then install the package running the following command:

```
composer require sgoranov/WebParser
```

## The BBC news example

The example below is located in *examples/bbcnews.php*. With this code we'll fetch a short
news list from BBC and then for each one we'll do one additional request to get 
the title and the full content.

The bbcnews example uses *zendframework/zend-dom* to convert the CSS selector queries to 
XPath and *soundasleep/html2text* to convert the news content to plain text. These two
packages are not required and you can use any other tool for conversion or plain XPath.

```php
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
```

WebParser's most important method is **addCallable(callable $handler);** which handles the response. The input 
parameters expected from the handler are the body and the URL both passed as strings. As you can see from the example 
we use Document Object Model with XPath to fetch the news list from the main page. We are interested
in the URL which points to the news where we can get the full content.

```php
// get list with news
$document = new \DOMDocument;
$document->preserveWhiteSpace = false;
@$document->loadHTML($response);

$xpath = new \DOMXPath($document);
$entries = $xpath->query(\Zend\Dom\Document\Query::cssToXpath('div.column--primary a.faux-block-link__overlay-link'));
```

Then we instantiate another web parser with a different handler - responsible
to fetch the news title and content from the news details page:

```php
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
```

This web parser is executed inside the main one against every single entry from the list:
```php
$newsParser->start('https://www.bbc.com' . $entry->getAttribute('href'));
```
 
