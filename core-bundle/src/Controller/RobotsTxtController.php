<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\RobotsTxtEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use webignition\RobotsTxt\Directive\Directive;
use webignition\RobotsTxt\File\File;
use webignition\RobotsTxt\Record\Record;

class RobotsTxtController
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/robots.txt", name="contao_robots_txt")
     */
    public function __invoke(Request $request): Response
    {
        $file = new File();
        $defaultRecord = new Record();
        $defaultRecord->getDirectiveList()->add(new Directive('Disallow'));

        $file->addRecord($defaultRecord);

        $this->eventDispatcher->dispatch(new RobotsTxtEvent($file, $request), ContaoCoreEvents::ROBOTS_TXT);

        return new Response((string) $file, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
