<?php

namespace App\Framework;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class Scraper
{
    private string $url;
    private Client $client;
    private ?Crawler $crawler = null;

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    public function fetch(): self
    {
        $response = $this->client->get($this->url);
        $html = (string) $response->getBody();
        $this->crawler = new Crawler($html);
        return $this;
    }

    public function filter(string $selector)
    {
        if (!$this->crawler) {
            $this->fetch();
        }
        return $this->crawler->filter($selector);
    }

    public function getText(string $selector): string
    {
        return trim($this->filter($selector)->text());
    }

    public function getHtml(string $selector): string
    {
        return trim($this->filter($selector)->html());
    }
} 