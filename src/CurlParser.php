<?php
namespace WebParser;

class CurlParser implements WebParserInterface
{
    private $options = [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36',
    ];

    private $follow = [];
    private $urlParts = [];
    private $delayBetweenRequests = 0;
    private $callables = [];

    // md5 hashes on all visited links
    private $visited = [];

    // md5 hashes on all parsed pages
    private $parsed = [];

    public function __construct(array $options = [], int $delayBetweenRequests = 1000)
    {
        $this->options = array_replace($this->options, $options);
        $this->delayBetweenRequests = $delayBetweenRequests;
    }

    public function addCallable(callable $handler)
    {
        $this->callables[] = $handler;
    }

    public function follow(string $xpath)
    {
        $this->follow[] = $xpath;
    }

    public function start(string $url, $method = 'GET', array $postData = [])
    {
        // check for valid URL
        $urlParts = parse_url($url);
        if ($urlParts === false) {
            throw new \InvalidArgumentException("Invalid URL passed: $url");
        }
        $this->urlParts = $urlParts;

        // check if the URL is already visited
        $hash = md5($url . $method . serialize($postData));
        if (in_array($hash, $this->visited)) {
            return;
        }
        $this->visited[] = $hash;

        // delay between requests
        usleep($this->delayBetweenRequests * 1000);

        $ch = curl_init();

        foreach ($this->options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);

        // check if the response is already parsed
        $hash = md5($response);
        if (in_array($hash, $this->parsed)) {
            return;
        }
        $this->parsed[] = $hash;

        // execute all attached callables
        foreach ($this->callables as $callable) {
            call_user_func($callable, $response, $url);
        }

        $doc = new \DOMDocument;
        $doc->preserveWhiteSpace = false;
        $isLoaded = @$doc->loadHTML($response);

        if (!$isLoaded) {
            throw new \Exception("Unable to load the HTML using the following URL: $url");
        }

        $links = $this->getLinksToFollow($doc);
        if (!empty($links)) {
            foreach ($links as $link) {
                $this->start($link);
            }
        }

        curl_close ($ch);
    }

    private function getLinksToFollow(\DOMDocument $document)
    {
        $linksToFollow = [];

        foreach ($this->follow as $query) {
            $xpath = new \DOMXPath($document);
            $entries = $xpath->query($query);

            foreach ($entries as $entry) {
                $url = $entry->getAttribute('href');

                // build the absolute URL if needed
                if (!$this->isAbsolutePath($url)) {
                    // TODO: use http_build_url instead
                    $url = $this->urlParts['scheme'] . '://' . $this->urlParts['host'] . '/' . ltrim($url, '/');
                }

                // skip duplicates
                if (!in_array($url, $linksToFollow)) {
                    $linksToFollow[] = $url;
                }
            }
        }

        return $linksToFollow;
    }

    private function isAbsolutePath($file)
    {
        return strspn($file, '/\\', 0, 1)
            || (strlen($file) > 3 && ctype_alpha($file[0])
                && substr($file, 1, 1) === ':'
                && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
            ;
    }
}