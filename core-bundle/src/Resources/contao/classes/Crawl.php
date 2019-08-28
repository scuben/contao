<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Search\EscargotFactory;
use Nyholm\Psr7\Uri;
use Psr\Log\Test\TestLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Terminal42\Escargot\BaseUriCollection;
use Terminal42\Escargot\Event\FinishedCrawlingEvent;
use Terminal42\Escargot\Event\SuccessfulResponseEvent;
use Terminal42\Escargot\EventSubscriber\LoggerSubscriber;
use Terminal42\Escargot\Exception\InvalidJobIdException;
use Terminal42\Escargot\Queue\InMemoryQueue;

/**
 * Maintenance module "crawl".
 *
 * @author Yanick Witschi <https://github.com/toflar>
 */
class Crawl extends Backend implements \executable
{
	/**
	 * Return true if the module is active
	 *
	 * @return boolean
	 */
	public function isActive()
	{
		return Input::get('act') == 'crawl';
	}

	/**
	 * Generate the module
	 *
	 * @return string
	 */
	public function run()
	{
		$template = new BackendTemplate('be_crawl');
		$template->action = ampersand(Environment::get('request'));
		$template->isActive = $this->isActive();


		/** @var EscargotFactory $factory */
		$factory = System::getContainer()->get('contao.search.escargot_factory');
		$template->subscriberNames = $factory->getSubscriberNames();

		if (!$this->isActive()) {
			return $template->parse();
		}

		$selectedSubscribers = (array) \Input::get('crawl_subscriber_names');
		$jobId = \Input::get('jobId');

		if (!$selectedSubscribers) {
			$template->error = 'You have to select at least one option!';
			return $template->parse();
		}

		$template->isRunning = true;

		$queue = $factory->createLazyQueue();

		if (!$jobId) {
			$baseUris = $factory->getSearchUriCollection();
			$escargot = $factory->create($baseUris, $queue, $selectedSubscribers);
			Controller::redirect(\Controller::addToUrl('&jobId=' . $escargot->getJobId()));
		} else {
			try {
				$escargot = $factory->createFromJobId($jobId, $queue, $selectedSubscribers);
			} catch (InvalidJobIdException $e) {
				Controller::redirect(str_replace('&jobId='. $jobId, '', Environment::get('request')));
			}
		}

		$escargot->setConcurrency(10); // TODO: Configurable
		$escargot->setMaxRequests(5); // TODO: Configurable
		$recorder = $this->getRecorder();
		$escargot->addSubscriber($recorder);

		if (Environment::get('isAjaxRequest')) {
			// Start crawling
			$escargot->crawl();

			// Commit the result on the lazy queue
			$queue->commit($jobId);

			// Return the results
			$response = new JsonResponse([
				'results' => $recorder->getResults(),
				'finished' => 0 === $queue->countPending($jobId),
			]);
			throw new ResponseException($response);
		}

		return $template->parse();
	}

	private function getRecorder(): EventSubscriberInterface
	{
		return new class implements EventSubscriberInterface
		{
			private $results = [];

			public function getResults(): array
			{
				return $this->results;
			}

			public function onSuccessfulResponse(SuccessfulResponseEvent $event): void
			{
				$this->results[(string) $event->getCrawlUri()->getUri()] = $event->getResponse()->getStatusCode();
			}

			public static function getSubscribedEvents()
			{
				return [
					SuccessfulResponseEvent::class => 'onSuccessfulResponse',
				];
			}
		};
	}
}
