<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 *
 * @copyright   Copyright (C) 2014 Schultschik Websolution - Sven Schultschik. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

/**
 * Winabor list controller class.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 */
class WinaborControllerWinabor extends JControllerAdmin
{
    /**
     * Proxy for getModel.
     * @since       2.5
     */
    public function getModel($name = 'Import', $prefix = 'WinaborModel')
    {
        $model = parent::getModel($name, $prefix, array('ignore_request' => true));
        return $model;
    }
}
