<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Search;

use Psr\Http\Message\UriInterface;

class Document
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * An array of headers.
     * The key is the header name in lowercase letters
     * and the value is again an array of header
     * values.
     *
     * @var array<string,array>
     */
    private $headers = [];

    /**
     * @var string
     */
    private $body;

    /**
     * @var array|null
     */
    private $jsonLd;

    public function __construct(UriInterface $uri, int $statusCode, array $headers = [], string $body = '')
    {
        $this->uri = $uri;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Extracts all <script type="application/ld+json">
     * script tags and returns their contents as a JSON decoded
     * array.
     * Optionally allows to restrict it to a given context and
     * type.
     * Uses regex parsing for fastest results. This, however,
     * means that you cannot use it when you e.g. use
     * <script type="application/ld+json"> within CDATA etc.
     * In this case, you have to parse the contents yourself.
     */
    public function extractJsonLdScripts($context = '', $type = ''): array
    {
        if (null !== $this->jsonLd) {
            return $this->jsonLd;
        }

        $this->jsonLd = [];

        if ('' === $this->body) {
            return $this->jsonLd;
        }

        preg_match_all('@^<script type="application/ld\+json">(.+)</script>$@m', $this->body, $matches);

        if (!isset($matches[1])) {
            return $this->jsonLd;
        }

        foreach ($matches[1] as $match) {
            $data = json_decode($match, true);

            if ('' !== $context && (!isset($data['@context']) || $data['@context'] !== $context)) {
                continue;
            }

            if ('' !== $type && (!isset($data['@type']) || $data['@type'] !== $type)) {
                continue;
            }

            $this->jsonLd[] = $data;
        }

        return $this->jsonLd;
    }
}
