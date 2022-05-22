<?php

/* Copyright (C) 2015       Abbes Bahfir                <bafbes@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   produit     Module cardmodifdemo
 * 	\brief      Module to manage catalog of predefined products
 * 	\file       htdocs/core/modules/modopensilog.class.php
 * 	\ingroup    produit
 * 	\brief      File to describe module to manage catalog of predefined products
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * 	Class descriptor of cardmodifdemo module
 */
class modcardmodifdemo extends DolibarrModules {

    /**
     *   Constructor. Define names, constants, directories, boxes, permissions
     *
     *   @param      DoliDB		$db      Database handler
     */
    function __construct($db) {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 53000;

        $this->rights_class = 'cardmodifdemo';
        $this->family = "Ab1 Consulting";
        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "module de la société cardmodifdemo";

        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '1.0';

        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;

        // Data directories to create when module is enabled
        $this->dirs = array();

        // Dependencies
        $this->depends = array('modcardmodifdemo');
        $this->requiredby = array();
        $this->phpmin = array(5, 3);

        // Config pages
        $this->need_dolibarr_version = array(3, 2);
        $this->langfiles = array('cardmodifdemo@cardmodifdemo');
        $this->const = array();
        // Main menu entries
        $this->module_parts = array(
            'models' => 1,
            'tpl' => 1,
            'hooks' => array('propalcard'),
            'substitutions' => 1,
//            'triggers' => 1,
//            'js' => 'cardmodifdemo/js/cardmodifdemo.js.php',

        );
        $this->config_page_url = array(
            'setup.php@cardmodifdemo'
        );
    }

    /**
     *      Function called when module is enabled.
     *      The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *      It also creates data directories
     *
     *      @param      string	$options    Options when enabling module ('', 'newboxdefonly', 'noboxes')
     *      @return     int             	1 if OK, 0 if KO
     */
    function init($options = '') {
        global $langs;
        $sql = array();
        $result = $this->load_tables();
        return $this->_init($sql, $options);
    }

    /**
     * 		Create tables, keys and data required by module
     * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     * 		and create data commands must be stored in directory /mymodule/sql/
     * 		This function is called by this->init.
     *
     * 		@return		int		<=0 if KO, >0 if OK
     */
    function load_tables() {
        return 1;
    }

    /**
     *      Function called when module is disabled.
     *      Remove from database constants, boxes and permissions from Dolibarr database.
     *      Data directories are not deleted
     *
     *      @param      string	$options    Options when enabling module ('', 'newboxdefonly', 'noboxes')
     *      @return     int             	1 if OK, 0 if KO
     */
    function remove($options = '') {
        $sql = array();
        return $this->_remove($sql, $options);
    }

}
