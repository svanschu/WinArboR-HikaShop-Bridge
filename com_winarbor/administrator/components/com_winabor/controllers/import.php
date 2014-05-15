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
 * Winabor import controller class.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 */
class WinaborControllerImport extends JControllerForm
{

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->registerTask('new', 'newIm');
    }

    /**
     * Proxy for getModel.
     * @since       2.5
     */
    public function getModel($name = 'Import', $prefix = 'WinaborModel', $config = array())
    {
        $model = parent::getModel($name, $prefix, array('ignore_request' => true));
        return $model;
    }

    public function newIm()
    {
        // Redirect to the edit screen.
        $this->setRedirect(
            JRoute::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_item . '&layout=import'
                , false
            )
        );

        return true;
    }

    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries.
        JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

        jimport('joomla.file');

        $data  = $this->input->post->get('jform', array(), 'array');
        $data  = JPATH_ROOT . $data['import_folder'] . '/' . $data['import_file'];

        if (!JFile::exists($data)){
            JLog::add('File not found: ' . $data, JLog::ERROR);
        }

        $model = $this->getModel();
        $model->importXML($data);

    }

}
