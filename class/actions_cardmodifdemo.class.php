<?php
/* Copyright (C) 2009-2018	Regis Houssin	<regis.houssin@inodbox.com>
 * Copyright (C) 2011		Herve Prot		<herve.prot@symeos.com>
 * Copyright (C) 2014		Philippe Grand	<philippe.grand@atoo-net.com>
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *    \file       htdocs/cardmodifdemo/actions_Carddemo.class.php
 *    \ingroup    cardmodifdemo
 *    \brief      File Class cardmodifdemo
 */


/**
 *    Class Actions of the module cardmodifdemo
 */
class ActionsCardmodifdemo
{
	/** @var DoliDB */
	var $db;
	/** @var DaoCarddemo */

	// For Hookmanager return
	var $resprints;
	var $results = array();


	/**
	 *    Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 *    Return action of hook
	 * @param object            Linked object
	 */
	public function init($parameters = false, &$object, &$action = '')
	{
		if(get_class($object)=='Propal'){
			global $db,$conf,$user,$langs,$error, $id ,$ref,$socid,$action,$cancel,$origin,$originid,$confirm ,$lineid,$contactid,$projectid,$hidedetails,$hidedesc,
				   $hideref,$parameters,$NBLINES,$object,$extrafields,$usercanread,$usercancreate,$usercandelete,$usercanclose,$usercanvalidate,
				   $usercansend,$usercancreateorder,$usercancreateinvoice,$usercancreatecontract,$usercancreateintervention,$usercancreatepurchaseorder,
				   $permissionnote,$permissiondellink,$permissiontoedit,$form,$formfile,$formpropal,$formmargin,$formproject,$title,$help_url,$now;
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formpropal.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';
			require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
			require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/modules/propale/modules_propale.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/propal.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/signature.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
			if (!empty($conf->projet->enabled)) {
				require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
				require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
			}

			if (!empty($conf->variants->enabled)) {
				require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination.class.php';
			}
			require_once DOL_DOCUMENT_ROOT . '/core/class/abstractactions.class.php';
			dol_include_once('/cardmodifdemo/class/actions.class.php');

			require_once DOL_DOCUMENT_ROOT . '/core/class/security.class.php';

// Load translation files required by the page
			$langs->loadLangs(array('companies', 'propal', 'compta', 'bills', 'orders', 'products', 'deliveries', 'sendings', 'other'));
			if (!empty($conf->incoterm->enabled)) {
				$langs->load('incoterm');
			}
			if (!empty($conf->margin->enabled)) {
				$langs->load('margins');
			}

			$error = 0;

			$id = GETPOST('id', 'int');
			$ref = GETPOST('ref', 'alpha');
			$socid = GETPOST('socid', 'int');
			$action = GETPOST('action', 'aZ09');
			$cancel = GETPOST('cancel', 'alpha');
			$origin = GETPOST('origin', 'alpha');
			$originid = GETPOST('originid', 'int');
			$confirm = GETPOST('confirm', 'alpha');
			$lineid = GETPOST('lineid', 'int');
			$contactid = GETPOST('contactid', 'int');
			$projectid = GETPOST('projectid', 'int');

// PDF
			$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
			$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
			$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));
			$parameters = array('socid' => $socid);


// Nombre de ligne pour choix de produit/service predefinis
			$NBLINES = 4;

			$object = new Propal($db);
			$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
			$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
			if ($id > 0 || !empty($ref)) {
				$ret = $object->fetch($id, $ref);
				if ($ret > 0) {
					$ret = $object->fetch_thirdparty();
				}
				if ($ret <= 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$action = '';
				}
			}

			$usercanread = $user->rights->propal->lire;
			$usercancreate = $user->rights->propal->creer;
			$usercandelete = $user->rights->propal->supprimer;

			$usercanclose = ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $usercancreate) || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->propal->propal_advance->close)));
			$usercanvalidate = ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && $usercancreate) || (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->propal->propal_advance->validate)));
			$usercansend = (empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->propal->propal_advance->send);

			$usercancreateorder = $user->rights->commande->creer;
			$usercancreateinvoice = $user->rights->facture->creer;
			$usercancreatecontract = $user->rights->contrat->creer;
			$usercancreateintervention = $user->rights->ficheinter->creer;
			$usercancreatepurchaseorder = ($user->rights->fournisseur->commande->creer || $user->rights->supplier_order->creer);

			$permissionnote = $usercancreate; // Used by the include of actions_setnotes.inc.php
			$permissiondellink = $usercancreate; // Used by the include of actions_dellink.inc.php
			$permissiontoedit = $usercancreate; // Used by the include of actions_lineupdown.inc.php

			$form = new Form($db);
			$formfile = new FormFile($db);
			$formpropal = new FormPropal($db);
			$formmargin = new FormMargin($db);
			if (!empty($conf->projet->enabled)) {
				$formproject = new FormProjets($db);
			}

			$title = $langs->trans('Proposal')." - ".$langs->trans('Card');
			$help_url = 'EN:Commercial_Proposals|FR:Proposition_commerciale|ES:Presupuestos|DE:Modul_Angebote';

			$now = dol_now();

			return 1;
		}

	return 0;
	}
}
