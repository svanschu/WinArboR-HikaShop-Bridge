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
 * View class for a list of winabor
 *
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 */
class WinaborViewWinabor extends JViewLegacy
{
    protected $items;

    protected $pagination;

    protected $state;

    /**
     * Display the view
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        //$this->state		= $this->get('State');
        //$this->items		= $this->get('Items');
        //$this->pagination	= $this->get('Pagination');

        $this->addToolbar();
        //$this->sidebar = JHtmlSidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @since   1.6
     */
    protected function addToolbar()
    {

        //$state	= $this->get('State');
        //$canDo	= JHelperContent::getActions('com_weblinks', 'category', $state->get('filter.category_id'));
        $user	= JFactory::getUser();

        JToolbarHelper::title(JText::_('COM_WINABOR'));

        JToolbarHelper::addNew('import.new', 'COM_WINABOR_IMPORT');

        JToolbarHelper::preferences('com_winabor');
    }
}