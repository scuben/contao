<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_news_feed'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'enableVersioning'            => true,
		'markAsCopy'                  => 'title',
		'onload_callback' => array
		(
			array('tl_news_feed', 'checkPermission'),
			array('tl_news_feed', 'generateFeed')
		),
		'oncreate_callback' => array
		(
			array('tl_news_feed', 'adjustPermissions')
		),
		'oncopy_callback' => array
		(
			array('tl_news_feed', 'adjustPermissions')
		),
		'onsubmit_callback' => array
		(
			array('tl_news_feed', 'scheduleUpdate')
		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'alias' => 'index'
			)
		),
		'backlink'                    => 'do=news'
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 1,
			'fields'                  => array('title'),
			'flag'                    => 1,
			'panelLayout'             => 'filter;search,limit'
		),
		'label' => array
		(
			'fields'                  => array('title'),
			'format'                  => '%s'
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			),
		),
		'operations' => array
		(
			'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg'
			),
			'copy' => array
			(
				'href'                => 'act=copy',
				'icon'                => 'copy.svg',
				'button_callback'     => array('tl_news_feed', 'copyFeed')
			),
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				'button_callback'     => array('tl_news_feed', 'deleteFeed')
			),
			'show' => array
			(
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{title_legend},title,alias,language;{archives_legend},archives;{config_legend},format,source,maxItems,feedBase,description'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'title' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'alias' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'alias', 'unique'=>true, 'maxlength'=>128, 'tl_class'=>'w50 clr'),
			'save_callback' => array
			(
				array('tl_news_feed', 'checkFeedAlias')
			),
			'sql'                     => "varchar(255) BINARY NOT NULL default ''"
		),
		'language' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'filter'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>32, 'tl_class'=>'w50'),
			'sql'                     => "varchar(32) NOT NULL default ''"
		),
		'archives' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'checkbox',
			'options_callback'        => array('tl_news_feed', 'getAllowedArchives'),
			'eval'                    => array('multiple'=>true, 'mandatory'=>true),
			'sql'                     => "blob NULL"
		),
		'format' => array
		(
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'select',
			'options'                 => array('rss'=>'RSS 2.0', 'atom'=>'Atom'),
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "varchar(32) NOT NULL default 'rss'"
		),
		'source' => array
		(
			'exclude'                 => true,
			'inputType'               => 'select',
			'options'                 => array('source_teaser', 'source_text'),
			'reference'               => &$GLOBALS['TL_LANG']['tl_news_feed'],
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => "varchar(32) NOT NULL default 'source_teaser'"
		),
		'maxItems' => array
		(
			'exclude'                 => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'rgxp'=>'natural', 'tl_class'=>'w50'),
			'sql'                     => "smallint(5) unsigned NOT NULL default 25"
		),
		'feedBase' => array
		(
			'default'                 => Contao\Environment::get('base'),
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('trailingSlash'=>true, 'rgxp'=>'url', 'decodeEntities'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'description' => array
		(
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('style'=>'height:60px', 'tl_class'=>'clr'),
			'sql'                     => "text NULL"
		)
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @property Contao\News $News
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_news_feed extends Contao\Backend
{

	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Contao\BackendUser', 'User');
	}

	/**
	 * Check permissions to edit table tl_news_archive
	 *
	 * @throws Contao\CoreBundle\Exception\AccessDeniedException
	 */
	public function checkPermission()
	{
		if ($this->User->isAdmin)
		{
			return;
		}

		// Set the root IDs
		if (empty($this->User->newsfeeds) || !\is_array($this->User->newsfeeds))
		{
			$root = array(0);
		}
		else
		{
			$root = $this->User->newsfeeds;
		}

		$GLOBALS['TL_DCA']['tl_news_feed']['list']['sorting']['root'] = $root;

		// Check permissions to add feeds
		if (!$this->User->hasAccess('create', 'newsfeedp'))
		{
			$GLOBALS['TL_DCA']['tl_news_feed']['config']['closed'] = true;
			$GLOBALS['TL_DCA']['tl_news_feed']['config']['notCreatable'] = true;
			$GLOBALS['TL_DCA']['tl_news_feed']['config']['notCopyable'] = true;
		}

		// Check permissions to delete feeds
		if (!$this->User->hasAccess('delete', 'newsfeedp'))
		{
			$GLOBALS['TL_DCA']['tl_news_feed']['config']['notDeletable'] = true;
		}

		/** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
		$objSession = Contao\System::getContainer()->get('session');

		// Check current action
		switch (Contao\Input::get('act'))
		{
			case 'select':
				// Allow
				break;

			case 'create':
				if (!$this->User->hasAccess('create', 'newsfeedp'))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to create news feeds.');
				}
				break;

			case 'edit':
			case 'copy':
			case 'delete':
			case 'show':
				if (!\in_array(Contao\Input::get('id'), $root) || (Contao\Input::get('act') == 'delete' && !$this->User->hasAccess('delete', 'newsfeedp')))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' news feed ID ' . Contao\Input::get('id') . '.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'copyAll':
				$session = $objSession->all();
				if (Contao\Input::get('act') == 'deleteAll' && !$this->User->hasAccess('delete', 'newsfeedp'))
				{
					$session['CURRENT']['IDS'] = array();
				}
				else
				{
					$session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $root);
				}
				$objSession->replace($session);
				break;

			default:
				if (Contao\Input::get('act'))
				{
					throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' news feeds.');
				}
				break;
		}
	}

	/**
	 * Add the new feed to the permissions
	 *
	 * @param $insertId
	 */
	public function adjustPermissions($insertId)
	{
		// The oncreate_callback passes $insertId as second argument
		if (\func_num_args() == 4)
		{
			$insertId = func_get_arg(1);
		}

		if ($this->User->isAdmin)
		{
			return;
		}

		// Set root IDs
		if (empty($this->User->newsfeeds) || !\is_array($this->User->newsfeeds))
		{
			$root = array(0);
		}
		else
		{
			$root = $this->User->newsfeeds;
		}

		// The feed is enabled already
		if (\in_array($insertId, $root))
		{
			return;
		}

		/** @var Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface $objSessionBag */
		$objSessionBag = Contao\System::getContainer()->get('session')->getBag('contao_backend');

		$arrNew = $objSessionBag->get('new_records');

		if (\is_array($arrNew['tl_news_feed']) && \in_array($insertId, $arrNew['tl_news_feed']))
		{
			// Add the permissions on group level
			if ($this->User->inherit != 'custom')
			{
				$objGroup = $this->Database->execute("SELECT id, newsfeeds, newsfeedp FROM tl_user_group WHERE id IN(" . implode(',', array_map('\intval', $this->User->groups)) . ")");

				while ($objGroup->next())
				{
					$arrNewsfeedp = Contao\StringUtil::deserialize($objGroup->newsfeedp);

					if (\is_array($arrNewsfeedp) && \in_array('create', $arrNewsfeedp))
					{
						$arrNewsfeeds = Contao\StringUtil::deserialize($objGroup->newsfeeds, true);
						$arrNewsfeeds[] = $insertId;

						$this->Database->prepare("UPDATE tl_user_group SET newsfeeds=? WHERE id=?")
									   ->execute(serialize($arrNewsfeeds), $objGroup->id);
					}
				}
			}

			// Add the permissions on user level
			if ($this->User->inherit != 'group')
			{
				$objUser = $this->Database->prepare("SELECT newsfeeds, newsfeedp FROM tl_user WHERE id=?")
										   ->limit(1)
										   ->execute($this->User->id);

				$arrNewsfeedp = Contao\StringUtil::deserialize($objUser->newsfeedp);

				if (\is_array($arrNewsfeedp) && \in_array('create', $arrNewsfeedp))
				{
					$arrNewsfeeds = Contao\StringUtil::deserialize($objUser->newsfeeds, true);
					$arrNewsfeeds[] = $insertId;

					$this->Database->prepare("UPDATE tl_user SET newsfeeds=? WHERE id=?")
								   ->execute(serialize($arrNewsfeeds), $this->User->id);
				}
			}

			// Add the new element to the user object
			$root[] = $insertId;
			$this->User->newsfeeds = $root;
		}
	}

	/**
	 * Return the copy news feed button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function copyFeed($row, $href, $label, $title, $icon, $attributes)
	{
		return $this->User->hasAccess('create', 'newsfeedp') ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
	}

	/**
	 * Return the delete news feed button
	 *
	 * @param array  $row
	 * @param string $href
	 * @param string $label
	 * @param string $title
	 * @param string $icon
	 * @param string $attributes
	 *
	 * @return string
	 */
	public function deleteFeed($row, $href, $label, $title, $icon, $attributes)
	{
		return $this->User->hasAccess('delete', 'newsfeedp') ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg/i', '_.svg', $icon)).' ';
	}

	/**
	 * Check for modified news feeds and update the XML files if necessary
	 */
	public function generateFeed()
	{
		/** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
		$objSession = Contao\System::getContainer()->get('session');

		$session = $objSession->get('news_feed_updater');

		if (empty($session) || !\is_array($session))
		{
			return;
		}

		$this->import('Contao\News', 'News');

		foreach ($session as $id)
		{
			$this->News->generateFeed($id);
		}

		$this->import('Contao\Automator', 'Automator');
		$this->Automator->generateSitemap();

		$objSession->set('news_feed_updater', null);
	}

	/**
	 * Schedule a news feed update
	 *
	 * This method is triggered when a single news archive or multiple news
	 * archives are modified (edit/editAll).
	 *
	 * @param Contao\DataContainer $dc
	 */
	public function scheduleUpdate(Contao\DataContainer $dc)
	{
		// Return if there is no ID
		if (!$dc->id)
		{
			return;
		}

		/** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
		$objSession = Contao\System::getContainer()->get('session');

		// Store the ID in the session
		$session = $objSession->get('news_feed_updater');
		$session[] = $dc->id;
		$objSession->set('news_feed_updater', array_unique($session));
	}

	/**
	 * Return the IDs of the allowed news archives as array
	 *
	 * @return array
	 */
	public function getAllowedArchives()
	{
		if ($this->User->isAdmin)
		{
			$objArchive = Contao\NewsArchiveModel::findAll();
		}
		else
		{
			$objArchive = Contao\NewsArchiveModel::findMultipleByIds($this->User->news);
		}

		$return = array();

		if ($objArchive !== null)
		{
			while ($objArchive->next())
			{
				$return[$objArchive->id] = $objArchive->title;
			}
		}

		return $return;
	}

	/**
	 * Check the RSS-feed alias
	 *
	 * @param mixed                $varValue
	 * @param Contao\DataContainer $dc
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function checkFeedAlias($varValue, Contao\DataContainer $dc)
	{
		// No change or empty value
		if ($varValue == $dc->value || $varValue == '')
		{
			return $varValue;
		}

		$varValue = Contao\StringUtil::standardize($varValue); // see #5096

		$this->import('Contao\Automator', 'Automator');
		$arrFeeds = $this->Automator->purgeXmlFiles(true);

		// Alias exists
		if (\in_array($varValue, $arrFeeds))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
		}

		return $varValue;
	}
}
