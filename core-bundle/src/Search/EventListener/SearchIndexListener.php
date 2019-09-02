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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Terminal42\Escargot\Escargot;
use Terminal42\Escargot\Event\FinishedCrawlingEvent;
use Terminal42\Escargot\Event\SuccessfulResponseEvent;

class SearchIndexListener implements EscargotEventSubscriber, ControllerResultProvidingSubscriberInterface
{
    /**
     * @var IndexerInterface
     */
    private $indexer;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $logFile;

    /**
     * @var array
     */
    private $logLines = [];

    public function __construct(IndexerInterface $indexer, RouterInterface $router, Filesystem $filesystem = null)
    {
        $this->indexer = $indexer;
        $this->router = $router;
        $this->filesystem = $filesystem ?? new Filesystem();
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
        $response = $event->getResponse();

        $this->logLines[] = [
            $response->getStatusCode(),
            (string) $event->getCrawlUri()->getUri(),
            $event->getCrawlUri()->getLevel(),
            (string) $event->getCrawlUri()->getFoundOn(),
            'no',
            '',
        ];

        $document = new Document(
            $event->getCrawlUri()->getUri(),
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getContent()
        );

        $this->indexer->index($document);
    }

    public function onFinishedCrawling(FinishedCrawlingEvent $event): void
    {
        $this->init($event->getEscargot()->getJobId());

        $handle = fopen($this->logFile, 'a');

        // Check if we need to add the headlines
        if (0 === filesize($this->logFile)) {
            fputcsv($handle, [
                'HTTP status code',
                'URI',
                'Level',
                'Found on',
                'Skipped',
                'Skip reason',
            ]);
        }

        foreach ($this->logLines as $line) {
            fputcsv($handle, $line);
        }

        fclose($handle);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            SuccessfulResponseEvent::class => 'onSuccessfulResponse',
            FinishedCrawlingEvent::class => 'onFinishedCrawling',
        ];
    }

    public function getResultAsHtml(Escargot $escargot): string
    {
        $this->init($escargot->getJobId());

        return sprintf('<a href="%s">Download log as CSV!</a>', $this->getDownloadLink($escargot->getJobId()));
    }

    public function addResultToConsole(Escargot $escargot, OutputInterface $output): void
    {
        $this->init($escargot->getJobId());

        $output->writeln('Rebuilt search index!');
        $output->writeln('The log file can be found here: '.$this->logFile);
    }

    public function controllerAction(Request $request, string $jobId): Response
    {
        $this->init($jobId);

        $response = new BinaryFileResponse($this->logFile);
        $response->setPrivate();
        $response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'search-index.csv');

        return $response;
    }

    private function getDownloadLink(string $jobId): string
    {
        return $this->router->generate('contao_escargot_subscriber', [
            'subscriber' => $this->getName(),
            'jobId' => $jobId,
        ]);
    }

    private function init(string $jobId): void
    {
        if (null !== $this->logFile) {
            return;
        }

        $this->logFile = sprintf('%s/search-index-listener-%s-log.csv',
            sys_get_temp_dir(),
            $jobId
        );

        if (!$this->filesystem->exists($this->logFile)) {
            $this->filesystem->dumpFile($this->logFile, '');
        }
    }
}
