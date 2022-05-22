<?php

include_once DOL_DOCUMENT_ROOT.'/core/class/abstractactions.class.php';

class Actions extends AbstractActions
{
	function propal_card_actions()
	{
		global $db, $user, $langs, $conf, $object, $action, $error, $socid, $form, $contactid,
			   $hookmanager, $formproject, $usercanread, $formfile, $text, $lineid, $mysoc,
			   $usercancreate, $usercandelete, $usercanvalidate, $usercancreateinvoice,
			   $usercancreateintervention, $usercancreatepurchaseorder, $usercancreatecontract,
			   $usercancreateorder, $usercanclose, $usercansend, $maxlength, $formmargin, $now;
		// Add new proposal
		if ($action == 'create') {
			$currency_code = $conf->currency;

			print load_fiche_titre($langs->trans("NewProp"), '', 'propal');

			$soc = new Societe($db);
			if ($socid > 0) {
				$res = $soc->fetch($socid);
			}

			// Load objectsrc
			if (!empty($origin) && !empty($originid)) {
				// Parse element/subelement (ex: project_task)
				$element = $subelement = $origin;
				$regs = array();
				if (preg_match('/^([^_]+)_([^_]+)/i', $origin, $regs)) {
					$element = $regs[1];
					$subelement = $regs[2];
				}

				if ($element == 'project') {
					$projectid = $originid;
				}
				else {
					// For compatibility
					if ($element == 'order' || $element == 'commande') {
						$element = $subelement = 'commande';
					}
					if ($element == 'propal') {
						$element = 'comm/propal';
						$subelement = 'propal';
					}
					if ($element == 'contract') {
						$element = $subelement = 'contrat';
					}
					if ($element == 'shipping') {
						$element = $subelement = 'expedition';
					}

					dol_include_once('/' . $element . '/class/' . $subelement . '.class.php');

					$classname = ucfirst($subelement);
					$objectsrc = new $classname($db);
					$objectsrc->fetch($originid);
					if (empty($objectsrc->lines) && method_exists($objectsrc, 'fetch_lines')) {
						$objectsrc->fetch_lines();
					}
					$objectsrc->fetch_thirdparty();

					$projectid = (!empty($objectsrc->fk_project) ? $objectsrc->fk_project : 0);
					$ref_client = (!empty($objectsrc->ref_client) ? $objectsrc->ref_client : '');

					$soc = $objectsrc->thirdparty;

					$cond_reglement_id = (!empty($objectsrc->cond_reglement_id) ? $objectsrc->cond_reglement_id : (!empty($soc->cond_reglement_id) ? $soc->cond_reglement_id : 0)); // TODO maybe add default value option
					$mode_reglement_id = (!empty($objectsrc->mode_reglement_id) ? $objectsrc->mode_reglement_id : (!empty($soc->mode_reglement_id) ? $soc->mode_reglement_id : 0));
					$remise_percent = (!empty($objectsrc->remise_percent) ? $objectsrc->remise_percent : (!empty($soc->remise_percent) ? $soc->remise_percent : 0));
					$remise_absolue = (!empty($objectsrc->remise_absolue) ? $objectsrc->remise_absolue : (!empty($soc->remise_absolue) ? $soc->remise_absolue : 0));
					$warehouse_id = (!empty($objectsrc->warehouse_id) ? $objectsrc->warehouse_id : (!empty($soc->warehouse_id) ? $soc->warehouse_id : 0));
					$dateinvoice = (empty($dateinvoice) ? (empty($conf->global->MAIN_AUTOFILL_DATE) ? -1 : '') : $dateinvoice);

					// Replicate extrafields
					$objectsrc->fetch_optionals();
					$object->array_options = $objectsrc->array_options;

					if (!empty($conf->multicurrency->enabled)) {
						if (!empty($objectsrc->multicurrency_code)) {
							$currency_code = $objectsrc->multicurrency_code;
						}
						if (!empty($conf->global->MULTICURRENCY_USE_ORIGIN_TX) && !empty($objectsrc->multicurrency_tx)) {
							$currency_tx = $objectsrc->multicurrency_tx;
						}
					}
				}
			}
			else {
				if (!empty($conf->multicurrency->enabled) && !empty($soc->multicurrency_code)) {
					$currency_code = $soc->multicurrency_code;
				}
			}

			//Warehouse default if null
			if ($soc->fk_warehouse > 0) {
				$warehouse_id = $soc->fk_warehouse;
			}
			if (!empty($conf->stock->enabled) && empty($warehouse_id) && !empty($conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER)) {
				if (empty($object->warehouse_id) && !empty($conf->global->MAIN_DEFAULT_WAREHOUSE)) {
					$warehouse_id = $conf->global->MAIN_DEFAULT_WAREHOUSE;
				}
				if (empty($object->warehouse_id) && !empty($conf->global->MAIN_DEFAULT_WAREHOUSE_USER)) {
					$warehouse_id = $user->fk_warehouse;
				}
			}

			$object = new Propal($db);

			print '<form name="addprop" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="action" value="add">';
			if ($origin != 'project' && $originid) {
				print '<input type="hidden" name="origin" value="' . $origin . '">';
				print '<input type="hidden" name="originid" value="' . $originid . '">';
			}
			elseif ($origin == 'project' && !empty($projectid)) {
				print '<input type="hidden" name="projectid" value="' . $projectid . '">';
			}

			print dol_get_fiche_head();

			print '<table class="border centpercent">';

			// Reference
			print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('Ref') . '</td><td>' . $langs->trans("Draft") . '</td></tr>';

			// Ref customer
			print '<tr><td>' . $langs->trans('RefCustomer') . '</td><td>';
			print '<input type="text" name="ref_client" value="' . GETPOST('ref_client') . '"></td>';
			print '</tr>';

			// Third party
			print '<tr>';
			print '<td class="fieldrequired">' . $langs->trans('Customer') . '</td>';
			if ($socid > 0) {
				print '<td>';
				print $soc->getNomUrl(1);
				print '<input type="hidden" name="socid" value="' . $soc->id . '">';
				print '</td>';
				if (!empty($conf->global->SOCIETE_ASK_FOR_SHIPPING_METHOD) && !empty($soc->shipping_method_id)) {
					$shipping_method_id = $soc->shipping_method_id;
				}
				//$warehouse_id       = $soc->warehouse_id;
			}
			else {
				print '<td>';
				print img_picto('', 'company') . $form->select_company('', 'socid', '(s.client = 1 OR s.client = 2 OR s.client = 3) AND status=1', 'SelectThirdParty', 0, 0, null, 0, 'minwidth300 maxwidth500 widthcentpercentminusxx');
				// reload page to retrieve customer informations
				if (empty($conf->global->RELOAD_PAGE_ON_CUSTOMER_CHANGE_DISABLED)) {
					print '<script type="text/javascript">
			$(document).ready(function() {
				$("#socid").change(function() {
					console.log("We have changed the company - Reload page");
					var socid = $(this).val();
					// reload page
					$("input[name=action]").val("create");
					$("form[name=addprop]").submit();
				});
			});
			</script>';
				}
				print ' <a href="' . DOL_URL_ROOT . '/societe/card.php?action=create&client=3&fournisseur=0&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create') . '"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans("AddThirdParty") . '"></span></a>';
				print '</td>';
			}
			print '</tr>' . "\n";

			if ($socid > 0) {
				// Contacts (ask contact only if thirdparty already defined).
				print "<tr><td>" . $langs->trans("DefaultContact") . '</td><td>';
				print img_picto('', 'contact');
				print $form->selectcontacts($soc->id, $contactid, 'contactid', 1, '', '', 0, 'minwidth300');
				print '</td></tr>';

				// Third party discounts info line
				print '<tr><td>' . $langs->trans('Discounts') . '</td><td>';

				$absolute_discount = $soc->getAvailableDiscounts();

				$thirdparty = $soc;
				$discount_type = 0;
				$backtopage = urlencode($_SERVER["PHP_SELF"] . '?socid=' . $thirdparty->id . '&action=' . $action . '&origin=' . GETPOST('origin') . '&originid=' . GETPOST('originid'));
				include DOL_DOCUMENT_ROOT . '/core/tpl/object_discounts.tpl.php';
				print '</td></tr>';
			}

			// Date
			print '<tr><td class="fieldrequired">' . $langs->trans('DatePropal') . '</td><td>';
			print $form->selectDate('', '', '', '', '', "addprop", 1, 1);
			print '</td></tr>';

			// Validaty duration
			print '<tr><td class="fieldrequired">' . $langs->trans("ValidityDuration") . '</td><td>' . img_picto('', 'clock') . '&ensp;<input name="duree_validite" class="width50" value="' . (GETPOSTISSET('duree_validite') ? GETPOST('duree_validite', 'alphanohtml') : $conf->global->PROPALE_VALIDITY_DURATION) . '"> ' . $langs->trans("days") . '</td></tr>';

			// Terms of payment
			print '<tr><td class="nowrap">' . $langs->trans('PaymentConditionsShort') . '</td><td>';
			print img_picto('', 'paiment');
			$form->select_conditions_paiements((GETPOSTISSET('cond_reglement_id') ? GETPOST('cond_reglement_id', 'int') : $soc->cond_reglement_id), 'cond_reglement_id', -1, 1);
			print '</td></tr>';

			// Mode of payment
			print '<tr><td>' . $langs->trans('PaymentMode') . '</td><td>';
			print img_picto('', 'bank') . '&ensp;';
			$form->select_types_paiements((GETPOSTISSET('mode_reglement_id') ? GETPOST('mode_reglement_id', 'int') : $soc->mode_reglement_id), 'mode_reglement_id', 'CRDT', 0, 1, 0, 0, 1, 'maxwidth200 widthcentpercentminusx');
			print '</td></tr>';

			// Bank Account
			if (!empty($conf->global->BANK_ASK_PAYMENT_BANK_DURING_PROPOSAL) && !empty($conf->banque->enabled)) {
				print '<tr><td>' . $langs->trans('BankAccount') . '</td><td>';
				print img_picto('', 'bank_account', 'class="pictofixedwidth"') . $form->select_comptes($soc->fk_account, 'fk_account', 0, '', 1, '', 0, 'maxwidth200 widthcentpercentminusx', 1);
				print '</td></tr>';
			}

			// Source / Channel - What trigger creation
			print '<tr><td>' . $langs->trans('Source') . '</td><td>';
			print img_picto('', 'question', 'class="pictofixedwidth"');
			$form->selectInputReason('', 'demand_reason_id', "SRC_PROP", 1, 'maxwidth200 widthcentpercentminusx');
			print '</td></tr>';

			// Delivery delay
			print '<tr class="fielddeliverydelay"><td>' . $langs->trans('AvailabilityPeriod');
			if (!empty($conf->commande->enabled)) {
				print ' (' . $langs->trans('AfterOrder') . ')';
			}
			print '</td><td>';
			print img_picto('', 'clock') . '&ensp;';
			$form->selectAvailabilityDelay('', 'availability_id', '', 1, 'maxwidth200 widthcentpercentminusx');
			print '</td></tr>';

			// Shipping Method
			if (!empty($conf->expedition->enabled)) {
				if (!empty($conf->global->SOCIETE_ASK_FOR_SHIPPING_METHOD) && !empty($soc->shipping_method_id)) {
					$shipping_method_id = $soc->shipping_method_id;
				}
				print '<tr><td>' . $langs->trans('SendingMethod') . '</td><td>';
				print img_picto('', 'object_dollyrevert', 'class="pictofixedwidth"');
				print $form->selectShippingMethod($shipping_method_id, 'shipping_method_id', '', 1, '', 0, 'maxwidth200 widthcentpercentminusx');
				print '</td></tr>';
			}

			// Warehouse
			if (!empty($conf->stock->enabled) && !empty($conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_PROPAL)) {
				require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
				$formproduct = new FormProduct($db);
				print '<tr><td>' . $langs->trans('Warehouse') . '</td><td>';
				print img_picto('', 'stock', 'class="pictofixedwidth"') . $formproduct->selectWarehouses($warehouse_id, 'warehouse_id', '', 1, 0, 0, '', 0, 0, array(), 'maxwidth500 widthcentpercentminusxx');
				print '</td></tr>';
			}

			// Delivery date (or manufacturing)
			print '<tr><td>' . $langs->trans("DeliveryDate") . '</td>';
			print '<td>';
			if ($conf->global->DATE_LIVRAISON_WEEK_DELAY != "") {
				$tmpdte = time() + ((7 * $conf->global->DATE_LIVRAISON_WEEK_DELAY) * 24 * 60 * 60);
				$syear = date("Y", $tmpdte);
				$smonth = date("m", $tmpdte);
				$sday = date("d", $tmpdte);
				print $form->selectDate($syear . "-" . $smonth . "-" . $sday, 'date_livraison', '', '', '', "addprop");
			}
			else {
				print $form->selectDate(-1, 'date_livraison', '', '', '', "addprop", 1, 1);
			}
			print '</td></tr>';

			// Project
			if (!empty($conf->projet->enabled)) {
				$langs->load("projects");
				print '<tr>';
				print '<td>' . $langs->trans("Project") . '</td><td>';
				print img_picto('', 'project', 'class="pictofixedwidth"') . $formproject->select_projects(($soc->id > 0 ? $soc->id : -1), $projectid, 'projectid', 0, 0, 1, 1, 0, 0, 0, '', 1, 0, 'maxwidth500 widthcentpercentminusxx');
				print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?socid=' . $soc->id . '&action=create&status=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create&socid=' . $soc->id) . '"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans("AddProject") . '"></span></a>';
				print '</td>';
				print '</tr>';
			}

			// Incoterms
			if (!empty($conf->incoterm->enabled)) {
				print '<tr>';
				print '<td><label for="incoterm_id">' . $form->textwithpicto($langs->trans("IncotermLabel"), $soc->label_incoterms, 1) . '</label></td>';
				print '<td class="maxwidthonsmartphone">';
				print $form->select_incoterms((!empty($soc->fk_incoterms) ? $soc->fk_incoterms : ''), (!empty($soc->location_incoterms) ? $soc->location_incoterms : ''));
				print '</td></tr>';
			}

			// Template to use by default
			print '<tr>';
			print '<td>' . $langs->trans("DefaultModel") . '</td>';
			print '<td>';
			print img_picto('', 'pdf') . '&ensp;';
			$liste = ModelePDFPropales::liste_modeles($db);
			$preselected = ($conf->global->PROPALE_ADDON_PDF_ODT_DEFAULT ? $conf->global->PROPALE_ADDON_PDF_ODT_DEFAULT : $conf->global->PROPALE_ADDON_PDF);
			print $form->selectarray('model', $liste, $preselected, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth200 widthcentpercentminusx', 1);
			print "</td></tr>";

			// Multicurrency
			if (!empty($conf->multicurrency->enabled)) {
				print '<tr>';
				print '<td>' . $form->editfieldkey('Currency', 'multicurrency_code', '', $object, 0) . '</td>';
				print '<td class="maxwidthonsmartphone">';
				print $form->selectMultiCurrency($currency_code, 'multicurrency_code', 0);
				print '</td></tr>';
			}

			// Public note
			print '<tr>';
			print '<td class="tdtop">' . $langs->trans('NotePublic') . '</td>';
			print '<td valign="top">';
			$note_public = $object->getDefaultCreateValueFor('note_public', (is_object($objectsrc) ? $objectsrc->note_public : null));
			$doleditor = new DolEditor('note_public', $note_public, '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PUBLIC) ? 0 : 1, ROWS_3, '90%');
			print $doleditor->Create(1);

			// Private note
			if (empty($user->socid)) {
				print '<tr>';
				print '<td class="tdtop">' . $langs->trans('NotePrivate') . '</td>';
				print '<td valign="top">';
				$note_private = $object->getDefaultCreateValueFor('note_private', ((!empty($origin) && !empty($originid) && is_object($objectsrc)) ? $objectsrc->note_private : null));
				$doleditor = new DolEditor('note_private', $note_private, '', 80, 'dolibarr_notes', 'In', 0, false, empty($conf->global->FCKEDITOR_ENABLE_NOTE_PRIVATE) ? 0 : 1, ROWS_3, '90%');
				print $doleditor->Create(1);
				// print '<textarea name="note_private" wrap="soft" cols="70" rows="'.ROWS_3.'">'.$note_private.'.</textarea>
				print '</td></tr>';
			}

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

			// Lines from source
			if (!empty($origin) && !empty($originid) && is_object($objectsrc)) {
				// TODO for compatibility
				if ($origin == 'contrat') {
					// Calcul contrat->price (HT), contrat->total (TTC), contrat->tva
					$objectsrc->remise_absolue = $remise_absolue;
					$objectsrc->remise_percent = $remise_percent;
					$objectsrc->update_price(1, -1, 1);
				}

				print "\n<!-- " . $classname . " info -->";
				print "\n";
				print '<input type="hidden" name="amount"         value="' . $objectsrc->total_ht . '">' . "\n";
				print '<input type="hidden" name="total"          value="' . $objectsrc->total_ttc . '">' . "\n";
				print '<input type="hidden" name="tva"            value="' . $objectsrc->total_tva . '">' . "\n";
				print '<input type="hidden" name="origin"         value="' . $objectsrc->element . '">';
				print '<input type="hidden" name="originid"       value="' . $objectsrc->id . '">';

				$newclassname = $classname;
				if ($newclassname == 'Propal') {
					$newclassname = 'CommercialProposal';
				}
				elseif ($newclassname == 'Commande') {
					$newclassname = 'Order';
				}
				elseif ($newclassname == 'Expedition') {
					$newclassname = 'Sending';
				}
				elseif ($newclassname == 'Fichinter') {
					$newclassname = 'Intervention';
				}

				print '<tr><td>' . $langs->trans($newclassname) . '</td><td>' . $objectsrc->getNomUrl(1) . '</td></tr>';
				print '<tr><td>' . $langs->trans('AmountHT') . '</td><td>' . price($objectsrc->total_ht, 0, $langs, 1, -1, -1, $conf->currency) . '</td></tr>';
				print '<tr><td>' . $langs->trans('AmountVAT') . '</td><td>' . price($objectsrc->total_tva, 0, $langs, 1, -1, -1, $conf->currency) . "</td></tr>";
				if ($mysoc->localtax1_assuj == "1" || $objectsrc->total_localtax1 != 0) {        // Localtax1
					print '<tr><td>' . $langs->transcountry("AmountLT1", $mysoc->country_code) . '</td><td>' . price($objectsrc->total_localtax1, 0, $langs, 1, -1, -1, $conf->currency) . "</td></tr>";
				}

				if ($mysoc->localtax2_assuj == "1" || $objectsrc->total_localtax2 != 0) {        // Localtax2
					print '<tr><td>' . $langs->transcountry("AmountLT2", $mysoc->country_code) . '</td><td>' . price($objectsrc->total_localtax2, 0, $langs, 1, -1, -1, $conf->currency) . "</td></tr>";
				}
				print '<tr><td>' . $langs->trans('AmountTTC') . '</td><td>' . price($objectsrc->total_ttc, 0, $langs, 1, -1, -1, $conf->currency) . "</td></tr>";

				if (!empty($conf->multicurrency->enabled)) {
					print '<tr><td>' . $langs->trans('MulticurrencyAmountHT') . '</td><td>' . price($objectsrc->multicurrency_total_ht) . '</td></tr>';
					print '<tr><td>' . $langs->trans('MulticurrencyAmountVAT') . '</td><td>' . price($objectsrc->multicurrency_total_tva) . "</td></tr>";
					print '<tr><td>' . $langs->trans('MulticurrencyAmountTTC') . '</td><td>' . price($objectsrc->multicurrency_total_ttc) . "</td></tr>";
				}
			}

			print "</table>\n";


			/*
			 * Combobox for copy function
			  */

			if (empty($conf->global->PROPAL_CLONE_ON_CREATE_PAGE)) {
				print '<input type="hidden" name="createmode" value="empty">';
			}

			if (!empty($conf->global->PROPAL_CLONE_ON_CREATE_PAGE)) {
				print '<br><table>';

				// For backward compatibility
				print '<tr>';
				print '<td><input type="radio" name="createmode" value="copy"></td>';
				print '<td>' . $langs->trans("CopyPropalFrom") . ' </td>';
				print '<td>';
				$liste_propal = array();
				$liste_propal [0] = '';

				$sql = "SELECT p.rowid as id, p.ref, s.nom";
				$sql .= " FROM " . MAIN_DB_PREFIX . "propal p";
				$sql .= ", " . MAIN_DB_PREFIX . "societe s";
				$sql .= " WHERE s.rowid = p.fk_soc";
				$sql .= " AND p.entity IN (" . getEntity('propal') . ")";
				$sql .= " AND p.fk_statut <> 0";
				$sql .= " ORDER BY Id";

				$resql = $db->query($sql);
				if ($resql) {
					$num = $db->num_rows($resql);
					$i = 0;
					while ($i < $num) {
						$row = $db->fetch_row($resql);
						$propalRefAndSocName = $row [1] . " - " . $row [2];
						$liste_propal [$row [0]] = $propalRefAndSocName;
						$i++;
					}
					print $form->selectarray("copie_propal", $liste_propal, 0);
				}
				else {
					dol_print_error($db);
				}
				print '</td></tr>';

				print '<tr><td class="tdtop"><input type="radio" name="createmode" value="empty" checked></td>';
				print '<td valign="top" colspan="2">' . $langs->trans("CreateEmptyPropal") . '</td></tr>';
				print '</table>';
			}

			print dol_get_fiche_end();

			$langs->load("bills");
			print '<div class="center">';
			print '<input type="submit" class="button" value="' . $langs->trans("CreateDraft") . '">';
			print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
			print '<input type="button" class="button button-cancel" value="' . $langs->trans("Cancel") . '" onClick="javascript:history.go(-1)">';
			print '</div>';

			print "</form>";


			// Show origin lines
			if (!empty($origin) && !empty($originid) && is_object($objectsrc)) {
				print '<br>';

				$title = $langs->trans('ProductsAndServices');
				print load_fiche_titre($title);

				print '<table class="noborder centpercent">';

				$objectsrc->printOriginLinesList();

				print '</table>';
			}
		}
		elseif ($object->id > 0) {
			/*
			 * Show object in view mode
			 */

			$soc = new Societe($db);
			$soc->fetch($object->socid);

			$head = propal_prepare_head($object);
			print dol_get_fiche_head($head, 'comm', $langs->trans('Proposal'), -1, 'propal');

			$formconfirm = '';

			// Clone confirmation
			if ($action == 'clone') {
				// Create an array for form
				$formquestion = array(
					// 'text' => $langs->trans("ConfirmClone"),
					// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
					// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
					array('type' => 'other', 'name' => 'socid', 'label' => $langs->trans("SelectThirdParty"), 'value' => $form->select_company(GETPOST('socid', 'int'), 'socid', '(s.client=1 OR s.client=2 OR s.client=3)'))
				);
				if (!empty($conf->global->PROPAL_CLONE_DATE_DELIVERY) && !empty($object->delivery_date)) {
					$formquestion[] = array('type' => 'date', 'name' => 'date_delivery', 'label' => $langs->trans("DeliveryDate"), 'value' => $object->delivery_date);
				}
				// Incomplete payment. We ask if reason = discount or other
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ToClone'), $langs->trans('ConfirmClonePropal', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
			}

			if ($action == 'closeas') {
				//Form to close proposal (signed or not)
				$formquestion = array(
					array('type' => 'select', 'name' => 'statut', 'label' => '<span class="fieldrequired">' . $langs->trans("CloseAs") . '</span>', 'values' => array($object::STATUS_SIGNED => $object->LibStatut($object::STATUS_SIGNED), $object::STATUS_NOTSIGNED => $object->LibStatut($object::STATUS_NOTSIGNED))),
					array('type' => 'text', 'name' => 'note_private', 'label' => $langs->trans("Note"), 'value' => '')                // Field to complete private note (not replace)
				);

				if (!empty($conf->notification->enabled)) {
					require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
					$notify = new Notify($db);
					$formquestion = array_merge($formquestion, array(
						array('type' => 'onecolumn', 'value' => $notify->confirmMessage('PROPAL_CLOSE_SIGNED', $object->socid, $object)),
					));
				}

				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('SetAcceptedRefused'), $text, 'confirm_closeas', $formquestion, '', 1, 250);
			}
			elseif ($action == 'delete') {
				// Confirm delete
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteProp'), $langs->trans('ConfirmDeleteProp', $object->ref), 'confirm_delete', '', 0, 1);
			}
			elseif ($action == 'reopen') {
				// Confirm reopen
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ReOpen'), $langs->trans('ConfirmReOpenProp', $object->ref), 'confirm_reopen', '', 0, 1);
			}
			elseif ($action == 'ask_deleteline') {
				// Confirmation delete product/service line
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
			}
			elseif ($action == 'validate') {
				// Confirm validate proposal
				$error = 0;

				// We verify whether the object is provisionally numbering
				$ref = substr($object->ref, 1, 4);
				if ($ref == 'PROV') {
					$numref = $object->getNextNumRef($soc);
					if (empty($numref)) {
						$error++;
						setEventMessages($object->error, $object->errors, 'errors');
					}
				}
				else {
					$numref = $object->ref;
				}

				$text = $langs->trans('ConfirmValidateProp', $numref);
				if (!empty($conf->notification->enabled)) {
					require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
					$notify = new Notify($db);
					$text .= '<br>';
					$text .= $notify->confirmMessage('PROPAL_VALIDATE', $object->socid, $object);
				}

				if (!$error) {
					$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ValidateProp'), $text, 'confirm_validate', '', 0, 1);
				}
			}

			// Call Hook formConfirm
			$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
			$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
			if (empty($reshook)) {
				$formconfirm .= $hookmanager->resPrint;
			}
			elseif ($reshook > 0) {
				$formconfirm = $hookmanager->resPrint;
			}

			// Print form confirm
			print $formconfirm;


			// Proposal card

			$linkback = '<a href="' . DOL_URL_ROOT . '/comm/propal/list.php?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

			$morehtmlref = '<div class="refidno">';
			// Ref customer
			$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', 0, 1);
			$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $usercancreate, 'string', '', null, null, '', 1);
			// Thirdparty
			$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'customer');
			if (empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $object->thirdparty->id > 0) {
				$morehtmlref .= ' (<a href="' . DOL_URL_ROOT . '/comm/propal/list.php?socid=' . $object->thirdparty->id . '&search_societe=' . urlencode($object->thirdparty->name) . '">' . $langs->trans("OtherProposals") . '</a>)';
			}
			// Project
			if (!empty($conf->projet->enabled)) {
				$langs->load("projects");
				$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';
				if ($usercancreate) {
					if ($action != 'classify'  && count($object->lines)==0 && $object->status==0) {
						$morehtmlref .= '<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a>';
						$morehtmlref .= ' : ';
					}
					if ($action == 'classify') {
						//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
						$morehtmlref .= '<form method="post" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
						$morehtmlref .= '<input type="hidden" name="action" value="classin">';
						$morehtmlref .= '<input type="hidden" name="token" value="' . newToken() . '">';
						$morehtmlref .= $formproject->select_projects($object->socid, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
						$morehtmlref .= '<input type="submit" class="button valignmiddle" value="' . $langs->trans("Modify") . '">';
						$morehtmlref .= '</form>';
					}
					else {
						$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
					}
				}
				else {
					if (!empty($object->fk_project)) {
						$proj = new Project($db);
						$proj->fetch($object->fk_project);
						$morehtmlref .= '<a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $object->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
						$morehtmlref .= $proj->ref;
						$morehtmlref .= '</a>';
					}
					else {
						$morehtmlref .= '';
					}
				}
			}
			$morehtmlref .= '</div>';


			dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);


			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';
			print '<div class="underbanner clearboth"></div>';

			print '<table class="border tableforfield" width="100%">';

			// Link for thirdparty discounts
			if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
				$filterabsolutediscount = "fk_facture_source IS NULL"; // If we want deposit to be substracted to payments only and not to total of final invoice
				$filtercreditnote = "fk_facture_source IS NOT NULL"; // If we want deposit to be substracted to payments only and not to total of final invoice
			}
			else {
				$filterabsolutediscount = "fk_facture_source IS NULL OR (description LIKE '(DEPOSIT)%' AND description NOT LIKE '(EXCESS RECEIVED)%')";
				$filtercreditnote = "fk_facture_source IS NOT NULL AND (description NOT LIKE '(DEPOSIT)%' OR description LIKE '(EXCESS RECEIVED)%')";
			}

			print '<tr><td class="titlefield">' . $langs->trans('Discounts') . '</td><td>';

			$absolute_discount = $soc->getAvailableDiscounts('', $filterabsolutediscount);
			$absolute_creditnote = $soc->getAvailableDiscounts('', $filtercreditnote);
			$absolute_discount = price2num($absolute_discount, 'MT');
			$absolute_creditnote = price2num($absolute_creditnote, 'MT');

			$thirdparty = $soc;
			$discount_type = 0;
			$backtopage = urlencode($_SERVER["PHP_SELF"] . '?id=' . $object->id);
			include DOL_DOCUMENT_ROOT . '/core/tpl/object_discounts.tpl.php';

			print '</td></tr>';

			// Date of proposal
			print '<tr>';
			print '<td>';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('DatePropal');
			print '</td>';
			if ($action != 'editdate' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editdate&amp;id=' . $object->id . '">' . img_edit($langs->trans('SetDate'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefield">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editdate' && $usercancreate) {
				print '<form name="editdate" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="post">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="action" value="setdate">';
				print $form->selectDate($object->date, 're', '', '', 0, "editdate");
				print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '">';
				print '</form>';
			}
			else {
				if ($object->date) {
					print dol_print_date($object->date, 'day');
				}
				else {
					print '&nbsp;';
				}
			}
			print '</td>';

			// Date end proposal
			print '<tr>';
			print '<td>';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('DateEndPropal');
			print '</td>';
			if ($action != 'editecheance' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editecheance&amp;id=' . $object->id . '">' . img_edit($langs->trans('SetConditions'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefield">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editecheance' && $usercancreate) {
				print '<form name="editecheance" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="post">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="action" value="setecheance">';
				print $form->selectDate($object->fin_validite, 'ech', '', '', '', "editecheance");
				print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '">';
				print '</form>';
			}
			else {
				if (!empty($object->fin_validite)) {
					print dol_print_date($object->fin_validite, 'day');
					if ($object->statut == Propal::STATUS_VALIDATED && $object->fin_validite < ($now - $conf->propal->cloture->warning_delay)) {
						print img_warning($langs->trans("Late"));
					}
				}
				else {
					print '&nbsp;';
				}
			}
			print '</td>';
			print '</tr>';

			// Payment term
			print '<tr><td>';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('PaymentConditionsShort');
			print '</td>';
			if ($action != 'editconditions' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editconditions&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetConditions'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefield">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editconditions' && $usercancreate) {
				$form->form_conditions_reglement($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->cond_reglement_id, 'cond_reglement_id');
			}
			else {
				$form->form_conditions_reglement($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->cond_reglement_id, 'none');
			}
			print '</td>';
			print '</tr>';

			// Payment mode
			print '<tr>';
			print '<td class="valuefield">';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('PaymentMode');
			print '</td>';
			if ($action != 'editmode' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editmode&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetMode'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefieldcreate">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editmode' && $usercancreate) {
				$form->form_modes_reglement($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->mode_reglement_id, 'mode_reglement_id', 'CRDT', 1, 1);
			}
			else {
				$form->form_modes_reglement($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->mode_reglement_id, 'none');
			}
			print '</td></tr>';

			// Delivery date
			$langs->load('deliveries');
			print '<tr><td>';
			print $form->editfieldkey($langs->trans('DeliveryDate'), 'date_livraison', $object->delivery_date, $object, $usercancreate, 'datepicker');
			print '</td><td class="valuefield">';
			print $form->editfieldval($langs->trans('DeliveryDate'), 'date_livraison', $object->delivery_date, $object, $usercancreate, 'datepicker');
			print '</td>';
			print '</tr>';

			// Delivery delay
			print '<tr class="fielddeliverydelay"><td>';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('AvailabilityPeriod');
			if (!empty($conf->commande->enabled)) {
				print ' (' . $langs->trans('AfterOrder') . ')';
			}
			print '</td>';
			if ($action != 'editavailability' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editavailability&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetAvailability'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefield">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editavailability' && $usercancreate) {
				$form->form_availability($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->availability_id, 'availability_id', 1);
			}
			else {
				$form->form_availability($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->availability_id, 'none', 1);
			}

			print '</td>';
			print '</tr>';

			// Shipping Method
			if (!empty($conf->expedition->enabled)) {
				print '<tr><td>';
				print '<table width="100%" class="nobordernopadding"><tr><td>';
				print $langs->trans('SendingMethod');
				print '</td>';
				if ($action != 'editshippingmethod' && $usercancreate) {
					print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editshippingmethod&amp;id=' . $object->id . '">' . img_edit($langs->trans('SetShippingMode'), 1) . '</a></td>';
				}
				print '</tr></table>';
				print '</td><td class="valuefield">';
				if ($action == 'editshippingmethod' && $usercancreate) {
					$form->formSelectShippingMethod($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->shipping_method_id, 'shipping_method_id', 1);
				}
				else {
					$form->formSelectShippingMethod($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->shipping_method_id, 'none');
				}
				print '</td>';
				print '</tr>';
			}

			// Warehouse
			if (!empty($conf->stock->enabled) && !empty($conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_PROPAL)) {
				$langs->load('stocks');
				require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
				$formproduct = new FormProduct($db);
				print '<tr><td>';
				$editenable = $usercancreate;
				print $form->editfieldkey("Warehouse", 'warehouse', '', $object, $editenable);
				print '</td><td class="valuefieldcreate">';
				if ($action == 'editwarehouse') {
					$formproduct->formSelectWarehouses($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->warehouse_id, 'warehouse_id', 1);
				}
				else {
					$formproduct->formSelectWarehouses($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->warehouse_id, 'none');
				}
				print '</td>';
				print '</tr>';
			}

			// Origin of demand
			print '<tr><td>';
			print '<table class="nobordernopadding" width="100%"><tr><td>';
			print $langs->trans('Source');
			print '</td>';
			if ($action != 'editdemandreason' && $object->statut == Propal::STATUS_DRAFT && $usercancreate) {
				print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editdemandreason&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetDemandReason'), 1) . '</a></td>';
			}
			print '</tr></table>';
			print '</td><td class="valuefield">';
			if ($object->statut == Propal::STATUS_DRAFT && $action == 'editdemandreason' && $usercancreate) {
				$form->formInputReason($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->demand_reason_id, 'demand_reason_id', 1);
			}
			else {
				$form->formInputReason($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->demand_reason_id, 'none');
			}
			print '</td>';
			print '</tr>';

			// Multicurrency
			if (!empty($conf->multicurrency->enabled)) {
				// Multicurrency code
				print '<tr>';
				print '<td>';
				print '<table class="nobordernopadding" width="100%"><tr><td>';
				print $form->editfieldkey('Currency', 'multicurrency_code', '', $object, 0);
				print '</td>';
				if ($action != 'editmulticurrencycode' && $object->statut == $object::STATUS_DRAFT && $usercancreate) {
					print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editmulticurrencycode&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetMultiCurrencyCode'), 1) . '</a></td>';
				}
				print '</tr></table>';
				print '</td><td class="valuefield">';
				if ($object->statut == $object::STATUS_DRAFT && $action == 'editmulticurrencycode' && $usercancreate) {
					$form->form_multicurrency_code($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->multicurrency_code, 'multicurrency_code');
				}
				else {
					$form->form_multicurrency_code($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->multicurrency_code, 'none');
				}
				print '</td></tr>';

				// Multicurrency rate
				if ($object->multicurrency_code != $conf->currency || $object->multicurrency_tx != 1) {
					print '<tr>';
					print '<td>';
					print '<table class="nobordernopadding" width="100%"><tr>';
					print '<td>';
					print $form->editfieldkey('CurrencyRate', 'multicurrency_tx', '', $object, 0);
					print '</td>';
					if ($action != 'editmulticurrencyrate' && $object->statut == $object::STATUS_DRAFT && $object->multicurrency_code && $object->multicurrency_code != $conf->currency && $usercancreate) {
						print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editmulticurrencyrate&amp;id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetMultiCurrencyCode'), 1) . '</a></td>';
					}
					print '</tr></table>';
					print '</td><td class="valuefield">';
					if ($object->statut == $object::STATUS_DRAFT && ($action == 'editmulticurrencyrate' || $action == 'actualizemulticurrencyrate') && $usercancreate) {
						if ($action == 'actualizemulticurrencyrate') {
							list($object->fk_multicurrency, $object->multicurrency_tx) = MultiCurrency::getIdAndTxFromCode($object->db, $object->multicurrency_code);
						}
						$form->form_multicurrency_rate($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->multicurrency_tx, 'multicurrency_tx', $object->multicurrency_code);
					}
					else {
						$form->form_multicurrency_rate($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->multicurrency_tx, 'none', $object->multicurrency_code);
						if ($object->statut == $object::STATUS_DRAFT && $object->multicurrency_code && $object->multicurrency_code != $conf->currency) {
							print '<div class="inline-block"> &nbsp; &nbsp; &nbsp; &nbsp; ';
							print '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=actualizemulticurrencyrate">' . $langs->trans("ActualizeCurrency") . '</a>';
							print '</div>';
						}
					}
					print '</td></tr>';
				}
			}

			if ($soc->outstanding_limit) {
				// Outstanding Bill
				print '<tr><td>';
				print $langs->trans('OutstandingBill');
				print '</td><td class="valuefield">';
				$arrayoutstandingbills = $soc->getOutstandingBills();
				print ($arrayoutstandingbills['opened'] > $soc->outstanding_limit ? img_warning() : '');
				print price($arrayoutstandingbills['opened']) . ' / ';
				print price($soc->outstanding_limit, 0, $langs, 1, -1, -1, $conf->currency);
				print '</td>';
				print '</tr>';
			}

			if (!empty($conf->global->BANK_ASK_PAYMENT_BANK_DURING_PROPOSAL) && !empty($conf->banque->enabled)) {
				// Bank Account
				print '<tr><td>';
				print '<table width="100%" class="nobordernopadding"><tr><td>';
				print $langs->trans('BankAccount');
				print '</td>';
				if ($action != 'editbankaccount' && $usercancreate) {
					print '<td class="right"><a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?action=editbankaccount&amp;id=' . $object->id . '">' . img_edit($langs->trans('SetBankAccount'), 1) . '</a></td>';
				}
				print '</tr></table>';
				print '</td><td class="valuefield">';
				if ($action == 'editbankaccount') {
					$form->formSelectAccount($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->fk_account, 'fk_account', 1);
				}
				else {
					$form->formSelectAccount($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->fk_account, 'none');
				}
				print '</td>';
				print '</tr>';
			}

			$tmparray = $object->getTotalWeightVolume();
			$totalWeight = $tmparray['weight'];
			$totalVolume = $tmparray['volume'];
			if ($totalWeight) {
				print '<tr><td>' . $langs->trans("CalculatedWeight") . '</td>';
				print '<td class="valuefield">';
				print showDimensionInBestUnit($totalWeight, 0, "weight", $langs, isset($conf->global->MAIN_WEIGHT_DEFAULT_ROUND) ? $conf->global->MAIN_WEIGHT_DEFAULT_ROUND : -1, isset($conf->global->MAIN_WEIGHT_DEFAULT_UNIT) ? $conf->global->MAIN_WEIGHT_DEFAULT_UNIT : 'no');
				print '</td></tr>';
			}
			if ($totalVolume) {
				print '<tr><td>' . $langs->trans("CalculatedVolume") . '</td>';
				print '<td class="valuefield">';
				print showDimensionInBestUnit($totalVolume, 0, "volume", $langs, isset($conf->global->MAIN_VOLUME_DEFAULT_ROUND) ? $conf->global->MAIN_VOLUME_DEFAULT_ROUND : -1, isset($conf->global->MAIN_VOLUME_DEFAULT_UNIT) ? $conf->global->MAIN_VOLUME_DEFAULT_UNIT : 'no');
				print '</td></tr>';
			}

			// Incoterms
			if (!empty($conf->incoterm->enabled)) {
				print '<tr><td>';
				print '<table width="100%" class="nobordernopadding"><tr><td>';
				print $langs->trans('IncotermLabel');
				print '<td><td class="right">';
				if ($usercancreate) {
					print '<a class="editfielda" href="' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . $object->id . '&action=editincoterm">' . img_edit() . '</a>';
				}
				else {
					print '&nbsp;';
				}
				print '</td></tr></table>';
				print '</td>';
				print '<td class="valuefield">';
				if ($action != 'editincoterm') {
					print $form->textwithpicto($object->display_incoterms(), $object->label_incoterms, 1);
				}
				else {
					print $form->select_incoterms((!empty($object->fk_incoterms) ? $object->fk_incoterms : ''), (!empty($object->location_incoterms) ? $object->location_incoterms : ''), $_SERVER['PHP_SELF'] . '?id=' . $object->id);
				}
				print '</td></tr>';
			}

			// Other attributes
			include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

			print '</table>';

			print '</div>';
			print '<div class="fichehalfright">';
			print '<div class="ficheaddleft">';
			print '<div class="underbanner clearboth"></div>';

			print '<table class="border tableforfield centpercent">';

			if (!empty($conf->multicurrency->enabled) && ($object->multicurrency_code && $object->multicurrency_code != $conf->currency)) {
				// Multicurrency Amount HT
				print '<tr><td class="titlefieldmiddle">' . $form->editfieldkey('MulticurrencyAmountHT', 'multicurrency_total_ht', '', $object, 0) . '</td>';
				print '<td class="nowrap">' . price($object->multicurrency_total_ht, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
				print '</tr>';

				// Multicurrency Amount VAT
				print '<tr><td>' . $form->editfieldkey('MulticurrencyAmountVAT', 'multicurrency_total_tva', '', $object, 0) . '</td>';
				print '<td class="nowrap">' . price($object->multicurrency_total_tva, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
				print '</tr>';

				// Multicurrency Amount TTC
				print '<tr><td>' . $form->editfieldkey('MulticurrencyAmountTTC', 'multicurrency_total_ttc', '', $object, 0) . '</td>';
				print '<td class="nowrap">' . price($object->multicurrency_total_ttc, '', $langs, 0, -1, -1, (!empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency)) . '</td>';
				print '</tr>';
			}

			// Amount HT
			print '<tr><td class="titlefieldmiddle">' . $langs->trans('AmountHT') . '</td>';
			print '<td class="nowrap">' . price($object->total_ht, '', $langs, 0, -1, -1, $conf->currency) . '</td>';
			print '</tr>';

			// Amount VAT
			print '<tr><td>' . $langs->trans('AmountVAT') . '</td>';
			print '<td class="nowrap">' . price($object->total_tva, '', $langs, 0, -1, -1, $conf->currency) . '</td>';
			print '</tr>';

			// Amount Local Taxes
			if ($mysoc->localtax1_assuj == "1" || $object->total_localtax1 != 0) {    // Localtax1
				print '<tr><td>' . $langs->transcountry("AmountLT1", $mysoc->country_code) . '</td>';
				print '<td class="nowrap">' . price($object->total_localtax1, '', $langs, 0, -1, -1, $conf->currency) . '</td>';
				print '</tr>';
			}
			if ($mysoc->localtax2_assuj == "1" || $object->total_localtax2 != 0) {    // Localtax2
				print '<tr><td>' . $langs->transcountry("AmountLT2", $mysoc->country_code) . '</td>';
				print '<td class="nowrap">' . price($object->total_localtax2, '', $langs, 0, -1, -1, $conf->currency) . '</td>';
				print '</tr>';
			}

			// Amount TTC
			print '<tr><td>' . $langs->trans('AmountTTC') . '</td>';
			print '<td class="nowrap">' . price($object->total_ttc, '', $langs, 0, -1, -1, $conf->currency) . '</td>';
			print '</tr>';

			// Statut
			//print '<tr><td height="10">' . $langs->trans('Status') . '</td><td class="left" colspan="2">' . $object->getLibStatut(4) . '</td></tr>';

			print '</table>';

			// Margin Infos
			if (!empty($conf->margin->enabled)) {
				$formmargin->displayMarginInfos($object);
			}

			print '</div>';
			print '</div>';
			print '</div>';

			print '<div class="clearboth"></div><br>';

			if (!empty($conf->global->MAIN_DISABLE_CONTACTS_TAB)) {
				$blocname = 'contacts';
				$title = $langs->trans('ContactsAddresses');
				include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
			}

			if (!empty($conf->global->MAIN_DISABLE_NOTES_TAB)) {
				$blocname = 'notes';
				$title = $langs->trans('Notes');
				include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
			}

			/*
			 * Lines
			 */

			// Show object lines
			$result = $object->getLinesArray();

			print '	<form name="addproduct" id="addproduct" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . (($action != 'editline') ? '' : '#line_' . GETPOST('lineid', 'int')) . '" method="POST">
	<input type="hidden" name="token" value="' . newToken() . '">
	<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline') . '">
	<input type="hidden" name="mode" value="">
	<input type="hidden" name="page_y" value="">
	<input type="hidden" name="id" value="' . $object->id . '">
	';

			if (!empty($conf->use_javascript_ajax) && $object->statut == Propal::STATUS_DRAFT) {
				include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
			}

			print '<div class="div-table-responsive-no-min">';
			if (!empty($object->lines) || ($object->statut == Propal::STATUS_DRAFT && $usercancreate && $action != 'selectlines' && $action != 'editline')) {
				print '<table id="tablelines" class="noborder noshadow" width="100%">';
			}

			if (!empty($object->lines)) {
				$ret = $object->printObjectLines($action, $mysoc, $soc, $lineid, 1);
			}

			// Form to add new line
			if ($object->statut == Propal::STATUS_DRAFT && $usercancreate && $action != 'selectlines') {
				if ($action != 'editline') {
					// Add products/services form

					$parameters = array();
					$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
					if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
					if (empty($reshook))
						$object->formAddObjectLine(1, $mysoc, $soc);
				}
				else {
					$parameters = array();
					$reshook = $hookmanager->executeHooks('formEditObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				}
			}

			if (!empty($object->lines) || ($object->statut == Propal::STATUS_DRAFT && $usercancreate && $action != 'selectlines' && $action != 'editline')) {
				print '</table>';
			}
			print '</div>';

			print "</form>\n";

			print dol_get_fiche_end();


			/*
			 * Button Actions
			 */

			if ($action != 'presend') {
				print '<div class="tabsAction">';

				$parameters = array();
				$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been
				// modified by hook
				if (empty($reshook)) {
					if ($action != 'editline') {
						// Validate
						if (($object->statut == Propal::STATUS_DRAFT && $object->total_ttc >= 0 && count($object->lines) > 0)
							|| ($object->statut == Propal::STATUS_DRAFT && !empty($conf->global->PROPAL_ENABLE_NEGATIVE) && count($object->lines) > 0)) {
							if ($usercanvalidate) {
								print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=validate">' . $langs->trans('Validate') . '</a>';
							}
							else {
								print '<a class="butActionRefused classfortooltip" href="#">' . $langs->trans('Validate') . '</a>';
							}
						}
						// Create event
						/*if ($conf->agenda->enabled && ! empty($conf->global->MAIN_ADD_EVENT_ON_ELEMENT_CARD)) 	// Add hidden condition because this is not a "workflow" action so should appears somewhere else on page.
						{
							print '<a class="butAction" href="' . DOL_URL_ROOT . '/comm/action/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans("AddAction") . '</a></div>';
						}*/
						// Edit
						if ($object->statut == Propal::STATUS_VALIDATED && $usercancreate) {
							print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=modif">' . $langs->trans('Modify') . '</a>';
						}

						// ReOpen
						if (($object->statut == Propal::STATUS_SIGNED || $object->statut == Propal::STATUS_NOTSIGNED || $object->statut == Propal::STATUS_BILLED) && $usercanclose) {
							print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=reopen' . (empty($conf->global->MAIN_JUMP_TAG) ? '' : '#reopen') . '"';
							print '>' . $langs->trans('ReOpen') . '</a>';
						}

						// Send
						if (empty($user->socid)) {
							if ($object->statut == Propal::STATUS_VALIDATED || $object->statut == Propal::STATUS_SIGNED || !empty($conf->global->PROPOSAL_SENDBYEMAIL_FOR_ALL_STATUS)) {
								if ($usercansend) {
									print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=presend&mode=init#formmailbeforetitle">' . $langs->trans('SendMail') . '</a>';
								}
								else {
									print '<a class="butActionRefused classfortooltip" href="#" title="' . $langs->trans("NotEnoughPermissions") . '">' . $langs->trans('SendMail') . '</a>';
								}
							}
						}

						// Create a sale order
						if (!empty($conf->commande->enabled) && $object->statut == Propal::STATUS_SIGNED) {
							if ($usercancreateorder) {
								print '<a class="butAction" href="' . DOL_URL_ROOT . '/commande/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans("AddOrder") . '</a>';
							}
						}

						// Create a purchase order
						if (!empty($conf->global->WORKFLOW_CAN_CREATE_PURCHASE_ORDER_FROM_PROPOSAL)) {
							if ($object->statut == Propal::STATUS_SIGNED && ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD)) || !empty($conf->supplier_order->enabled))) {
								if ($usercancreatepurchaseorder) {
									print '<a class="butAction" href="' . DOL_URL_ROOT . '/fourn/commande/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans("AddPurchaseOrder") . '</a>';
								}
							}
						}

						// Create an intervention
						if (!empty($conf->service->enabled) && !empty($conf->ficheinter->enabled) && $object->statut == Propal::STATUS_SIGNED) {
							if ($usercancreateintervention) {
								$langs->load("interventions");
								print '<a class="butAction" href="' . DOL_URL_ROOT . '/fichinter/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans("AddIntervention") . '</a>';
							}
						}

						// Create contract
						if ($conf->contrat->enabled && $object->statut == Propal::STATUS_SIGNED) {
							$langs->load("contracts");

							if ($usercancreatecontract) {
								print '<a class="butAction" href="' . DOL_URL_ROOT . '/contrat/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans('AddContract') . '</a>';
							}
						}

						// Create an invoice and classify billed
						if ($object->statut == Propal::STATUS_SIGNED) {
							if (!empty($conf->facture->enabled) && $usercancreateinvoice) {
								print '<a class="butAction" href="' . DOL_URL_ROOT . '/compta/facture/card.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->socid . '">' . $langs->trans("CreateBill") . '</a>';
							}

							$arrayofinvoiceforpropal = $object->getInvoiceArrayList();
							if ((is_array($arrayofinvoiceforpropal) && count($arrayofinvoiceforpropal) > 0) || empty($conf->global->WORKFLOW_PROPAL_NEED_INVOICE_TO_BE_CLASSIFIED_BILLED)) {
								if ($usercanclose) {
									print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=classifybilled&amp;socid=' . $object->socid . '">' . $langs->trans("ClassifyBilled") . '</a>';
								}
								else {
									print '<a class="butActionRefused classfortooltip" href="#" title="' . $langs->trans("NotEnoughPermissions") . '">' . $langs->trans("ClassifyBilled") . '</a>';
								}
							}
						}

						// Close as accepted/refused
						if ($object->statut == Propal::STATUS_VALIDATED) {
							if ($usercanclose) {
								print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=closeas' . (empty($conf->global->MAIN_JUMP_TAG) ? '' : '#close') . '"';
								print '>' . $langs->trans('SetAcceptedRefused') . '</a>';
							}
							else {
								print '<a class="butActionRefused classfortooltip" href="#" title="' . $langs->trans("NotEnoughPermissions") . '"';
								print '>' . $langs->trans('SetAcceptedRefused') . '</a>';
							}
						}

						// Clone
						if ($usercancreate) {
							print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&amp;socid=' . $object->socid . '&amp;action=clone&amp;token=' . newToken() . '&amp;object=' . $object->element . '">' . $langs->trans("ToClone") . '</a>';
						}

						// Delete
						if ($usercandelete) {
							print '<a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=delete&amp;token=' . newToken() . '"';
							print '>' . $langs->trans('Delete') . '</a>';
						}
					}
				}

				print '</div>';
			}

			//Select mail models is same action as presend
			if (GETPOST('modelselected')) {
				$action = 'presend';
			}

			if ($action != 'presend') {
				print '<div class="fichecenter"><div class="fichehalfleft">';
				print '<a name="builddoc"></a>'; // ancre
				/*
				 * Generated documents
				 */
				$objref = dol_sanitizeFileName($object->ref);
				$filedir = $conf->propal->multidir_output[$object->entity] . "/" . dol_sanitizeFileName($object->ref);
				$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
				$genallowed = $usercanread;
				$delallowed = $usercancreate;

				print $formfile->showdocuments('propal', $objref, $filedir, $urlsource, $genallowed, $delallowed, $object->model_pdf, 1, 0, 0, 28, 0, '', 0, '', $soc->default_lang, '', $object);

				// Show links to link elements
				$linktoelem = $form->showLinkToObjectBlock($object, null, array('propal'));

				$compatibleImportElementsList = false;
				if ($user->rights->propal->creer && $object->statut == Propal::STATUS_DRAFT) {
					$compatibleImportElementsList = array('commande', 'propal'); // import from linked elements
				}
				$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem, $compatibleImportElementsList);

				// Show online signature link
				$useonlinesignature = $conf->global->MAIN_FEATURES_LEVEL; // Replace this with 1 when feature to make online signature is ok

				if ($object->statut != Propal::STATUS_DRAFT && $useonlinesignature) {
					print '<br><!-- Link to sign -->';
					require_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';
					print showOnlineSignatureUrl('proposal', $object->ref) . '<br>';
				}

				print '</div><div class="fichehalfright"><div class="ficheaddleft">';

				// List of actions on element
				include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
				$formactions = new FormActions($db);
				$somethingshown = $formactions->showactions($object, 'propal', $socid, 1);

				print '</div></div></div>';
			}

			// Presend form
			$modelmail = 'propal_send';
			$defaulttopic = 'SendPropalRef';
			$diroutput = $conf->propal->multidir_output[$object->entity];
			$trackid = 'pro' . $object->id;

			include DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
		}
	}
}
