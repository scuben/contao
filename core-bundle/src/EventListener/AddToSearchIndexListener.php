<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener;

use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;
use Nyholm\Psr7\Uri;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class AddToSearchIndexListener
{
    /**
     * @var IndexerInterface
     */
    private $indexer;

    /**
     * @var string
     */
    private $fragmentPath;

    public function __construct(IndexerInterface $indexer, string $fragmentPath = '_fragment')
    {
        $this->indexer = $indexer;
        $this->fragmentPath = $fragmentPath;
    }

    /**
     * Checks if the request can be indexed and forwards it accordingly.
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        // Only index GET requests (see #1194)
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }
        // Do not index fragments
        if (preg_match('~(?:^|/)'.preg_quote($this->fragmentPath, '~').'/~', $request->getPathInfo())) {
            return;
        }

        if (!$this->indexer->isEnabled()) {
            return;
        }

        $response = $event->getResponse();

        $document = new Document(
            new Uri($request->getUri()),
            $response->getStatusCode(),
            $response->headers->all(),
            $response->getContent()
        );

        // If there are no json ld scripts at all, nothing will be indexed.
        $lds = $document->extractJsonLdScripts();

        if (0 === \count($lds)) {
            return;
        }

        $this->indexer->index($document);
    }
}
