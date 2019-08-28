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

use Contao\CoreBundle\Event\RobotsTxtEvent;
use Doctrine\DBAL\Driver\Connection;
use webignition\RobotsTxt\Directive\Directive;

class AddSitemapsToRobotsTxtListener
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function onRobotsTxt(RobotsTxtEvent $event): void
    {
        $request = $event->getRequest();

        $rootPages = $this->connection->fetchAll(
            "SELECT * FROM tl_page WHERE createSitemap=1 AND dns=? AND sitemapName!='' AND published=1",
            [$request->server->get('HTTP_HOST')]
        );

        // Generate the sitemaps
        foreach ($rootPages as $rootPage) {
            $sitemap = sprintf('%s/share/%s.xml',
                ($rootPage['useSSL'] ? 'https://' : 'http://').($rootPage['dns'] ?: $request->server->get('HTTP_HOST')),
                $rootPage['sitemapName']
            );

            $event->getFile()->getNonGroupDirectives()->add(new Directive('Sitemap', $sitemap));
        }
    }
}
