<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Search\EventListener;

use Contao\CoreBundle\Search\Document;
use Contao\CoreBundle\Search\Indexer\IndexerInterface;
use Terminal42\Escargot\Event\SuccessfulResponseEvent;

class SearchIndexListener implements EscargotEventSubscriber
{
    /**
     * @var IndexerInterface
     */
    private $indexer;

    public function __construct(IndexerInterface $indexer)
    {
        $this->indexer = $indexer;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'search-index';
    }

    public function onSuccessfulResponse(SuccessfulResponseEvent $event): void
    {
        $document = new Document(
            $event->getCrawlUri()->getUri(),
            $event->getResponse()->getStatusCode(),
            $event->getResponse()->getHeaders(),
            $event->getResponse()->getContent(),
        );

        $this->indexer->index($document);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            SuccessfulResponseEvent::class => 'onSuccessfulResponse',
        ];
    }
}
