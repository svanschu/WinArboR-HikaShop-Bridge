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
class WinaborViewImport extends JViewLegacy
{
    /**
     * Display the view
     *
     * @param null $tpl
     * @return  void
     */
    public function display($tpl = null)
    {
        // get the Data
        $form = $this->get('Form');

        // Check for errors.
        if (count($errors = $this->get('Errors')))
        {
            JError::raiseError(500, implode('<br />', $errors));
            return false;
        }
        // Assign the Data
        $this->form = $form;

        // Set the toolbar
        $this->addToolBar();

        // Display the template
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @since   1.6
     */
    protected function addToolbar()
    {
        $input = JFactory::getApplication()->input;
        $input->set('hidemainmenu', true);

        $user = JFactory::getUser();

        JToolbarHelper::title(JText::_('COM_WINABOR_IMPORT'));

        if ($user->authorise('winabor.import', 'com_winabor')) {
            JToolbarHelper::save('import.save', 'COM_WINABOR_STARTIMPORT');
        }

        JToolbarHelper::cancel();

    }
}