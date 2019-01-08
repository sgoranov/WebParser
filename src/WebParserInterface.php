<?php
namespace WebParser;

interface WebParserInterface
{
    public function follow(string $xpath);
    public function start(string $url, $method = 'GET', array $postData = []);
    public function addCallable(callable $handler);
}