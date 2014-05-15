<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 *
 * @copyright   Copyright (C) 2014 Schultschik Websolution - Sven Schultschik. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;
JHtml::_('behavior.tabstate');


$controller	= JControllerLegacy::getInstance('Winabor');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
