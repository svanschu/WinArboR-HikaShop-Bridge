<?php
/**
 * @package    HikaShop for Joomla!
 * @version    2.3.0
 * @author    hikashop.com
 * @copyright    (C) 2010-2014 HIKARI SOFTWARE. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
while (ob_get_level() > 1)
    ob_end_clean();

/*$config =& hikashop_config();
$format = $config->get('export_format','csv');
$separator = $config->get('csv_separator',';');
$force_quote = $config->get('csv_force_quote',1);

$export = hikashop_get('helper.spreadsheet');
$export->init($format, 'hikashop_export', $separator, $force_quote);*/
jimport('joomla.filesystem.folder');

$exportFolder = JPATH_ROOT . '/hikaExport/Bestellungen';
if (!JFolder::exists($exportFolder)) {
    if (!JFolder::create($exportFolder)) {
        JLog::add("Can't create folder", JLog::ERROR);
    }
}

$application = JFactory::$application;

if (!empty($this->orders)) {
    foreach ($this->orders as $order) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Winabor></Winabor>');

        $orderDate = $order->order_created;
        if ($order->order_created < $order->order_modified) {
            $orderDate = $order->order_modified;
        }

        $ident = $xml->addChild('IDENT');
        $ident->addChild('DATUM', JDate::getInstance($orderDate)->format('d.m.Y H:i:s'));
        $ident->addChild('ORDERID', $order->order_id);
        $cms = $ident->addChild('CMS');
        $cms->addChild('USERID', $order->user_id);
        $cms->addChild('DATE', JDate::getInstance($order->user_created)->format('d.m.Y H:i:s'));

        /** [billing_address_id] => 1
         * [billing_address_user_id] => 0
         * [billing_address_title] => Mr
         * [billing_address_firstname] => Muster
         * [billing_address_middle_name] =>
         * [billing_address_lastname] => Mann
         * [billing_address_company] =>
         * [billing_address_street] => Musterstr
         * [billing_address_street2] =>
         * [billing_address_post_code] => 76929
         * [billing_address_city] => Musterstadt
         * [billing_address_telephone] => 07231
         * [billing_address_telephone2] =>
         * [billing_address_fax] =>
         * [billing_address_state] => Baden-Württemberg
         * [billing_address_country] => Deutschland
         * [billing_address_published] => 1
         * [billing_address_vat] =>
         * [billing_address_default] => 0
         */
        $formular = $xml->addChild('FORMULAR');
        $formular->addChild('FN', $order->billing_address_company)->addAttribute('TITLE', 'Firma');
        $formular->addChild('VN', $order->billing_address_firstname)->addAttribute('TITLE', 'Vorname');
        $formular->addChild('NA1', $order->billing_address_lastname)->addAttribute('TITLE', 'Name 1');
        //$formular->addChild('NA1', $order->billing_address_middle_name)->addAttribute('TITLE', 'Name 1');
        //$formular->addChild('NA2', $order->billing_address_lastname)->addAttribute('TITLE', 'Name 2');
        $formular->addChild('ST', $order->billing_address_street)->addAttribute('TITLE', 'Strasse');
        $formular->addChild('PL', $order->billing_address_post_code)->addAttribute('TITLE', 'PLZ');
        $formular->addChild('OR', $order->billing_address_city)->addAttribute('TITLE', 'Ort');
        $formular->addChild('LA', $order->billing_address_country)->addAttribute('TITLE', 'Land');
        $formular->addChild('EM', $order->user_email)->addAttribute('TITLE', 'E-Mail');
        $formular->addChild('TE', $order->billing_address_telephone)->addAttribute('TITLE', 'Telefon');
        $formular->addChild('FA', $order->billing_address_fax)->addAttribute('TITLE', 'Fax');

        /** [shipping_address_id] => 2
         * [shipping_address_user_id] => 0
         * [shipping_address_title] => Mr
         * [shipping_address_firstname] => Muster
         * [shipping_address_middle_name] =>
         * [shipping_address_lastname] => Mustermann
         * [shipping_address_company] =>
         * [shipping_address_street] => Musterstr 2
         * [shipping_address_street2] =>
         * [shipping_address_post_code] => 78181
         * [shipping_address_city] => Musterstadt
         * [shipping_address_telephone] => 567890
         * [shipping_address_telephone2] =>
         * [shipping_address_fax] =>
         * [shipping_address_state] => Baden-Württemberg
         * [shipping_address_country] => Deutschland
         * [shipping_address_published] => 1
         * [shipping_address_vat] =>
         * [shipping_address_default] => 0
         */
        $formular->addChild('L_NA1', $order->shipping_address_firstname)->addAttribute('TITLE', 'Name 1');
        $formular->addChild('L_NA2', $order->shipping_address_lastname)->addAttribute('TITLE', 'Name 2');
        $formular->addChild('L_ST', $order->shipping_address_street)->addAttribute('TITLE', 'Strasse');
        $formular->addChild('L_PL', $order->shipping_address_post_code)->addAttribute('TITLE', 'PLZ');
        $formular->addChild('L_OR', $order->shipping_address_city)->addAttribute('TITLE', 'Ort');
        $formular->addChild('L_LA', $order->shipping_address_country)->addAttribute('TITLE', 'Land');

        /**
         *  [order_id] => 1
         * [order_billing_address_id] => 1
         * [order_shipping_address_id] => 2
         * [order_user_id] => 1
         * [order_status] => created
         * [order_type] => sale
         * [order_number] => B1
         * [order_created] => 1399364520
         * [order_modified] => 1399364974
         * [order_invoice_id] => 0
         * [order_invoice_number] =>
         * [order_invoice_created] => 0
         * [order_currency_id] => 1
         * [order_full_price] => 20.00000
         * [order_tax_info] => b:0;
         * [order_discount_code] =>
         * [order_discount_price] => 0.00000
         * [order_discount_tax] => 0.00000
         * [order_payment_id] =>
         * [order_payment_method] =>
         * [order_payment_price] => 0.00000
         * [order_payment_params] => b:0;
         * [order_shipping_id] =>
         * [order_shipping_method] =>
         * [order_shipping_price] => 0.00000
         * [order_shipping_tax] => 0.00000
         * [order_shipping_params] => b:0;
         * [order_partner_id] => 0
         * [order_partner_price] => 0.00000
         * [order_partner_paid] => 0
         * [order_partner_currency_id] => 0
         * [order_ip] =>
         */
        $LF_TYP = $formular->addChild('LF_TYP', $order->order_shipping_method ? $order->order_shipping_method : 'Versand');
        $LF_TYP->addAttribute('TITLE', 'Lieferung');
        $LF_TYP->addAttribute('VALUE', 'VS');

        $Z_ZA = $formular->addChild('Z_ZA', $order->order_payment_method ? $order->order_payment_method : 'Rechnung');
        $Z_ZA->addAttribute('TITLE', 'Zahlungsart');
        $Z_ZA->addAttribute('VALUE', 'RE');

        /**
         * [order_full_price] => 23.20000
         * [order_tax_info] => a:1:{s:5:"alles";O:8:"stdClass":3:{s:11:"tax_namekey";s:5:"alles";s:8:"tax_rate";s:7:"0.16000";s:10:"tax_amount";d:3.2000000000000002;}}
         */
        $bestellung = $xml->addChild('BESTELLUNG');
        $bestellung->addChild('ORDERTYP', 'ORDER');
        $bestellung->addChild('CURRENCY', 'EUR');
        $bestellung->addChild('INKLUSIV', 'TRUE');
        $bestellung->addChild('MWST', 'TRUE');

        $taxInfo = unserialize($order->order_tax_info);
        $mwst = 0;
        foreach ($taxInfo as $tax) {
            $mwst += $tax->tax_amount;
        }
        $bestellung->addChild('NETTO_COMPLETE', ($order->order_full_price - $mwst));
        $bestellung->addChild('MWST_COMPLETE', $mwst);
        $bestellung->addChild('BRUTTO_COMPLETE', $order->order_full_price);

        $orderList = $bestellung->addChild('ORDERLIST');
        foreach ($order->products as $product) {
            $item = $orderList->addChild('ITEM');
            /**
             *  [order_product_id] => 3
             * [order_id] => 3
             * [product_id] => 3
             * [order_product_quantity] => 1
             * [order_product_name] => rose"<span class="hikashop_product_variant_subname">:  3L</span>"
             * [ [order_product_code] => cf4567_2
             * [order_product_price] => 20.00000
             * [order_product_tax] => 3.20000
             * [order_product_tax_info] => a:1:{i:0;O:8:"stdClass":14:{s:11:"taxation_id";s:1:"1";s:12:"zone_namekey";s:18:"country_Germany_81";s:16:"category_namekey";s:11:"default_tax";s:11:"tax_namekey";s:5:"alles";s:18:"taxation_published";s:1:"1";s:13:"taxation_type";s:0:"";s:15:"taxation_access";s:3:"all";s:19:"taxation_cumulative";s:1:"0";s:18:"taxation_post_code";s:0:"";s:19:"taxation_date_start";s:1:"0";s:17:"taxation_date_end";s:1:"0";s:8:"tax_rate";s:7:"0.16000";s:9:"zone_type";s:7:"country";s:10:"tax_amount";d:3.2000000000000002;}}
             * [order_product_options] => a:2:{i:0;O:8:"stdClass":8:{s:25:"variant_characteristic_id";s:1:"2";s:18:"variant_product_id";s:1:"3";s:8:"ordering";s:1:"0";s:17:"characteristic_id";s:1:"2";s:24:"characteristic_parent_id";s:1:"3";s:20:"characteristic_value";s:2:"3L";s:20:"characteristic_alias";s:0:"";s:23:"characteristic_ordering";s:1:"0";}s:14:"Topf/Container";s:2:"3L";}
             * [order_product_option_parent_id] => 0
             * [order_product_wishlist_id] => 0
             * [order_product_shipping_id] => 1@0
             * [order_product_shipping_method] => manual
             * [order_product_shipping_price] => 0.00000
             * [order_product_shipping_tax] => 0.00000
             * [order_product_shipping_params] =>
             */
            $taxInfo = unserialize($product->order_product_tax_info);

            $kennung = substr($product->order_product_code, 0, 1);
            $productCode = substr($product->order_product_code, 1);

            $item->addChild('ID', $product->order_product_id);
            $item->addChild('COUNT', $product->order_product_quantity);
            $item->addChild('TAX', $taxInfo[0]->tax_rate * 100);
            //$item->addChild('TAXTYP', $product->order_product_id);
            $item->addChild('NETTO', $product->order_product_price);
            $item->addChild('MWST', $product->order_product_tax);
            $item->addChild('KENNUNG', $kennung);
            $item->addChild('ARTIKELNUMMER', $productCode);
            $item->addChild('BEZEICHNUNG1', strip_tags($product->order_product_name));
        }


        $filename = $exportFolder . '/USER_' . $order->user_id . '_ORDER_' . $order->order_id . '_' . JDate::getInstance($orderDate)->format('dmYHis') . '.xml';
        if (!JFile::exists($filename)) {
        $xml->saveXML($filename);
        } else {
            $application->enqueueMessage('File already exists: '. $filename, 'warning');
        }
        //print_r($xml);
        //echo '<p>';
        //print_r($order);
        //echo '<p>';
    }
    //die();
}

$application->enqueueMessage('Orders exportet succesfully');
$application->redirect('index.php?option=com_hikashop&ctrl=order');

exit;