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
 * Winabor Controller
 *
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 */
class WinaborController extends JControllerLegacy
{
    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
     *
     * @return  JController  This object to support chaining.
     *
     */
    public function display($cachable = false, $urlparams = false)
    {

        $view   = $this->input->get('view', 'winabor');
        $layout = $this->input->get('layout', 'default');

        parent::display();

        return $this;
    }
}
