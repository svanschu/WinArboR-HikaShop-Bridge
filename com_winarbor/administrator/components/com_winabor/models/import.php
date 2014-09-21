<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_winabor
 *
 * @copyright   Copyright (C) 2014 Schultschik Websolution - Sven Schultschik. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// import Joomla modelform library
jimport('joomla.application.component.modeladmin');

/**
 * HelloWorld Model
 */
class WinaborModelImport extends JModelAdmin
{
    /**
     * @var null
     */
    protected $xml = null;

    protected $db = null;

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->db = JFactory::getDbo();
    }


    /**
     * Method to get the record form.
     *
     * @param       array $data Data for the form.
     * @param       boolean $loadData True if the form is to load its own data (default case), false if not.
     * @return      mixed   A JForm object on success, false on failure
     * @since       2.5
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_winabor.import', 'import',
            array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return      mixed   The data for the form.
     * @since       2.5
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = JFactory::getApplication()->getUserState('com_winabor.import.winabor.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        return $data;
    }

    /**
     * @param null $pk
     * @return JRegistry|mixed
     */
    public function getItem($pk = NULL)
    {
        $params = JComponentHelper::getParams('com_winabor');
        //print_r($params);die();
        return $params;
    }

    /**
     * Importiert die Produkt XML von WiAboR
     *
     * @param $data
     */
    public function importXML($data)
    {

        $this->xml = simplexml_load_file($data);

        if ($this->xml == FALSE) {
            JLog::add('XML Konnte nicht geladen werden', JLog::ERROR);
        }

        if (!$this->importWarengruppen()) {
            JLog::add('Warengruppenimport fehlgeschlagen', JLog::ERROR);
        }

        $this->importArtikel();

        $this->createRelations();

        //die();
        $app = JFactory::$application;
        $app->enqueueMessage('Import successfull');
        $app->redirect('index.php?option=com_winabor');
    }

    private function importArtikel()
    {
        $sorten = $this->xml->xpath("Sortendaten")[0];

        //print_r($sorten);echo '<p>';

        foreach ($sorten as $sorte) {
            //print_r($sorte);
            //echo '<p>';
            $sortennummer = 's' . $sorte->children()->{'Kennung'} . $sorte->children()->{'Sortennummer'};
            $category = $sorte->xpath("Artikeldaten/Artikel")[0]->{"Artikelgruppe"};

            $sorteName = $sorte->children()->{'Bezeichnung1'};

            $pictures = $sorte->children()->{'Bilder'}[0];

            $productId = $this->isProduct($sortennummer);
            if (!$productId) {
                //create product
                $productId = $this->createProduct($sorteName, $sorte->children()->{'Beschreibung'}, $sortennummer, $category);
                $this->setPictures($pictures, $sorteName, $productId);
            } else {
                //update product
                $this->updateProduct($sorteName, $sorte->children()->{'Beschreibung'}, $productId, $category);
                $this->updatePictures($pictures, $sorteName, $productId);
            }

            if (!empty($sorte->children()->{'ArtikelAttribute'})) {
                //set Artikel Attributes
                $this->setCostumFields($productId, $sorte->children()->{'ArtikelAttribute'}[0]);
            }

            $artikeldaten = $sorte->xpath("Artikeldaten")[0];

            foreach ($artikeldaten as $artikel) {
                //print_r($artikel);
                //echo '<p>';

                $characteristicNumber = $artikel->children()->{'Kennung'} . $artikel->children()->{'Artikelnummer'};
                $quantity = $artikel->children()->{'Bestand'};

                $mwst = str_replace(",", ".", $artikel->children()->{'Mwst'}) / 100;
                $taxID = $this->getTaxId($mwst);

                $prices = array();
                $prices[] = array('price' => $this->getNetto($artikel->children()->{'Preis1'}, $mwst), 'minQuantity' => 0);

                $i = 1;
                do {
                    $minQuantity = $artikel->children()->{'Staffel' . $i};

                    $count = $minQuantity->count();

                    if ($count > 0) {
                        $i++;
                        $prices[] = array('price' => $this->getNetto($artikel->children()->{'Preis' . $i}, $mwst), 'minQuantity' => (int)$minQuantity);
                    }
                } while ($count > 0);

                $productCharacteristicID = $this->isProduct($characteristicNumber, $productId);

                if (!$productCharacteristicID) { //characteristic does not exist
                    //create characteristic

                    //exist a group of characteristics for the product?
                    list($chGroup, $newGroup) = $this->getCharacteristicGroup($sortennummer);

                    //create characteristic
                    $characteristicID = $this->createCharacteristic($artikel->children()->{'Bezeichnung2'}, $chGroup);

                    //create the product for this characteristic
                    $productCharacteristicID = $this->createProduct('', '', $characteristicNumber, $category, $quantity, $productId, $productType = 'variant', $published = 1, $taxID);

                    //create relation between product and characteristic
                    if ($newGroup) {
                        $values = array();
                        $values[] = $chGroup . "," . $productId; //add new group to main product
                        $values[] = $characteristicID . "," . $productId; //add characteristic to the main product as default
                        $this->setVariantRelation($values);
                    }

                    //set relation between characteristic product and characteristic
                    $values = array();
                    $values[] = $characteristicID . "," . $productCharacteristicID;
                    $this->setVariantRelation($values);

                    //create price
                    $this->createPrices($productCharacteristicID, $prices);


                } else {
                    //update characteristic product
                    $this->updateProduct('', '', $productCharacteristicID, $category, $quantity, $productId, $productType = 'variant', $published = 1, $taxID);

                    // update the relation between characteristic and characteristic product
                    $query = $this->db->getQuery(true);
                    $query->select($this->db->quoteName("variant_characteristic_id"))
                        ->from($this->db->quoteName("#__hikashop_variant"))
                        ->where($this->db->quoteName("variant_product_id") . "=" . $productCharacteristicID);
                    $this->db->setQuery($query);
                    $characteristicID = $this->db->loadResult();

                    $query = $this->db->getQuery(true);
                    $query->update("#__hikashop_characteristic")
                        ->set("characteristic_value='" . $this->db->escape($artikel->children()->{'Bezeichnung2'}) . "'")
                        ->where("characteristic_id=" . $characteristicID);

                    //update price
                    $this->updatePrices($productCharacteristicID, $prices);
                }

            }
        }
    }

    /**
     * Importiert Warengruppen und Artikelgruppen, die in Warengruppen enthalten sind
     *
     * @return bool
     */
    private function importWarengruppen()
    {

        $warengruppendaten = $this->xml->xpath("Warengruppendaten")[0];

        $warengruppen = $warengruppendaten->children();

        foreach ($warengruppen as $warengruppe) {

            $res = $this->isProductCategory($warengruppe->attributes()->{"Name"}, 2);

            if (!$res) {
                $id = $this->createProductCategory($warengruppe->attributes()->{"Name"}, $warengruppe->attributes()->{"Beschreibung"}, 2, 2);

                $res = $this->loadProductCategory($id);
            }

            $artikelgruppen = $warengruppe->children()->{"Artikelgruppe"};
            $parent_id = $res['category_id'];
            $categoryDepth = $res['category_depth'] + 1;

            $this->importArtikelgruppen($artikelgruppen, $parent_id, $categoryDepth);
        }

        return true;
    }

    /**
     * @param $name
     * @param $beschreibung
     * @param $parentId
     * @param $categoryDepth
     * @return mixed
     */
    private function createProductCategory($name, $beschreibung, $parentId, $categoryDepth)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $time = time();
        $query->insert('#__hikashop_category')
            ->set('category_name = \'' . $name . '\'')
            ->set('category_alias = \'' . JFilterOutput::stringURLSafe($name) . '\'')
            ->set('category_description = \'' . $beschreibung . '\'')
            ->set('category_type = \'product\'')
            ->set('category_parent_id = ' . $parentId)
            ->set('category_published = 1')
            ->set('category_depth = ' . $categoryDepth)
            ->set('category_created = ' . $time)
            ->set('category_modified = ' . $time)
            ->set('category_namekey = \'product_' . $time . '_' . rand() . '\'');
        $db->setQuery($query);
        $db->execute();
        return $db->insertid();
    }

    /**
     * checks if product category exists. returns assoc or false
     *
     * @param $name
     * @param $parent_id
     * @return array
     */
    private function isProductCategory($name, $parent_id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        // Ist die Warengruppe bekannt?
        $query->select('category_id, category_depth')
            ->from('#__hikashop_category')
            ->where('category_name=\'' . $db->escape($name) . "'")
            ->where('category_type=\'product\'')
            ->where('category_parent_id = ' . $db->escape($parent_id));

        $db->setQuery($query);
        try {
            $res = $db->loadAssoc();
        } catch (exception $e) {
            print_r($db);
            die();
        }

        if (count($res) == 0) {
            return false;
        }

        return $res;
    }

    /**
     * @param $id
     * @return mixed
     */
    private function loadProductCategory($id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('category_id, category_depth')
            ->from('#__hikashop_category')
            ->where('category_id = ' . $db->escape($id));
        $db->setQuery($query);
        $res = $db->loadAssoc();
        return $res;
    }

    /**
     * @param $artikelgruppen
     * @param $parent_id
     * @param $categoryDepth
     */
    private function importArtikelgruppen($artikelgruppen, $parent_id, $categoryDepth)
    {
        foreach ($artikelgruppen as $artikelgruppe) {
            $artikelgruppeCategory = $this->isProductCategory($artikelgruppe->attributes()->{"Name"}, $parent_id);

            if (!$artikelgruppeCategory) {
                $id = $this->createProductCategory($artikelgruppe->attributes()->{"Name"}, $artikelgruppe->attributes()->{"Beschreibung"}, $parent_id, $categoryDepth);

                $artikelgruppeCategory = $this->loadProductCategory($id);
            }

        }
    }

    /**
     * Checks if a product exists or not
     *
     * @param $sortennummer
     * @param null $parentID
     * @return mixed product_id or false
     */
    private function isProduct($sortennummer, $parentID = null)
    {
        //does the product exist?
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select($db->quoteName('product_id'))
            ->from($db->quoteName('#__hikashop_product'))
            ->where($db->quoteName('product_code') . '=' . $db->quote($sortennummer));
        if ($parentID) {
            $query->where($db->quoteName("product_parent_id") . "=" . $parentID);
        }
        $db->setQuery($query);
        $product = $db->loadAssoc();

        if (count($product) == 0) {
            return false;
        }

        return $product['product_id'];
    }

    /**
     * @param $name
     * @param $description
     * @param $sortennummer
     * @param $category
     * @param string $quantity
     * @param int $parentID
     * @param string $productType
     * @param int $published
     * @param null $taxId
     * @return mixed
     */
    private function createProduct($name, $description = '', $sortennummer, $category, $quantity = '-1', $parentID = 0, $productType = 'main', $published = 1, $taxId = null)
    {
        $time = time();

        $query = $this->db->getQuery(true);
        $query->insert('#__hikashop_product')
            ->set('product_parent_id =' . $parentID)
            ->set("product_name =" . $this->db->quote($name))
            ->set("product_description = " . $this->db->quote($description))
            ->set("product_quantity = " . $quantity)
            ->set("product_code = " . $this->db->quote($sortennummer))
            ->set("product_published = " . $published)
            ->set("product_created = " . $time)
            ->set("product_type = " . $this->db->quote($productType))
            ->set("product_alias = '" . JFilterOutput::stringURLSafe($name) . "'");
        if ($taxId) {
            $query->set("product_tax_id=" . $taxId);
        }

        $this->db->setQuery($query);
        $this->db->execute();
        $insertid = $this->db->insertid();

        if ($productType == 'main') {
            $this->setCategory($category, $insertid);
        }

        return $insertid;
    }

    /**
     * @param $name
     * @param $description
     * @param $productId
     * @param $category
     * @param string $quantity
     * @param int $parentID
     * @param string $productType
     * @param int $published
     * @param null $taxId
     * @internal param $sorte
     * @internal param $time
     */
    private function updateProduct($name, $description, $productId, $category, $quantity = '-1', $parentID = 0, $productType = 'main', $published = 1, $taxId = null)
    {
        $time = time();

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->update('#__hikashop_product')
            ->set('product_parent_id =' . $parentID)
            ->set("product_name =" . $db->quote($name))
            ->set("product_description = " . $db->quote($description))
            ->set("product_quantity = " . $quantity)
            //->set("product_code = " . $db->escape($sortennummer))
            ->set("product_published = " . $published)
            ->set("product_created = " . $time)
            ->set("product_type = " . $db->quote($productType))
            //->set("product_alias = '" . JFilterOutput::stringURLSafe($name) . "'")
            ->where("product_id = " . $productId);
        if ($taxId) {
            $query->set("product_tax_id=" . $taxId);
        }

        $db->setQuery($query);
        $db->execute();

        if ($productType == 'main') {
            $this->setCategory($category, $productId);
        }
    }

    /**
     * Checks if a category exists or not
     *
     * @param $category
     * @return mixed category_id or false
     */
    private function isCategory($category)
    {
        //does the category exist?
        $query = $this->db->getQuery(true);
        $query->select('category_id')
            ->from('#__hikashop_category')
            ->where('category_name=' . $this->db->quote($category));
        $this->db->setQuery($query);
        $product = $this->db->loadAssoc();

        if (count($product) == 0) {
            return false;
        }

        return $product['category_id'];
    }

    /**
     * @param $category
     * @param $insertid
     * @throws RuntimeException
     */
    private function setCategory($category, $insertid)
    {
        $categoryID = $this->isCategory($category);

        if (!$categoryID) {
            JLog::add('Category not found: ' . $category, JLog::ERROR);
            return;
        }

        $query = $this->db->getQuery(true);
        $query->select("*")
            ->from("#__hikashop_product_category")
            ->where("category_id=" . $this->db->escape($categoryID))
            ->where("product_id=" . $this->db->escape($insertid));
        $this->db->setQuery($query);
        if (count($this->db->loadAssoc()) == 0) {
            $query = $this->db->getQuery(true);
            $query->insert("#__hikashop_product_category")
                ->set("category_id=" . $this->db->escape($categoryID))
                ->set("product_id=" . $this->db->escape($insertid));
            $this->db->setQuery($query);
            $this->db->execute();
        }
    }

    /**
     * @param $sortennummer
     * @return array
     */
    private function getCharacteristicGroup($sortennummer)
    {
        $query = $this->db->getQuery(true);
        $query->select("characteristic_id")
            ->from("#__hikashop_characteristic")
            ->where("characteristic_alias='Variant" . $this->db->escape($sortennummer) . "'");
        $this->db->setQuery($query);
        $chGroup = $this->db->loadAssoc();

        if (!$chGroup) { //create a group for characteristic if not exist
            return array($this->createCharacteristic('', '', $sortennummer), true);
        }
        return array($chGroup['characteristic_id'], false);
    }

    /**
     * @param $name
     * @param $parent
     * @param null $sortennummer
     * @internal param $artikel
     * @return array
     */
    private function createCharacteristic($name, $parent, $sortennummer = null)
    {
        $query = $this->db->getQuery(true);
        $query->insert("#__hikashop_characteristic");
        if ($sortennummer === null) {
            $query->set("characteristic_parent_id=" . $parent)
                ->set("characteristic_value='" . $this->db->escape($name) . "'");
        } else {
            $query->set("characteristic_alias='Variant" . $this->db->escape($sortennummer) . "'")
                ->set("characteristic_value='Variante'");
        }
        $this->db->setQuery($query)
            ->execute();
        return $this->db->insertid();
    }

    /**
     * @param array() $values
     * @return JDatabaseQuery
     */
    private function setVariantRelation($values)
    {
        $query = $this->db->getQuery(true);
        $query->insert($this->db->quoteName("#__hikashop_variant"))
            ->columns("`variant_characteristic_id` , `variant_product_id`")
            ->values($values);
        $this->db->setQuery($query)
            ->execute();
        return $query;
    }

    /**
     * @param $mwst
     * @throws RuntimeException
     * @return category ID
     */
    private function getTaxId($mwst)
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName("c.category_id"))
            ->from($this->db->quoteName("#__hikashop_taxation", "a"))
            ->leftJoin($this->db->quoteName('#__hikashop_tax', 'b') . ' ON ' . $this->db->quoteName('a.tax_namekey') . '=' . $this->db->quoteName('b.tax_namekey'))
            ->leftJoin($this->db->quoteName("#__hikashop_category", 'c') . ' ON (' . $this->db->quoteName('a.category_namekey') . '=' . $this->db->quoteName('c.category_namekey') . ')')
            ->where($this->db->quoteName("b.tax_rate") . "=" . number_format($mwst, 5));
        $this->db->setQuery($query);
        $catId = $this->db->loadResult();

        if (!$catId) {
            JLog::add('MWST nicht gefunden, bitte erstellen: ' . $mwst, JLog::ERROR);
        }

        return $catId;
    }

    /**
     * @param $productCharacteristicID
     * @param $prices
     * @throws RuntimeException
     */
    private function createPrices($productCharacteristicID, $prices)
    {
        if (count($prices) > 0) {
            $query = $this->db->getQuery(true);

            $query->insert($this->db->quoteName("#__hikashop_price"))
                ->columns("price_currency_id, price_product_id, price_value, price_min_quantity");

            foreach ($prices as $price) {
                $query->values("1, " . $productCharacteristicID . "," . $price['price'] . "," . $price['minQuantity']);
            }
            $this->db->setQuery($query)
                ->execute();
        } else {
            throw new RuntimeException('createPrices failed because price array is empty');
        }
    }

    /**
     * @param $productCharacteristicID
     * @param $prices
     */
    private function updatePrices($productCharacteristicID, $prices)
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName("price_id") . ',' . $this->db->quoteName("price_value") . ',' . $this->db->quoteName("price_min_quantity"))
            ->from($this->db->quoteName("#__hikashop_price"))
            ->where($this->db->quoteName("price_product_id") . "=" . $productCharacteristicID);
        $this->db->setQuery($query);
        $currentPrices = $this->db->loadAssocList();

        foreach ($currentPrices as $ck => $currentPrice) {
            foreach ($prices as $k => $price) {
                if ($price['minQuantity'] == $currentPrice['price_min_quantity']) {
                    if (number_format($price['price'], 5) != $currentPrice['price_value']) {
                        //update price in DB and unset
                        $this->updatePrice($productCharacteristicID, $price);
                    }
                    unset($currentPrices[$ck]);
                    unset($prices[$k]);
                    break;
                }
            }
        }

        //still new prices here?
        if (count($prices) > 0) {
            $this->createPrices($productCharacteristicID, $prices);
        }

        //delete old prices if there are still
        if (count($currentPrices) > 0) {
            foreach ($currentPrices as $currentPrice) {
                $query = $this->db->getQuery(true);
                $query->delete($this->db->quoteName("#__hikashop_price"))
                    ->where($this->db->quoteName("price_value") . '=' . $currentPrice['price_id']);
                $this->db->setQuery($query)
                    ->execute();

            }
        }
    }

    /**
     * @param $brutto
     * @param $mwst
     * @return mixed
     *
     * N: Netto
     * B: Brutto
     * p: Mehrwertsteuersatz
     * N =  B / (p / 100 + 1)
     * B = N * (p  /100 + 1)
     * Mwst. = N * (p / 100)
     */
    private
    function getNetto($brutto, $mwst)
    {
        $brutto = str_replace(",", ".", $brutto);
        $netto = $brutto / ($mwst / 100 + 1);
        return $netto;
    }

    /**
     * @param $productCharacteristicID
     * @param $price
     */
    private
    function updatePrice($productCharacteristicID, $price)
    {
        $query = $this->db->getQuery(true);
        $query->update($this->db->quoteName("#__hikashop_price"))
            ->set($this->db->quoteName("price_currency_id") . "=1")
            ->set($this->db->quoteName("price_value") . "=" . $price['price'])
            ->where($this->db->quoteName("price_min_quantity") . "=" . $price['minQuantity'])
            ->where($this->db->quoteName("price_product_id") . "=" . $productCharacteristicID);
        //echo $price;
        $this->db->setQuery($query)
            ->execute();
    }

    /**
     * @param $pictures
     * @param $sorteName
     * @param $productId
     */
    private function setPictures($pictures, $sorteName, $productId)
    {
        if (!empty($pictures) && $pictures->count() > 0) {
            $query = $this->db->getQuery(true);
            $query->insert($this->db->quoteName('#__hikashop_file'))
                ->columns(
                    $this->db->quoteName('file_name') . ', ' .
                    $this->db->quoteName('file_description') . ', ' .
                    $this->db->quoteName('file_path') . ', ' .
                    $this->db->quoteName('file_type') . ', ' .
                    $this->db->quoteName('file_ref_id') . ', ' .
                    $this->db->quoteName('file_ordering'));
            foreach ($pictures as $picture) {
                $pictureDesc = (string)$picture->children()->{'Beschreibung'}[0];

                $query->values(
                    $this->db->quote($sorteName) . ', ' .
                    $this->db->quote(empty($pictureDesc) ? 'Picture of ' . $sorteName : $pictureDesc) . ', ' .
                    $this->db->quote((string)$picture->children()->{'Datei'}[0]) . ', ' .
                    $this->db->quote('product') . ', ' .
                    $this->db->quote($productId) . ', ' .
                    $this->db->quote((int)$picture->attributes()->{'NR'}[0]));
            }

            $this->db->setQuery($query)
                ->execute();
        }
    }

    /**
     * @param $file_id
     * @param $picture
     * @param $sorteName
     */
    private function updatePicture($file_id, $picture, $sorteName)
    {
        $pictureDesc = (string)$picture->children()->{'Beschreibung'}[0];

        $query = $this->db->getQuery(true);
        $query->update($this->db->quoteName('#__hikashop_file'))
            ->set($this->db->quoteName('file_name') . '=' . $this->db->quote($sorteName))
            ->set($this->db->quoteName('file_description') . '= ' . $this->db->quote(empty($pictureDesc) ? 'Picture of ' . $sorteName : $pictureDesc))
            ->set($this->db->quoteName('file_path') . '=' . (string)$picture->children()->{'Datei'}[0])
            ->set($this->db->quoteName('file_ordering') . '=' . (int)$picture->attributes()->{'NR'}[0])
            ->where($this->db->quoteName('file_id') . '=' . $file_id);
        $this->db->setQuery($query)
            ->execute();
    }

    /**
     * @param $pictures
     * @param $sorteName
     * @param $productId
     */
    private function updatePictures($pictures, $sorteName, $productId)
    {
        //get picture for the sort
        $currentPictures = $this->getPictures($productId);

        if (count($currentPictures) > 0) {
            foreach ($currentPictures as $ck => $currentPicture) {
                foreach ($pictures as $k => $picture) {
                    if ((string)$picture->children()->{'Datei'}[0] == $currentPicture['file_path']) {
                        if ((int)$picture->attributes()->{'NR'}[0] != $currentPicture['file_ordering']) {
                            //update price in DB and unset
                            $this->updatePicture($currentPicture['file_id'], $picture, $sorteName);
                        }
                        unset($currentPictures[$ck]);
                        unset($picture->{0});
                        break;
                    }
                }
            }
        }

        //still new prices here?
        if (count($pictures) > 0) {
            $this->setPictures($pictures, $sorteName, $productId);
        }

        //delete old prices if there are still
        if (count($currentPictures) > 0) {
            foreach ($currentPictures as $currentPicture) {
                $query = $this->db->getQuery(true);
                $query->delete($this->db->quoteName("#__hikashop_file"))
                    ->where($this->db->quoteName('file_id') . '=' . $currentPicture['file_id']);
                $this->db->setQuery($query)
                    ->execute();
            }
        }
    }

    /**
     * @param $productId
     * @return mixed
     */
    private function getPictures($productId)
    {
        $query = $this->db->getQuery(true);
        $query->select(
            $this->db->quoteName('file_id') . ', ' .
            $this->db->quoteName('file_path') . ', ' .
            $this->db->quoteName('file_ordering'))
            ->from($this->db->quoteName('#__hikashop_file'))
            ->where($this->db->quoteName('file_ref_id') . '=' . $productId);
        $this->db->setQuery($query);
        return $this->db->loadAssocList();
    }

    private function setCostumFields($productId, $artikelAttributes)
    {
        $query = $this->db->getQuery(true);
        $query->select(
            $this->db->quoteName('field_id') . ',' .
            $this->db->quoteName('field_realname') . ',' .
            $this->db->quoteName('field_namekey') . ',' .
            $this->db->quoteName('field_value'))
            ->from($this->db->quoteName('#__hikashop_field'))
            ->where($this->db->quoteName('field_table') . '=' . $this->db->quote('product'));
        $this->db->setQuery($query);
        $costumFields = $this->db->loadAssocList();

        foreach ($artikelAttributes as $k => $artikelAttribute) {
            $artikelAttribute = explode("|", $artikelAttribute);
            foreach ($costumFields as $kk => $costumField) {
                if ($artikelAttribute[0] == $costumField['field_realname']) {
                    $fieldValues = explode("\n", $costumField['field_value']);
                    foreach ($fieldValues as $fieldValue) {
                        $fieldValue = substr($fieldValue, strpos($fieldValue, '::') + 2, (strrpos($fieldValue, '::') - strpos($fieldValue, '::') - 2));
                        if ($artikelAttribute[1] == $fieldValue) {
                            //set attribute to product
                            $query = $this->db->getQuery(true);
                            $query->update($this->db->quoteName('#__hikashop_product'))
                                ->set($this->db->quoteName($costumField['field_namekey']) . '=' . $this->db->quote($artikelAttribute[1]))
                                ->where($this->db->quoteName('product_id') . '=' . $productId);
                            $this->db->setQuery($query)
                                ->execute();
                            continue;
                        }
                    }
                    continue;
                }
            }
        }
    }

    private function createRelations()
    {
        $sorten = $this->xml->xpath("Sortendaten")[0];

        foreach ($sorten as $sorte) {
            $sortennummer = 's' . $sorte->children()->{'Kennung'} . $sorte->children()->{'Sortennummer'};

            $ersatzSorten = $sorte->children()->{'Ersatzsorten'}[0];

            if (!empty($ersatzSorten)) {
                $productId = $this->isProduct($sortennummer);
                if ($productId) {
                    foreach ($ersatzSorten as $ersatzSorte) {
                        $ersatzSortennummer = 's' . $sorte->children()->{'Kennung'} . (string)$ersatzSorte;
                        $ersatzSorteID = $this->isProduct($ersatzSortennummer);
                        if ($ersatzSorteID) {
                            $query = $this->db->getQuery(true);
                            $query->select($this->db->quoteName('product_id'))
                                ->from($this->db->quoteName('#__hikashop_product_related'))
                                ->where($this->db->quoteName('product_id') . '=' . $productId)
                                ->where($this->db->quoteName('product_related_id') . '=' . $ersatzSorteID);
                            $this->db->setQuery($query);
                            if ($this->db->getNumRows() <= 0) {
                                $query = $this->db->getQuery(true);
                                $query->insert($this->db->quoteName('#__hikashop_product_related'))
                                    ->columns($this->db->quoteName('product_related_id') . ',' . $this->db->quoteName('product_id'))
                                    ->values($ersatzSorteID . ',' . $productId);
                                $this->db->setQuery($query)
                                    ->execute();
                            }
                        }
                    }
                }
            }

        }
    }
}