<?php

require('config.php');

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
dol_include_once('/dispatch/class/dispatchdetail.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php';
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';

$PDOdb = new TPDOdb;

$langs->load('companies');
$langs->load('suppliers');
$langs->load('products');
$langs->load('bills');
$langs->load('orders');
$langs->load('commercial');
$langs->load('stocks');
$langs->load('dispatch@dispatch');

$hookmanager->initHooks(array('receptionstockcard'));

$id = GETPOST('id');
$ref = GETPOST('ref');
$action = GETPOST('action');
$comment = dol_htmlentities(GETPOST('comment', 'none'));
$post_create_ventilation_expe = GETPOST('bt_create');
$TLine = GETPOST('TLine');
$date_recep  = GETPOST('date_recep');
$btSave = GETPOST('bt_save');
$toDispatch = GETPOST('ToDispatch');
$newLineFkProduct = GETPOST('new_line_fk_product');
$TOrderLine = GETPOST('TOrderLine');
$dataShipmentTreatedId = GETPOST('data-shipment-treated-id');
$dataShipmentEntity = GETPOST('data-shipment-entity');

$commandefourn = new CommandeFournisseur($db);
$commandefourn->fetch($id, $ref);
$TImport = _loadDetail($PDOdb,$commandefourn);


$parameters=array();
$hookmanager->executeHooks('doAction',$parameters, $commandefourn, $action);


////////// GESTION DE OFSOM ///////////

if(isset($post_create_ventilation_expe) && !empty($post_create_ventilation_expe)) {

	$PDOdb=new TPDOdb;
	$time_date_recep = Tools::get_time($date_recep);

	// Tableau provisoire qui permettra la ventilation standard Dolibarr après la création des équipements
	$TProdVentil = array();
	$TAssetVentil=array();
	$TAssetCreated = array();

	// Use to calculated corrected order status at the end of dispatch/serialize process
	$TQtyDispatch=array();
	$TQtyWished=array();

	$commandefourn->fetch_thirdparty();

	$Tlength = count($TLine);
	// Préparation du $TImport pour éviter les effets de bord
	for ($i = 0; $i < $Tlength; $i++){
		$TImport[$i]['numserie'] = $TLine[$i]['numserie'];
        $TImport[$i]['notes'] = $TLine[$i]['notes'];
        $TImport[$i]['vpn'] = $TLine[$i]['vpn'];
        $TImport[$i]['codebarre'] = $TLine[$i]['codebarre'];
        $TImport[$i]['numlog'] = $TLine[$i]['numlog'];


		$TImport[$i]['ref'] = $TLine[$i]['ref'];
		$TImport[$i]['fk_product'] = $TLine[$i]['fk_product'];
		$TImport[$i]['fk_entrepot'] = $TLine[$i]['entrepot'];
		$TImport[$i]['fk_warehouse'] = $TLine[$i]['entrepot'];
		$TImport[$i]['quantity'] = $TLine[$i]['quantity'];
		$TImport[$i]['fk_asset'] = $TLine[$i]['fk_asset'];

	}


	foreach($TImport as $k=>&$line) {

		$asset = new TAsset();
		// Dans le cas où on gère des lots et non des numéros de série
		if (!empty($conf->global->DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_IF_LOT) && empty($line['numserie']) && !empty($line['lot_number'])) {
			$product = new Product($db);
			$product->fetch($line['fk_product']);

			$asset->fk_asset_type = $product->array_options['options_type_asset'];

			if ($asset->fk_asset_type > 0) {
				$asset->load_asset_type($PDOdb);
				$line['numserie'] = $asset->getNextValue($PDOdb, $commandefourn->thirdparty);
				setEventMessage($langs->trans('createNumSerieOnTheFly', $line['numserie']), "warning");

				$TImport = _addCommandedetLine($PDOdb, $TImport, $commandefourn, $product->ref, $line['numserie'], $line['imei'], $line['firmware'], $line['lot_number'], ($line['quantity']) ? $line['quantity'] : 1, $line['quantity_unit'], $line['dluo'], $k, $line['entrepot'], $comment);
			}

		}
		// Si on a une fk_asset, cela veut dire qu'on est un équipement et donc qu'on attend d'avoir un numéro de série afin d'être créé
		// Si fk_asset = null, cela veut dire qu'on est un produit "non sérialisé"
		if (empty($line['numserie']) && ($line['fk_asset']) !== 'standardProduct') {
			setEventMessage($langs->trans('NoSerialNumber', $line['ref']), 'errors');
		}
		// Ici, on a un fk_asset et un numéro de série renseigné donc on est un équipement
		// Mais on vérifie que cet équipement n'est pas déjà créé chez nous
		else if ($line['fk_asset'] !== 'standardProduct') {

			if (!$asset->loadReference($PDOdb, $line['numserie'], $line['fk_product'])) {


				// TODO /!\ À décommenter et analyser...
				//				if($k == -1){
				//					_addCommandedetLine($PDOdb,$TImport,$commandefourn,$line['ref'],$line['numserie'],$line['$imei'],$line['$firmware'],$line['lot_number'],$line['quantity'],$line['quantity_unit'],null,null,$line['fk_warehouse'], $comment);
				//				}

				$prod = new Product($db);

				$res = $prod->fetch($line['fk_product']);


				if($res) {

					// Affectation du type d'équipement pour avoir accès aux extrafields équipement
					$asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
					$asset->load_asset_type($PDOdb);
					$asset->fk_product = $line['fk_product'];
					$asset->serial_number = ($line['numserie']) ? $line['numserie'] : $asset->getNextValue($PDOdb);
					$asset->lot_number = $line['lot_number'];
					$asset->contenance_value = ($line['quantity']) ? $line['quantity'] : 1;
					$asset->contenancereel_value = ($line['quantity']) ? $line['quantity'] : 1;
					$asset->contenancereel_units = ($line['quantity_unit']) ? $line['quantity_unit'] : 0;
					$asset->contenance_units = ($line['quantity_unit']) ? $line['quantity_unit'] : 0;
					$asset->lot_number = $line['lot_number'];
					$asset->firmware = $line['firmware'];
					$asset->imei = $line['imei'];
					$asset->set_date('dluo', $line['dluo']);
					$asset->entity = $conf->entity;
					$nb_year_garantie = 0;
					// Renseignement des extrafields
					$asset->set_date('date_reception', $date_recep);
                    $asset->vpn = $line['vpn'];
                    $asset->codebarre =$line['codebarre'];
                    $asset->numlog =$line['numlog'];
                    $asset->notes =$line['notes'];

					// OPTIONS DE GARANTIE
					foreach ($commandefourn->lines as $l) {
						if ($l->fk_product == $asset->fk_product) {
							$asset->valeur = $asset->prix_achat = price2num($l->subprice, 'MU');

							$extension_garantie = 0;
							$PDOdb->Execute('SELECT extension_garantie FROM ' . MAIN_DB_PREFIX . 'commande_fournisseurdet WHERE rowid = ' . $l->id);
							if ($PDOdb->Get_line()) {
								$extension_garantie = $PDOdb->Get_field('extension_garantie');
							}

						}
					}
					$nb_year_garantie += $prod->array_options['options_duree_garantie_fournisseur'];
					$asset->date_fin_garantie_fourn = strtotime('+' . $nb_year_garantie . 'year', $time_date_recep);
					$asset->date_fin_garantie_fourn = strtotime('+' . $extension_garantie . 'year', $asset->date_fin_garantie_fourn);
					// FIN OPTIONS GARANTIE

					$asset->fk_soc = $commandefourn->socid;
					$fk_entrepot = (!empty($line['fk_warehouse']) && $line['fk_warehouse'] > 0) ? $line['fk_warehouse'] : GETPOST('id_entrepot', 'int');
					$asset->fk_entrepot = $fk_entrepot;

					$societe = new Societe($db);
					$societe->fetch('', $conf->global->MAIN_INFO_SOCIETE_NOM);

					$asset->fk_societe_localisation = $societe->id;
					$asset->etat = 0; //En stock

					// Le destockage dans Dolibarr est fait par la fonction de ventilation plus loin, donc désactivation du mouvement créé par l'équipement.
					//				$asset->save($PDOdb, $user,$langs->trans("Asset").' '.$asset->serial_number.' '. $langs->trans("DispatchSupplierOrder",$commandefourn->ref), $line['quantity'], false, $line['fk_product'], false,$fk_entrepot);
					$TAssetCreated[$asset->fk_product][] = $asset->save($PDOdb, $user, '', 0, false, 0, true, $fk_entrepot);

					$TAssetVentil[$line['fk_product']][$fk_entrepot]['qty'] += $line['quantity'];
					$TAssetVentil[$line['fk_product']][$fk_entrepot]['price'] += $line['quantity'] * $asset->prix_achat;
					$TAssetVentil[$line['fk_product']][$fk_entrepot][$asset->getId()]['qty'] = $line['quantity'];
					$TAssetVentil[$line['fk_product']][$fk_entrepot][$asset->getId()]['price'] = $line['quantity'] * $asset->prix_achat;
					$TAssetVentil[$line['fk_product']][$fk_entrepot][$asset->getId()]['comment'] = $comment;


					if ($asset->serial_number != $line['numserie']) {
						$receptDetailLine = new TRecepDetail;
						$receptDetailLine->load($PDOdb, $line['commande_fournisseurdet_asset']);
						$receptDetailLine->numserie = $receptDetailLine->serial_number = $asset->serial_number;
						$receptDetailLine->save($PDOdb);
					}
				} else {
					dol_syslog(__METHOD__.' $prod='.var_export($prod,true), LOG_ERR);
				}
			} else {

				setEventMessage($langs->trans('AssetAlreadyLinked') . ' : ' . $line['numserie'], 'errors');
			}
		}
	}

	// INSERT EN BASE DES TABLES llx_Commande_Fournisseur_Dispatch, llx_stock_mouvement, llx_assetatm
	if(!empty($TAssetVentil)) {
		foreach($TAssetVentil as $fk_product=>$item) {
			foreach($item as $fk_entrepot=>$TDispatchEntrepot) {
				$qty = $TDispatchEntrepot['qty'];
				$unitPrice = $TDispatchEntrepot['qty'] > 0 ? $TDispatchEntrepot['price'] / $TDispatchEntrepot['qty'] : 0;
				if(empty($conf->global->DISPATCH_STOCK_MOVEMENT_BY_ASSET)) $ret = $commandefourn->dispatchProduct($user,$fk_product, $qty, $fk_entrepot, $unitPrice, $comment);
				else $ret = 1;

				if($ret > 0 && !empty($conf->stock->enabled)
					&& !empty($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER)
					&& !empty($conf->global->DISPATCH_LINK_ASSET_TO_STOCK_MOVEMENT) // conf cachée
					&& !empty($TAssetCreated[$fk_product]))
				{
					// lier les asset créés au mouvement de stock pour en récupérer le prix

					foreach($TAssetCreated[$fk_product] as $asset_id) {
						if(!empty($conf->global->DISPATCH_STOCK_MOVEMENT_BY_ASSET)) {
							$ret = $commandefourn->dispatchProduct($user, $fk_product, $TDispatchEntrepot[$asset_id]['qty'], $fk_entrepot, $TDispatchEntrepot[$asset_id]['price'], $TDispatchEntrepot[$asset_id]['comment']);
						}
						else $ret = 1;
						if($ret > 0) {
							$sql = "SELECT MAX(rowid) as id FROM " . MAIN_DB_PREFIX . "stock_mouvement";
							$sql .= " WHERE origintype = 'order_supplier'";
							$sql .= " AND fk_origin = " . $commandefourn->id;
							$sql .= " AND fk_product = " . $fk_product;
							$sql .= " AND fk_entrepot = " . $fk_entrepot;
							$res = $db->query($sql);
							if($res) {
								$obj = $db->fetch_object($res);
								if($obj) {

									$lastStockMouvement = $obj->id;

									TAsset::set_element_element($asset_id, 'TAssetOFLine', $lastStockMouvement, 'DolStockMouv');
									$stock = new TAssetStock;
									$stock->mouvement_stock($PDOdb, $user, $asset_id, $TDispatchEntrepot[$asset_id]['qty'], $TDispatchEntrepot[$asset_id]['comment'], $asset->rowid, $lastStockMouvement);
								} else {
									dol_syslog(__METHOD__.' $obj='.var_export($obj,true), LOG_ERR);
								}
							}
						}
					}
				}
				elseif(!empty($conf->global->DISPATCH_STOCK_MOVEMENT_BY_ASSET)) $ret = $commandefourn->dispatchProduct($user,$fk_product, $qty, $fk_entrepot, $unitPrice, $comment);

				//Build array with quantity serialze by product
				$TQtyDispatch[$fk_product]+=$qty;
			}
		}
	}

	// PRISE EN COMPTE DES LIGNES NON VENTILÉES EN RÉCEPTION SIMPLE
	if(!empty($TLine)) {

		foreach($TLine as &$line) {

			if($line['fk_asset'] == 'standardProduct') {

				$TProdVentil[$line['fk_product']]['qty'] = $line['quantity'];
				$TProdVentil[$line['fk_product']]['price'] = $line['subprice'];

				if (!empty($line['entrepot']) && $line['entrepot'] > 0) {
					$TProdVentil[$line['fk_product']]['entrepot'] = $line['entrepot'];
				}

				if ($conf->global->DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION) {
					$TProdVentil[$line['fk_product']]['supplier_price'] = $line['supplier_price'];
				}

				if ($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE) {
					$TProdVentil[$line['fk_product']]['supplier_qty'] = $line['supplier_qty'];
					$TProdVentil[$line['fk_product']]['generate_supplier_tarif'] = $line['generate_supplier_tarif'];
				}

				// Build array with quantity wished by product
				if (array_key_exists('fk_product', $line) && !empty($line['fk_product']) && !array_key_exists($line['fk_product'], $TQtyDispatch)) {
					$TQtyDispatch[$line['fk_product']] += $line['quantity'];
				}
			}

		}

	}

	dol_syslog(__METHOD__.' $TProdVentil='.var_export($TProdVentil,true), LOG_DEBUG);

	$status = $commandefourn->statut;

	if(count($TProdVentil)>0) {

		$status = $commandefourn->statut;

		foreach($TProdVentil as $id_prod => $item){
			//Fonction standard ventilation commande fournisseur
			//TODO AA dans la 3.9 il y a l'id de la ligne concernée... Ce qui implique de ne plus sélectionner un produit mais une ligne à ventiler. Adaptation à faire dans une future version
			if($conf->global->DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION)
			{
				$sup_price = $item['supplier_price'];

				$lineprod = searchProductInCommandeLine($commandefourn->lines, $id_prod);
				$unitaire = ($sup_price / $lineprod->qty);
				$prix =  $unitaire * $lineprod->qty;
				if($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE)
				{
					$sup_qty = $item['supplier_qty'];
					$generate = ($item['generate_supplier_tarif'] == 'on')?true:false;
					// On va générer le prix s'il est coché
					if($generate)
					{
						$fourn = new Fournisseur($db);
						$fourn->fetch($commandefourn->socid);
						$prix =  $unitaire * $sup_qty;
						$fournisseurproduct = new ProductFournisseur($db);
						$fournisseurproduct->id = $id_prod;
						$fournisseurproduct->update_buyprice($sup_qty, $prix, $user, 'HT', $fourn, 0, $lineprod->ref_supplier, '20');
					}
				}else{
					$sup_qty += $lineprod->qty;
				}

				if($lineprod->subprice != $unitaire && $unitaire > 0)
				{
					$prixtva = $prix * ($lineprod->tva_tx/100);
					$total = $prix + $prixtva;

					$lineprod->subprice = ''.$unitaire;
					$lineprod->total_ht = ''.$prix;
					$lineprod->total_tva = ''.$prixtva;
					$lineprod->total_ttc = ''.$total;

					$_REQUEST['lineid'] = $line->id;


					$commandefourn->brouillon = true; // obligatoire pour mettre a jour les lignes
					$commandefourn->updateline($lineprod->id, $lineprod->desc, $lineprod->subprice, $lineprod->qty, $lineprod->remise_percent, $lineprod->tva_tx,
						$lineprod->localtax1_tx, $lineprod->localtax2_tx, 'HT', 0, 0, 0, false, null, null, 0, null);
					$commandefourn->brouillon = false;
				}
			}
			// END NEW CODE
			dol_syslog(__METHOD__.' dispatchProduct idprod='.$id_prod.' qty='.$item['qty'], LOG_DEBUG);
			$ret = $commandefourn->dispatchProduct($user, $id_prod, $item['qty'], empty( $item['entrepot']) ? GETPOST('id_entrepot') : $item['entrepot'],$item['price'],$comment);
		}
	}

	if($commandefourn->statut == 0){
		$commandefourn->valid($user);
	}

	foreach($commandefourn->lines as $l){
		if (!empty($l->fk_product) && !empty( $l->qty ) ) {
			$TQtyWished[$l->fk_product]+=$l->qty;
		}
	}


	$TQtyDispatched = array();
	$sql = "SELECT cfd.fk_product, sum(cfd.qty) as qty";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as l on l.rowid = cfd.fk_commandefourndet";
	$sql.= " WHERE cfd.fk_commande = ".$commandefourn->id;
	$sql.= " GROUP BY cfd.fk_product";
	$resql = $db->query($sql);
	while($objd = $db->fetch_object($resql)) {
		$TQtyDispatched[$objd->fk_product] = $objd->qty;
	}

	//Compare array
	dol_syslog(__METHOD__.' $TQtyDispatched='.var_export($TQtyDispatched,true), LOG_DEBUG);
	dol_syslog(__METHOD__.' $TQtyWished='.var_export($TQtyWished,true), LOG_DEBUG);

	$status = 5;

	// Si on trouve au moins un produit dont la quantité ventilée est inférieure au commandé, la commande n'est reçue que partiellement
	foreach($TQtyWished as $fk_product => $qty) {
		if($TQtyDispatched[$fk_product] < $qty) {
			$status = 4;
			break;
		}
	}

	$commandefourn->setStatus($user, $status);
	$commandefourn->statut = $status;
	if(method_exists($commandefourn, 'log')) $commandefourn->log($user, $status, time()); // removed in 4.0

	setEventMessage($langs->transnoentities('DispatchMsgAssetGen'));
	if (isset($dataShipmentTreatedId) && !empty($dataShipmentTreatedId)) {
		_set_treated_expedition_extrafield($dataShipmentTreatedId, $dataShipmentEntity);
	}

    /**
     *
     *  le doAction prepare les expeditions pour l'affichage
     *  et reflete l'etat avant la mise à jour
     *  on redirige ici pour afficher l'etat aprés modification.
     *
     */
    $redirection = DOL_URL_ROOT.'/custom/dispatch/receptionofsom.php?id='.$id;
    header('Location: '.$redirection);
    exit();
}
fiche($commandefourn, $TImport, $comment);
?>
			<script type="text/javascript">

				/* Javascript library of module dispatch */
				$(document).ready(function() {


					$(document).on("click", ".butActionDelete", function (e) {
						e.preventDefault();
						$(".shipment-details").remove();
						$('.ofsomVentilExpeBtn').removeClass('butActionRefused');
					});

					$(document).on("click", ".ofsomVentilExpeBtn", function (e) {
						$('.ofsomVentilExpeBtn').removeClass('butActionRefused');
						$(this).addClass('butActionRefused');

						let comFourn = $(this).attr('data-commandFourn-id');
						let expeid = $(this).attr('data-shipment-id');
						let experef = $(this).attr('data-shipment-ref');
						let entity = $(this).attr('data-shipment-entity');
						let selectorforexpenumber = $(this).attr('data-selectwarehouse-for-expe_number');
						let idWarehouse = $(this).parent().find('#selectwarehouse'+selectorforexpenumber).val();

						var className = "shipment-details";
						if ($(className).length == 0) {
							$(".tabBar").append('<div class="'+className+'" ></div>');
						}

						let dataToSend = {
							comFourn : comFourn,
							idexpe: expeid,
							refexpe: experef,
							entity : entity,
							action: "loadExpeLines",
							idWarehouse : idWarehouse
						};

						$.ajax({
							url: "<?php print dol_buildpath('dispatch/script/interface_expedition_handler.php', 1)?>",
							method: "POST",
							dataType: "json",
							data: dataToSend,
							success: function (data) {
								if(!data.error) {
									$(".shipment-details").html(data.html);
								}else {
									dispatchSetMessage(data.error, "error");
								}
							}
						})
					});

					function dispatchSetMessage($out, $type = "success") {
						$.jnotify($out, $type, 3000);
					}

				});
			</script>
<?php

/**
 * @param Commande $commande
 * @param array $TImport
 * @param string $comment
 */
function fiche(&$commande, &$TImport, $comment) {

	global $langs, $db, $conf;

	llxHeader( '', $langs->transnoentities('ReceptionTab').' | '.$commande->ref);

	$head = ordersupplier_prepare_head($commande);

	$title=$langs->trans("SupplierOrder");
	$notab=-1;
	dol_fiche_head($head, 'recepasset', $title, $notab, 'order');

	entetecmd($commande);

	$form=new TFormCore('auto','formrecept','post', true);
	echo $form->hidden('action', 'SAVE');
	echo $form->hidden('id', $commande->id);

	if($commande->statut < 5 && $conf->global->DISPATCH_USE_IMPORT_FILE){
		echo $form->fichier('Fichier à importer','file1','',80);
		echo $form->btsubmit('Envoyer', 'btsend');
	}

	_list_shipments_untreated($commande->shipmentsFromSupplier,$commande->id);

	_list_shipments_treated($commande->shipmentsFromSupplier,$commande->id);

	dol_fiche_end($notab);
	llxFooter();
}

/**
 * Récupération des expéditions
 * @param array $shipments
 * @param int $idCmdFourn
 */
function _list_shipments_untreated(&$shipments , $idCmdFourn){

	global $db, $langs, $conf, $user;
	print load_fiche_titre($langs->trans("ShipmentsList"));

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("SelectShipment").'</td>';

	if (! empty($conf->productbatch->enabled) && (float) DOL_VERSION > 3.7)
	{
		print '<td>'.$langs->trans("batch_number").'</td>';
		print '<td>'.$langs->trans("l_eatby").'</td>';
		print '<td>'.$langs->trans("l_sellby").'</td>';
	}

	print '<td></td>';
	if (! empty($conf->global->SUPPLIER_ORDER_USE_DISPATCH_STATUS) && (float) DOL_VERSION > 3.7)
		print '<td align="center" colspan="2">'.$langs->trans("Status").'</td>';

	print "</tr>\n";
	print "</table>\n";

	foreach ($shipments as $k=>$shipment) {

		$current_cmdFourn = new CommandeFournisseur($db);
		$objCurrentCmdFourn = $current_cmdFourn->fetch($idCmdFourn);

        //$backupConfEntity = $conf->entity;
		if($objCurrentCmdFourn) {
			$formproduct = new FormProduct($db);

			//$conf->entity = $shipment->entity;
            if ($shipment->customer_treated_shipment == '0' || $shipment->customer_treated_shipment == null){
				//$conf->entity = $backupConfEntity;

					print '<td>' . $current_cmdFourn->getNomUrl() . '  ->   <span class="classfortooltip" style="margin-right:25px" title="' . $langs->trans("supplierOrderLinkedShipment") . '">' . $shipment->ref . ' </span></td>';
					print '<td></td>';
					print '<td>' . $langs->trans('Warehouse') . $formproduct->selectWarehouses('', "selectwarehouse" . $k, '', 1);
					'</td>';
					$form = new TFormCore;
					print '<td><a class="butAction ofsomVentilExpeBtn button --ventilate-button" type="submit" data-selectwarehouse-for-expe_number="' . $k . '" data-shipment-entity="' . $current_cmdFourn->entity . '" data-shipment-id="' . $shipment->rowid . '" data-commandFourn-id="' . $idCmdFourn . '"  data-shipment-ref="' . $shipment->ref . '"  >' . $langs->trans("SelectExpe") . '</a></td><hr><br/>';
            } else{
               // $conf->entity = $backupConfEntity;
				dol_syslog(__METHOD__.' $objCurrentExp='.var_export($shipment,true), LOG_ERR);
			}
		} else{
           // $conf->entity = $backupConfEntity;
			dol_syslog(__METHOD__.' $objCurrentCmdFourn='.var_export($objCurrentCmdFourn,true), LOG_ERR);
		}
	}

}

/**
 * Affichage des expéditions traitées
 * @param $shipments
 */
function _list_shipments_treated(&$shipments , $idCmdFourn){
	global $db, $langs, $conf, $user;

	$TreatedExpAlreadyExists = _isTreatedExpAlreadyExists($shipments);

	if($TreatedExpAlreadyExists){
		print load_fiche_titre($langs->trans("ShipmentsTreatedList"));
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("VentilatedExpeditions").'</td>';

		if (! empty($conf->productbatch->enabled) && (float) DOL_VERSION > 3.7)
		{
			print '<td>'.$langs->trans("batch_number").'</td>';
			print '<td>'.$langs->trans("l_eatby").'</td>';
			print '<td>'.$langs->trans("l_sellby").'</td>';
		}

		print '<td></td>';
		if (! empty($conf->global->SUPPLIER_ORDER_USE_DISPATCH_STATUS) && (float) DOL_VERSION > 3.7)
			print '<td align="center" colspan="2">'.$langs->trans("Status").'</td>';

		print "</tr>\n";
		print "</table>\n";

		foreach ($shipments as $shipment) {

            /**  bug sur conf entity  je n'ai pas résolu
             *   le fait qit que $conf->entity ne pointe pas vers 9 (ama shangai)
             *   ce petit hack passe au travers
             */
            $backupConfEntity = $conf->entity;
            $conf->entity = $_SESSION["dol_entity"];

            $current_cmdFourn = new CommandeFournisseur($db);
			$objCurrentFournCom = $current_cmdFourn->fetch($idCmdFourn);
            $conf->entity = $backupConfEntity;

			if ($objCurrentFournCom) {

                $backupConfEntity = $conf->entity;
                $conf->entity = $shipment->entity;

				if ($shipment->customer_treated_shipment == "1") {
					$conf->entity = $backupConfEntity;

					// On remonte l'extrafield caché de l'état de traitement de l'expédition.
					//if ($currentExp->array_options['options_customer_treated_shipment']) {

						print '<td>' . $current_cmdFourn->getNomUrl() . '  ->   <span class="classfortooltip" title="' . $langs->trans("supplierOrderLinkedShipment") . '">' . $shipment->ref . ' </span></td>';
						print '<td></td>';
						$form = new TFormCore;
						print '<td><span class="butActionRefused" data-shipment-entity="' . $current_cmdFourn->entity . '" data-shipment-id="' . $shipment->rowid . '" data-commandFourn-id="' . $idCmdFourn . '"  data-shipment-ref="' . $shipment->ref . '"  >' . $langs->trans("TreatedExpe") . '</span></td><hr><br/>';
					//}
				} else{
					dol_syslog(__METHOD__.' $ret='.var_export($shipment,true), LOG_ERR);
				}
			} else{
				dol_syslog(__METHOD__.' $objCurrentFournCom='.var_export($objCurrentFournCom,true), LOG_ERR);
			}
		}
	}
	elseif($TreatedExpAlreadyExists === -1){
		dol_syslog(__METHOD__.' $TreatedExpAlreadyExists='.var_export($TreatedExpAlreadyExists,true), LOG_ERR);
	}

    _list_already_dispatched($idCmdFourn);
}

/**
 * @param Commande $commande
 */
function entetecmd(&$commande)
{
	global $langs, $db, $form, $user, $conf;

	if(empty($commande->thirdparty) && method_exists($commande, 'fetch_thirdparty'))
	{
		$commande->fetch_thirdparty();
	}

	if(! is_object($form))
	{
		$form = new Form($db);
	}

	$author = new User($db);
	$author->fetch($commande->user_author_id);

	if(function_exists('dol_banner_tab'))
	{
		dol_include_once('/projet/class/project.class.php');

		$linkback = '<a href="'.DOL_URL_ROOT.'/fourn/commande/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

		$morehtmlref='<div class="refidno">';
		// Ref supplier
		$morehtmlref.= $langs->trans('RefSupplier') . ' : ' . $commande->ref_supplier;
		// Thirdparty
		$morehtmlref.= $commande->thirdparty ?  '<br>'.$langs->trans('ThirdParty') . ' : ' . $commande->thirdparty->getNomUrl(1) : '';
		if (empty($conf->global->MAIN_DISABLE_OTHER_LINK) && $commande->thirdparty->id > 0) $morehtmlref.=' (<a href="'.DOL_URL_ROOT.'/fourn/commande/list.php?socid='.$commande->thirdparty->id.'&search_company='.urlencode($commande->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
		// Project
		if (! empty($conf->projet->enabled))
		{
			dol_include_once('/projet/class/project.class.php');
			$langs->load("projects");
			$morehtmlref.='<br>'.$langs->trans('Project') . ' : ';

			if (! empty($commande->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($commande->fk_project);
				$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id=' . $commande->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
				$morehtmlref.=$proj->ref;
				$morehtmlref.='</a>';
				if(! empty($proj->title)) $morehtmlref.=' - '.$proj->title;
			}
		}
		$morehtmlref.='</div>';

		dol_banner_tab($commande, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	}


	/*
	 *	COMMANDE
	 */
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border tableforfield centpercent">';

	if(! function_exists('dol_banner_tab'))
	{
		// Ref
		print '<tr><td>'.$langs->trans("Ref").'</td>';
		print '<td colspan="2">';
		print $form->showrefnav($commande,'ref','',1,'ref','ref');
		print '</td>';
		print '</tr>';

		// Supplier/ThirdParty
		print '<tr><td>'.$langs->trans("Supplier")."</td>";
		print '<td colspan="2">' . $commande->thirdparty->getNomUrl(1, 'supplier') . '</td>';
		print '</tr>';

		// Status
		print '<tr>';
		print '<td>'.$langs->trans("Status").'</td>';
		print '<td colspan="2">';
		print $commande->getLibStatut(4);
		print "</td></tr>";
	}

	// DATE
	if ($commande->methode_commande_id > 0)
	{
		print '<tr><td class="titlefield">' . $langs->trans("Date") . '</td><td colspan="2">';
		if ($commande->date_commande) {
			print dol_print_date($commande->date_commande, "dayhour") . "\n";
		}
		print "</td></tr>";

		if ($commande->methode_commande)
		{
			print '<tr><td>' . $langs->trans("Method") . '</td><td colspan="2">' . $commande->getInputMethod() . '</td></tr>';
		}
	}

	// AUTHOR
	print '<tr><td>' . $langs->trans("AuthorRequest") . '</td>';
	print '<td colspan="2">' . $author->getNomUrl(1) . '</td>';
	print '</tr>';

	print '</table>';

	print '</div>';
	print '<div class="fichehalfright">';
	print '<div class="ficheaddleft">';
	print '<div class="underbanner clearboth"></div>';

	print '</div>'; // .ficheaddleft
	print '</div>'; // .fichehalfright
	print '</div>'; // .fichecenter

	print '<div class="clearboth"></div><br>';
}

/**
 * Remonte les informations des équipements liées aux lignes de la commande fournisseur
 * @param PDO $PDOdb
 * @param CommandeFournisseur $commandefourn
 * @return array  tableau d'import des équipements
 */
function _loadDetail(&$PDOdb,&$commandefourn){

	global $langs;
	$TImport = array();

	foreach($commandefourn->lines as $line){

		$sql = "SELECT ca.rowid as idline,ca.serial_number,p.ref,p.rowid, ca.fk_commandedet, ca.fk_warehouse, ca.imei, ca.firmware,ca.lot_number,ca.weight_reel,ca.weight_reel_unit, ca.dluo
			FROM ".MAIN_DB_PREFIX."commande_fournisseurdet_asset as ca
				LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = ca.fk_product)
			WHERE ca.fk_commandedet = ".$line->id."
				ORDER BY ca.rang ASC";

		$resultSet = $PDOdb->Execute($sql);
		if($resultSet) {

			while ($PDOdb->Get_line()) {
				$TImport[] = array(
						'ref'                             => $PDOdb->Get_field('ref')
						, 'numserie'                      => $PDOdb->Get_field('serial_number')
						, 'lot_number'                    => $PDOdb->Get_field('lot_number')
						, 'quantity'                      => $PDOdb->Get_field('weight_reel')
						, 'quantity_unit'                 => $PDOdb->Get_field('weight_reel_unit')
						, 'imei'                          => $PDOdb->Get_field('imei')
						, 'firmware'                      => $PDOdb->Get_field('firmware')
						, 'fk_product'                    => $PDOdb->Get_field('rowid')
						, 'fk_warehouse'                  => $PDOdb->Get_field('fk_warehouse')
						, 'dluo'                          => $PDOdb->Get_field('dluo')
						, 'commande_fournisseurdet_asset' => $PDOdb->Get_field('idline')
				);
			}
		} else{
			setEventMessage($langs->trans('ErrorAtResultSet'), 'resultSet');
		}
	}

	return $TImport;
}

/**
 * Fonction dupliquée depuis le fichier reception.php
 * @param        $PDOdb
 * @param        array $TImport
 * @param        CommandeFournisseur $commandefourn
 * @param        string $refproduit
 * @param        string $numserie
 * @param        string $imei
 * @param        string $firmware
 * @param        string $lot_number
 * @param        int $quantity
 * @param        int $quantity_unit
 * @param        null   $dluo
 * @param        null   $k
 * @var          Entrepot $entrepot
 * @param        null   $entrepot
 * @param        string $comment
 * @return       mixed
 */
function _addCommandedetLine(&$PDOdb,&$TImport,&$commandefourn,$refproduit,$numserie,$imei,$firmware,$lot_number,$quantity,$quantity_unit,$dluo=null,$k=null,$entrepot=null,$comment=''){
	global $db, $conf, $user, $langs;

	//Charge le produit associé à l'équipement
	$prodAsset = new Product($db);
	$prodAsset->fetch('',$refproduit);

	//TODO incompréhensible - Cette notion est dispo depuis la 3.9 mettre à jour
	//Récupération de l'identifiant de la ligne d'expédition concernée par le produit
	foreach($commandefourn->lines as $commandeline){
		if($commandeline->fk_product == $prodAsset->id){
			$fk_line = $commandeline->id;
		}
	}

	if (!empty($TLine[$k])) {
		if ($numserie != $TLine[$k]['numserie']) {
			$line_update = true;
		}
	}
	//Sauvegarde (ajout/MAJ) des lignes de détail d'expédition
	$recepdetail = new TRecepDetail;

	$fk_line_receipt = !empty($TLine[$k]['commande_fournisseurdet_asset']) ? (int)$TLine[$k]['commande_fournisseurdet_asset'] : 0;
	if($fk_line_receipt>0){
		$recepdetail->load($PDOdb, $fk_line_receipt);
		$lineFound = true;
	}
	else {
		$lineFound = false;
	}

	$keys = array_keys($TImport);
	$rang = $keys[count($keys)-1];

	$recepdetail->fk_commandedet = $fk_line;
	$recepdetail->fk_product = $prodAsset->id;
	$recepdetail->rang = $rang + 1;
	$recepdetail->set_date('dluo', ($dluo) ? $dluo : date('Y-m-d H:i:s'));
	$recepdetail->lot_number = $lot_number;
	$recepdetail->weight_reel = $quantity;
	$recepdetail->weight = $quantity;
	$recepdetail->weight_unit = $quantity_unit;
	$recepdetail->weight_reel_unit = $quantity_unit;
	$recepdetail->serial_number = $numserie;
	$recepdetail->imei = $imei;
	$recepdetail->firmware = $firmware;
	$recepdetail->fk_warehouse = $entrepot;
	$resultSave = $recepdetail->save($PDOdb);
	if($resultSave) {

		$currentLine = array(
				'ref'                             => $prodAsset->ref
				, 'numserie'                      => $numserie
				, 'lot_number'                    => $lot_number
				, 'quantity'                      => $quantity
				, 'quantity_unit'                 => $quantity_unit
				, 'fk_product'                    => $prodAsset->id
				, 'fk_warehouse'                  => $entrepot
				, 'imei'                          => $imei
				, 'firmware'                      => $firmware
				, 'dluo'                          => $recepdetail->get_date('dluo', 'Y-m-d H:i:s')
				, 'commande_fournisseurdet_asset' => $recepdetail->getId()
		);

		//Rempli le tableau utilisé pour l'affichage des lignes
		($lineFound) ? $TImport[$k] = $currentLine : $TImport[] = $currentLine;
	}else{
		setEventMessage($langs->trans('ErrorAtSaveObjectRecepDetail'));
	}
	return $TImport;

}

/**
 * @param array $array
 * @param int $idprod
 * @return bool|mixed
 */
function searchProductInCommandeLine($array, $idprod)
{
	$line=false;
	foreach($array as $item)
	{
		if($item->fk_product == $idprod)
		{
			$line = $item;
			break;
		}
	}
	return $line;
}

/**
 * @param array $TImport
 * @param Commande $commande
 * @param TFormCore $form
 */
function _show_product_ventil(&$TImport, &$commande,&$form) {
	global $langs, $db, $conf, $hookmanager;
	$langs->load('dispatch@dispatch');

	$TProductCount = array();
	foreach($TImport as &$line) {
		if(empty($TProductCount[$line['fk_product']]))$TProductCount[$line['fk_product']] = 0;
		$TProductCount[$line['fk_product']] += $line['quantity'];
	}

	?>
	<style type="text/css">
		input.text_readonly {
			background-color: #eee;
		}
	</style>
	<?php


	print '<table class="noborder" width="100%">';

	// Set $products_dispatched with qty dispatched for each product id
	$products_dispatched = array();
	$sql = "SELECT cfd.fk_product, sum(cfd.qty) as qty";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet as l on l.rowid = cfd.fk_commandefourndet";
	$sql.= " WHERE cfd.fk_commande = ".$commande->id;
	$sql.= " GROUP BY cfd.fk_product";

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		if ($num)
		{
			while ($i < $num)
			{
				$objd = $db->fetch_object($resql);
				$products_dispatched[$objd->fk_product] = price2num($objd->qty, 5);
				$i++;
			}
		}
		$db->free($resql);
	} else{
		setEventMessage($langs->trans('ErrorAtResultSet'), 'resql');
		dol_syslog(__METHOD__.' $resql='.var_export($resql,true), LOG_ERR);
	}

	$sql = "SELECT l.fk_product, SUM(l.qty * l.subprice) / SUM(l.qty) AS subprice, SUM(l.qty * l.remise_percent) / SUM(l.qty) AS remise_percent, SUM(l.qty) as qty,";
	$sql.= " p.ref, p.label, pe.type_asset, p.fk_default_warehouse";

	if(DOL_VERSION>=3.8) {
		$sql.=", p.tobatch";
	}


	$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseurdet as l";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON l.fk_product=p.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields as pe ON pe.fk_object=p.rowid";
	$sql.= " WHERE l.fk_commande = ".$commande->id;
	$sql.= " AND l.fk_product > 0";
	if (!empty($conf->global->DISPATCH_SKIP_SERVICES)) $sql.= ' AND l.product_type = 0';
	$sql.= " GROUP BY l.fk_product, pe.type_asset";	// Calculation of amount dispatched is done per fk_product so we must group by fk_product
	$sql.= " ORDER BY p.ref, p.label";

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		if ($num)
		{
			print '<tr class="liste_titre">';

			print '<td>'.$langs->trans("Description").'</td>';

			// NEW CODE FOR PRICE
			if($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE) print '<td align="right">'.$langs->trans("SupplierQtyPrice").'</td>';
			if($conf->global->DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION) print '<td align="right">'.$langs->trans("TotalPriceOrdered").'</td>';
			if($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE) print '<td align="right">'.$langs->trans("GenerateSupplierTarif").'</td>';

			print '<td align="right">'.$langs->trans("QtyOrdered").'</td>';
			print '<td align="right">'.$langs->trans("QtyDispatchedShort").'</td>';
			print '<td align="right" rel="QtyToDispatchShort">'.$langs->trans("QtyToDispatchShort").'</td>';

			$formproduct=new FormProduct($db);
			$formproduct->loadWarehouses();

			print '<td align="right">'.$langs->trans("Warehouse").' : '.$formproduct->selectWarehouses(GETPOST('id_entrepot'), 'id_entrepot','',0,0,0,'',0,1).'</td>';
			print '<td align="right">'.$langs->trans("SerializedProduct").'</td>';
			print "</tr>\n";

			?>
			<script type="text/javascript">
				$(document).ready(function() {

					$('#id_entrepot').change(function() {
						$('td[rel=entrepot] select').val($(this).val());
					});

					$('td[rel=entrepot] select').change(function() {

						var fk_product = $(this).closest('td').attr('fk_product');
						$('#dispatchAsset td[rel=entrepotChild][fk_product='+fk_product+'] select').val($(this).val());

					});

				});
			</script>

			<?php

		}

		$nbproduct=0;

		$TOrderLine = GETPOST('TOrderLine', 'array');

		$var=true;
		while ($i < $num)
		{
			$objp = $db->fetch_object($resql);
			$serializedProduct = 0;

			if (!empty($TProductCount[$objp->fk_product])) {
				$serializedProduct = 1;
			}

			if(isset($TOrderLine[$objp->fk_product]['qty']) && !isset($post_create_ventilation_expe)) {
				$remaintodispatch = $TOrderLine[$objp->fk_product]['qty'];
			} else {
				$remaintodispatch=price2num($objp->qty - ((float) $products_dispatched[$objp->fk_product]), 5);	// Calculation of dispatched
			}

			if ($remaintodispatch < 0) $remaintodispatch=0;

			$nbproduct++;

			$var=!$var;

			// To show detail cref and description value, we must make calculation by cref
			if (DOL_VERSION<3.8 || (empty($conf->productbatch->enabled)) || $objp->tobatch==0)
			{
				$suffix='_'.$i;
			} else {
				$suffix='_0_'.$i;
			}


			print "\n";
			print '<!-- Line '.$suffix.' -->'."\n";
			print '<tr class="oddeven">';

			$linktoprod='<a href="'.DOL_URL_ROOT.'/product/fournisseurs.php?id='.$objp->fk_product.'">'.img_object($langs->trans("ShowProduct"),'product').' '.$objp->ref.'</a>';
			$linktoprod.=' - '.$objp->label."\n";


			print '<td>' . $linktoprod . '</td>';

			$up_ht_disc=$objp->subprice;
			if (! empty($objp->remise_percent) && empty($conf->global->STOCK_EXCLUDE_DISCOUNT_FOR_PMP)) $up_ht_disc=price2num($up_ht_disc * (100 - $objp->remise_percent) / 100, 'MU');

			// NEW CODE FOR PRICE
			$exprice = $objp->subprice * $objp->qty;
			if($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE)
			{
				print '<td align="right">';
				print '<input type="text" id="TOrderLine['.$objp->fk_product.'][supplier_qty]" name="TOrderLine['.$objp->fk_product.'][supplier_qty]" size="8" value="'.$objp->qty.'">';
				print '</td>';
			}
			if($conf->global->DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION)
			{
				print '<td align="right">';
				print '<input type="text" id="TOrderLine['.$objp->fk_product.'][supplier_price]" name="TOrderLine['.$objp->fk_product.'][supplier_price]" size="8" value="'.$exprice.'">';
				print '</td>';
			}
			if($conf->global->DISPATCH_CREATE_SUPPLIER_PRICE)
			{
				print '<td align="right">';
				print '<input type="checkbox" id="TOrderLine['.$objp->fk_product.'][generate_supplier_tarif]" name="TOrderLine['.$objp->fk_product.'][generate_supplier_tarif]">';
				print '</td>';
			}

			// Qty ordered
			print '<td align="right">'.$objp->qty.'</td>';

			// Already dispatched
			print '<td align="right">'.$products_dispatched[$objp->fk_product].'</td>';

			// Dispatch
			print '<td align="right">';

			if($remaintodispatch==0) {
				echo $form->texteRO('', 'TOrderLine['.$objp->fk_product.'][qty]', $remaintodispatch, 5,30);
			}
			else {
				echo $form->texte('', 'TOrderLine['.$objp->fk_product.'][qty]', $remaintodispatch, 5,30);
			}

			print '</td>';


			print '<td align="right" rel="entrepot" fk_product="'.$objp->fk_product.'">';

			$formproduct=new FormProduct($db);
			$formproduct->loadWarehouses();

			if (count($formproduct->cache_warehouses)>1)
			{
				print $formproduct->selectWarehouses(($objp->fk_default_warehouse) ? $objp->fk_default_warehouse : '', 'TOrderLine['.$objp->fk_product.'][entrepot]','',1,0,$objp->fk_product,'',0,1);
			}
			elseif  (count($formproduct->cache_warehouses)==1)
			{
				print $formproduct->selectWarehouses(($objp->fk_default_warehouse) ? $objp->fk_default_warehouse : '', 'TOrderLine['.$objp->fk_product.'][entrepot]','',0,0,$objp->fk_product,'',0,1);
			}
			else
			{
				print $langs->trans("NoWarehouseDefined");
			}
			print "</td>\n";

			print '<td align="right">';

			if($remaintodispatch==0) {
				print $langs->trans('Yes').img_info('SerializedProductInfo');
			} elseif($objp->type_asset > 0){
				print $form->btsubmit($langs->trans('SerializeThisProduct'), 'ToDispatch['.$objp->fk_product.']', '', 'butAction').img_info($langs->trans('SerializeThisProductInfo'));
			}

			print $form->hidden('TOrderLine['.$objp->fk_product.'][fk_product]', $objp->fk_product);
			print $form->hidden('TOrderLine['.$objp->fk_product.'][serialized]', $serializedProduct);
			print $form->hidden('TOrderLine['.$objp->fk_product.'][subprice]', $objp->subprice);

			print '</td>';
			print "</tr>\n";

			$i++;
		}
		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}

	$parameters=array('colspan'=>' colspan="4" ');
	$hookmanager->executeHooks('formObjectOptions',$parameters, $commande, $action);

	print "</table>\n";
	print "<br/>\n";

	if(! empty($conf->global->DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_FROM_FIRST_INPUT)) {
		printJSSerialNumberAutoDeduce();
	}
}

/**
 * Function that print serial number autodeduced
 */
function printJSSerialNumberAutoDeduce() {

	global $langs;
	?>
	<script>

		function setSerialNumbers(fkProduct, TMatches) {

			var prefix = TMatches[1] ? TMatches[1] : ''; // Si undefined car nombre pur => chaine vide
			var currentNum = TMatches[2];
			var i = 1;
			var numSize = currentNum.length;
			var count = parseInt(currentNum);

			$('table#dispatchAsset tr[data-fk-product='+fkProduct+']').not(':first').each(function() {
				var lineID = $(this).attr('id').replace('dispatchAssetLine', '');
				var elem = $(this).find('input#TLine\\['+lineID+'\\]\\[numserie\\]');
				var suffixCount = count + i;
				var suffix = (new String(suffixCount)).padStart(numSize, '0');
				var newVal = prefix + suffix;

				elem.val(newVal);

				i++;
			});
		}

		function setSerialNumberListener(fkProduct, TElemTR) {
			var lineID = TElemTR.first().attr('id').replace('dispatchAssetLine', '');
			var inputElem = $('input#TLine\\['+lineID+'\\]\\[numserie\\]'); // Doublement échapper les crochets faisant partie d'un ID ou d'une classe et non du sélecteur

			inputElem.on('change', function() {

				$('span#setSerialNumbers'+fkProduct).remove(); // On supprime le lien même s'il existe pour éventuellement le recréer avec un nouveau listener

				var TMatches = $(this).val().match(/^(.*[^0-9])?([0-9]+)$/); // On détermine si le numéro de série finit par un nombre

				if(TMatches instanceof Array && TMatches.length > 0) { // String.match() retourne un tableau si des correspondances sont trouvées

					$('<span id="setSerialNumbers'+fkProduct+'"> <a href="javascript:;"><?php print dol_escape_js($langs->trans('CalculateFollowingSerialNumbers')); ?></a></span>')
						.insertAfter('input#TLine\\['+lineID+'\\]\\[commande_fournisseurdet_asset\\]')
						.on('click', function() {
							setSerialNumbers(fkProduct, TMatches);
						});
				}
			});
		}

		$(document).ready(function() {

			var TProducts = [];

			$('table#dispatchAsset tr.dispatchAssetLine').each(function() {
				var fkProduct = parseInt($(this).data('fk-product'));

				if(! TProducts.includes(fkProduct)) {
					TProducts.push(fkProduct);
				}
			});

			for(var fkProduct of TProducts) { // Equivalent JS de foreach($TProducts as $fkProduct) en PHP

				var TElemTR = $('table#dispatchAsset tr[data-fk-product='+fkProduct+']');

				if(TElemTR.length > 1) { // Si au moins 2 équipements à dispatcher issus du même produit
					setSerialNumberListener(fkProduct, TElemTR);
				}
			}
		});
	</script>

	<?php
}

/**
 * @param int $entityId
 * @param int $socid
 * @return bool
 */
function is_supplier_linked($entityId,$socid){
	global $db;

	$sql = "SELECT DISTINCT te.rowid FROM " . MAIN_DB_PREFIX . "societe AS s ";
	$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "thirdparty_entity AS te ON s.rowid = te.fk_soc ";
	$sql .= " WHERE te.entity=" . $entityId;
	$sql .= " AND te.fk_soc =" . $socid;

	$res = $db->query($sql);
	return $db->num_rows($res) > 0;
}

/** copyed from reception.php */
function _list_already_dispatched(&$commande) {
    global $db, $langs, $conf, $user;

    // List of lines already dispatched
    $sql = "SELECT p.ref, p.label,";
    if ((float) DOL_VERSION <= 6.0) $sql.= " e.rowid as warehouse_id, e.label as entrepot,";
    else $sql.= " e.rowid as warehouse_id, e.ref as entrepot,";
    $sql.= " cfd.rowid as dispatchlineid, cfd.fk_product, cfd.qty";
    if ((float) DOL_VERSION > 3.7) $sql .= ", cfd.eatby, cfd.sellby, cfd.batch, cfd.comment, cfd.status";
    $sql.= " FROM ".MAIN_DB_PREFIX."product as p,";
    $sql.= " ".MAIN_DB_PREFIX."commande_fournisseur_dispatch as cfd";
    $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON cfd.fk_entrepot = e.rowid";
    $sql.= " WHERE cfd.fk_commande = ".$commande;
    $sql.= " AND cfd.fk_product = p.rowid";
    $sql.= " ORDER BY cfd.rowid ASC";

    $resql = $db->query($sql);
    if ($resql)
    {
        $num = $db->num_rows($resql);
        $i = 0;

        if ($num > 0)
        {
            print "<br/>\n";

            print load_fiche_titre($langs->trans("ReceivingForSameOrder"));

            print '<table class="noborder" width="100%">';

            print '<tr class="liste_titre">';
            print '<td>'.$langs->trans("Description").'</td>';
            if (! empty($conf->productbatch->enabled) && (float) DOL_VERSION > 3.7)
            {
                print '<td>'.$langs->trans("batch_number").'</td>';
                print '<td>'.$langs->trans("l_eatby").'</td>';
                print '<td>'.$langs->trans("l_sellby").'</td>';
            }
            print '<td align="right">'.$langs->trans("QtyDispatched").'</td>';
            print '<td></td>';
            print '<td>'.$langs->trans("Warehouse").'</td>';
            print '<td>'.$langs->trans("Comment").'</td>';
            if (! empty($conf->global->SUPPLIER_ORDER_USE_DISPATCH_STATUS) && (float) DOL_VERSION > 3.7) print '<td align="center" colspan="2">'.$langs->trans("Status").'</td>';
            print "</tr>\n";

            $var=false;

            while ($i < $num)
            {
                $objp = $db->fetch_object($resql);

                print "<tr>";
                print '<td>';
                print '<a href="'.DOL_URL_ROOT.'/product/fournisseurs.php?id='.$objp->fk_product.'">'.img_object($langs->trans("ShowProduct"),'product').' '.$objp->ref.'</a>';
                print ' - '.$objp->label;
                print "</td>\n";

                if (! empty($conf->productbatch->enabled) && (float) DOL_VERSION > 3.7)
                {
                    print '<td>'.$objp->batch.'</td>';
                    print '<td>'.dol_print_date($db->jdate($objp->eatby),'day').'</td>';
                    print '<td>'.dol_print_date($db->jdate($objp->sellby),'day').'</td>';
                }

                // Qty
                print '<td align="right">'.$objp->qty.'</td>';
                print '<td>&nbsp;</td>';

                // Warehouse
                print '<td>';
                $warehouse_static = new Entrepot($db);
                $warehouse_static->id=$objp->warehouse_id;
                $warehouse_static->libelle=$objp->entrepot;
                print $warehouse_static->getNomUrl(1);
                print '</td>';

                // Comment
                print '<td>'.dol_trunc($objp->comment).'</td>';

                // Status
                if (! empty($conf->global->SUPPLIER_ORDER_USE_DISPATCH_STATUS) && (float) DOL_VERSION > 3.7)
                {
                    print '<td align="right">';
                    $supplierorderdispatch->status = (empty($objp->status)?0:$objp->status);
                    //print $supplierorderdispatch->status;
                    print $supplierorderdispatch->getLibStatut(5);
                    print '</td>';

                    // Add button to check/uncheck disaptching
                    print '<td align="center">';
                    if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && empty($user->rights->fournisseur->commande->receptionner))
                        || (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && empty($user->rights->fournisseur->commande_advance->check))
                    )
                    {
                        if (empty($objp->status))
                        {
                            print '<a class="button buttonRefused" href="#">'.$langs->trans("Approve").'</a>';
                            print '<a class="button buttonRefused" href="#">'.$langs->trans("Deny").'</a>';
                        }
                        else
                        {
                            print '<a class="button buttonRefused" href="#">'.$langs->trans("Disapprove").'</a>';
                            print '<a class="button buttonRefused" href="#">'.$langs->trans("Deny").'</a>';
                        }
                    }
                    else
                    {
                        $disabled='';
                        if ($commande->statut == 5) $disabled=1;
                        if (empty($objp->status))
                        {
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=checkdispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Approve").'</a>';
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=denydispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Deny").'</a>';
                        }
                        if ($objp->status == 1)
                        {
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=uncheckdispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Reinit").'</a>';
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=denydispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Deny").'</a>';
                        }
                        if ($objp->status == 2)
                        {
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=uncheckdispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Reinit").'</a>';
                            print '<a class="button'.($disabled?' buttonRefused':'').'" href="'.$_SERVER["PHP_SELF"]."?id=".$id."&action=checkdispatchline&lineid=".$objp->dispatchlineid.'">'.$langs->trans("Approve").'</a>';
                        }
                    }
                    print '</td>';
                }

                print "</tr>\n";

                $i++;
                $var=!$var;
            }
            $db->free($resql);

            print "</table>\n";
        }
    }
    else
    {
        dol_print_error($db);
    }
}

/**
 * passe l'extrafield de l'expedition
 * @param int $idexpe
 * @param int $shipmentEntity
 */
function _set_treated_expedition_extrafield($idexpe, $shipmentEntity) {
	global $db, $user, $conf;

	$sql  = "select entity from ".MAIN_DB_PREFIX."expedition where rowid =".$idexpe;
	$re = $db->query($sql);
    $obj = $db->fetch_object($re);

   // var_dump($obj->entity);

  //  var_dump($conf->entity,$shipmentEntity);
	$backEntity = $conf->entity;

	$conf->entity = $obj->entity;


	$currentExp = new Expedition($db);
	$res = $currentExp->fetch(intval($idexpe));



   // var_dump($conf->entity, $res, $idexpe);exit();

	$extra = new ExtraFields($db);
	$resultExtra = $extra->fetch_name_optionals_label($currentExp->table_element);
	//var_dump($resultExtra);exit();
	if($resultExtra) {
		$currentExp->array_options['options_customer_treated_shipment'] = 1;
		$currentExp->updateExtraField('customer_treated_shipment');
	}else {
		dol_syslog(__METHOD__.' $resultExtra='.var_export($resultExtra,true), LOG_ERR);
	}

	$conf->entity = $backEntity;
    //var_dump($conf->entity);exit();
}

/**
 * @param array $shipments
 * @return bool
 */
function _isTreatedExpAlreadyExists($shipments) {
	global $db;

	//array_map sur array d'object
	$arrShimpemtsId = array_map(function($shipment){return $shipment->rowid;},$shipments);

	$sql = 'SELECT * FROM '.MAIN_DB_PREFIX . 'expedition_extrafields WHERE fk_object in (' . implode(",",$arrShimpemtsId) . ') AND customer_treated_shipment = 1';
	$resultset= $db->query($sql);

	if($resultset){
		return $db->num_rows($resultset) > 0;
	}else return -1;


}


