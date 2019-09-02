<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Search\Indexer;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Search\Document;
use Contao\Search;

class DefaultIndexer implements IndexerInterface
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var bool
     */
    private $isEnabled = true;

    /**
     * @var bool
     */
    private $indexProtected = false;

    public function __construct(ContaoFramework $framework, bool $isEnabled = true, bool $indexProtected = false)
    {
        $this->framework = $framework;
        $this->isEnabled = $isEnabled;
        $this->indexProtected = $indexProtected;
    }

    /**
     * {@inheritdoc}
     */
    public function index(Document $document): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (200 !== $document->getStatusCode()) {
            return;
        }

        $meta = [
            'protected' => false,
            'groups' => [],
            'pageId' => 0,
        ];

        $this->extendMetaFromJsonLdScripts($document, $meta);

        // If search was disabled in the page settings, we do not index
        if (isset($meta['noSearch']) && true === $meta['noSearch']) {
            return;
        }

        // If the front end preview is activated, we do not index
        if (isset($meta['fePreview']) && true === $meta['fePreview']) {
            return;
        }

        // If the page is protected, we only index if the member is logged in and protecting indexed pages is enabled
        if (isset($meta['protected']) && true === $meta['protected']) {
            if (!$this->indexProtected) {
                return;
            }

            if (!isset($meta['memberId']) || null === $meta['memberId']) {
                return;
            }
        }

        $this->framework->initialize(true);
        $this->framework->getAdapter(Search::class)->indexPage([
            'url' => (string) $document->getUri(),
            'content' => $document->getBody(),
            'protected' => ($meta['protected']) ? '1' : '',
            'groups' => $meta['groups'],
            'pid' => $meta['pageId'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        // TODO: implement current default behaviour
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    private function extendMetaFromJsonLdScripts(Document $document, array &$meta): void
    {
        $jsonLds = $document->extractJsonLdScripts('https://contao.org/', 'PageMetaData');

        if (0 === \count($jsonLds)) {
            return;
        }

        // Merge all entries to one meta array (the latter overrides the former)
        $meta = array_merge(...$jsonLds);
    }
}
