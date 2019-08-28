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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface EscargotEventSubscriber extends EventSubscriberInterface
{
    /**
     * Has to return a unique subscriber name so that it can be
     * identified.
     */
    public function getName(): string;
}
