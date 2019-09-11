<?php

	require('config.php');

	dol_include_once('/expedition/class/expedition.class.php' );
	dol_include_once('/product/class/product.class.php' );
	dol_include_once('/dispatch/class/dispatchdetail.class.php' );
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/core/lib/admin.lib.php' );
	dol_include_once('/core/lib/sendings.lib.php' );
	dol_include_once('/core/lib/product.lib.php');
	dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');


	$langs->load('orders');
	$PDOdb=new TPDOdb;

	$id = GETPOST('id');
	$ref = GETPOST('ref');

	$expedition = new Expedition($db);
	$expedition->fetch($id, $ref);
	
	$action = GETPOST('action');
	$TImport = _loadDetail($PDOdb, $expedition);

	$hookmanager->initHooks(array('shipmentdispatchcard'));

	$parameters = array();
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $TImport, $action);
	if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	if(empty($reshook))
	{
		switch ($action)
		{
			case 'importfile':
				// TODO à nettoyer, dégueulasse et spécifique (ex. IMEI ?!) - MdLL, 12/03/2019
				if (isset($_FILES['file1']) && $_FILES['file1']['name'] != '') {
					$f1 = file($_FILES['file1']['tmp_name']);

					$TImport = array();

					foreach ($f1 as $line) {
						list($ref, $numserie, $imei, $firmware) = str_getcsv($line, ';', '"');

						$TImport = _addExpeditiondetLine($PDOdb, $TImport, $expedition, $numserie);
					}
				}

				break;

			case 'deleteline':
				array_splice($TImport, (int)GETPOST('k'), 1); // Supprime le k-ième élément et décale les suivants dans le tableau

				$rowid = GETPOST('rowid');

				$dispatchdetail = new TDispatchDetail;
				$dispatchdetail->load($PDOdb, $rowid);
				$dispatchdetail->delete($PDOdb);

				setEventMessage('Ligne supprimée');

				break;

			case 'save':
				$numserie = GETPOST('numserie');

				$asset = new TAsset;
				if ($asset->loadBy($PDOdb, $numserie, 'serial_number')) {

					_addExpeditiondetLine($PDOdb, $TImport, $expedition, $numserie);

					setEventMessage('Numéro de série enregistré');
				} else {
					setEventMessage('Aucun équipement pour ce numéro de série', 'errors');
				}

				header('location:'.$_SERVER['PHP_SELF'].'?id='.$id);
                		exit;

				break;
		}
	}


	fiche($PDOdb,$expedition, $TImport);

	function _loadDetail(&$PDOdb, &$expedition)
	{
		$TImport = array();

		foreach($expedition->lines as $line)
		{
			$sql = "SELECT ea.rowid as fk_expeditiondet_asset, a.rowid as id,a.serial_number,p.ref,p.rowid, ea.fk_expeditiondet, ea.lot_number, ea.weight_reel, ea.weight_reel_unit, ea.is_prepared
					FROM ".MAIN_DB_PREFIX."expeditiondet_asset as ea
						LEFT JOIN ".MAIN_DB_PREFIX.ATM_ASSET_NAME." as a ON ( a.rowid = ea.fk_asset)
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = a.fk_product)
					WHERE ea.fk_expeditiondet = ".$line->line_id."
						ORDER BY ea.rang ASC";

			$PDOdb->Execute($sql);
			$Tres = $PDOdb->Get_All();
			
			foreach ($Tres as $res)
			{
				$TImport[] = array(
					'fk_expeditiondet_asset'=>$res->fk_expeditiondet_asset
					,'ref'=>$res->ref
					,'numserie'=>$res->serial_number
					,'fk_product'=>$res->rowid
					,'fk_expeditiondet'=>$res->fk_expeditiondet
					,'lot_number'=>$res->lot_number
					,'quantity'=>$res->weight_reel
					,'quantity_unit'=>$res->weight_reel_unit
					,'is_prepared'=>$res->is_prepared
				);
			}
		}

		return $TImport;
	}

	function _addExpeditiondetLine(&$PDOdb,&$TImport,&$expedition,$numserie)
	{
		global $db;
		
		//Charge l'asset lié au numéro de série dans le fichier
		$asset = new TAsset;
		if($asset->loadBy($PDOdb,$numserie,'serial_number')){
			
			//Charge le produit associé à l'équipement
			$prodAsset = new Product($db);
			$prodAsset->fetch($asset->fk_product);

			$fk_line_expe = (int)GETPOST('lineexpeditionid');
			if( empty($fk_line_expe) ) { 
				//Récupération de l'indentifiant de la ligne d'expédition concerné par le produit
				foreach($expedition->lines as $expeline){
					if($expeline->fk_product == $prodAsset->id){
						$fk_line_expe = $expeline->line_id;
					}
				}
			}
			
			//Sauvegarde (ajout/MAJ) des lignes de détail d'expédition
			$dispatchdetail = new TDispatchDetail;

			//Si déjà existant => MAj
			$PDOdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."expeditiondet_asset WHERE fk_asset = ".$asset->rowid." AND fk_expeditiondet = ".$fk_line_expe." ");
			if($PDOdb->Get_line()){
				$dispatchdetail->load($PDOdb,$PDOdb->Get_field('rowid'));
			}

			$keys = array_keys($TImport);
			$rang = $keys[count($keys)-1];

			$dispatchdetail->fk_expeditiondet = $fk_line_expe;
			$dispatchdetail->fk_asset = $asset->rowid;
			$dispatchdetail->rang = $rang;
			$dispatchdetail->lot_number = $asset->lot_number;
			$dispatchdetail->weight = (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_value;
			$dispatchdetail->weight_reel = (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_value;
			$dispatchdetail->weight_unit = $asset->contenancereel_units;
			$dispatchdetail->weight_reel_unit = $asset->contenancereel_units;
			$dispatchdetail->is_prepared = 0;

			$fk_expeditiondet_asset = $dispatchdetail->save($PDOdb);

			if($fk_expeditiondet_asset > 0)
			{
				// Remplit le tableau utilisé pour l'affichage des lignes
				$TImport[] =array(
					'fk_expeditiondet_asset'=>$fk_expeditiondet_asset
					,'ref'=>$prodAsset->ref
					,'numserie'=>$numserie
					,'fk_product'=>$prodAsset->id
					,'fk_expeditiondet'=>$fk_line_expe
					,'lot_number'=>$asset->lot_number
					,'quantity'=> (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_value
					,'quantity_unit'=> (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_units
					,'is_prepared' => 0
				);
			}
		}
		//pre($TImport,true);
		return $TImport;

	}


function fiche(&$PDOdb,&$expedition, &$TImport)
{
	global $langs, $db, $conf;

	llxHeader();

	$head = shipping_prepare_head($expedition);
	
	$title=$langs->trans("Shipment");
	dol_fiche_head($head, 'dispatch', $title, -1, 'sending');
	
	enteteexpedition($expedition);
	
	if($expedition->statut == 0 && ! empty($conf->global->DISPATCH_USE_IMPORT_FILE))
	{
		//Form pour import de fichier
		echo '<br>';

		$form=new TFormCore('auto','formimport','post', true);
		
		echo $form->hidden('action', 'importfile');
		echo $form->hidden('id', $expedition->id);
		
		echo $form->fichier('Fichier à importer','file1','',80);
		echo $form->btsubmit('Envoyer', 'btsend');

		$form->end();
	}

	tabImport($TImport,$expedition);

	dol_fiche_end();

	llxFooter();
}


function tabImport(&$TImport,&$expedition)
{
	global $langs, $db, $conf, $hookmanager;

	$form = new TFormCore('auto', 'formaddasset','post');
	echo $form->hidden('action','save');
	echo $form->hidden('mode','addasset');
	echo $form->hidden('id', $expedition->id);

	$PDOdb=new TPDOdb;
	
	print count($TImport).' équipement(s) dans votre expédition';

	$fullColspan = 4;
	if(! empty($conf->global->USE_LOT_IN_OF)) $fullColspan++;
	if(! empty($conf->global->DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED)) $fullColspan++;
?>
	<br>
	<table width="100%" class="noborder">
		<tr class="liste_titre">
			<td>Produit</td>
<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
			<td>Numéro de Lot</td>
<?php } ?>
			<td>Numéro de série</td>
			<td>Quantité</td>
<?php if(! empty($conf->global->DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED)) { ?>
			<td><?php echo $form->checkbox1('', 'allPrepared', 1, false, '','allPreparedCheckbox'); ?> Préparé</td>
<?php }

		$parameters = array('fullColspan' => &$fullColspan);
		$reshook = $hookmanager->executeHooks('addColumnsHeader', $parameters, $TImport, $action);
		if(empty($reshook)) echo $hookmanager->resPrint;
?>
			<td>&nbsp;</td>
		</tr>
		
	<?php
		$prod = new Product($db);
		
		$form->Set_typeaff('view');

		$canEdit = $expedition->statut == 0 || (! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE) && $expedition->statut == 1);

		if(! empty($TImport)){

			foreach ($TImport as $k=>$line) {
							
				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(!empty( $line['fk_product']))$prod->fetch($line['fk_product']);
					else $prod->fetch('', $line['ref']);
				} 		
				
				$asset = new TAsset;
				$asset->loadBy($PDOdb,$line['numserie'],'serial_number');
				$asset->load_asset_type($PDOdb);
				
				$assetLot = new TAssetLot;
				$assetLot->loadBy($PDOdb,$line['lot_number'],'lot_number');
				
				$Trowid = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."expeditiondet_asset",array('fk_asset'=>$asset->rowid,'fk_expeditiondet'=>$line['fk_expeditiondet']));
				?><tr class="oddeven">
					<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_expeditiondet_asset]', $line['fk_expeditiondet_asset']).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref).$form->hidden('TLine['.$k.'][fk_expeditiondet]', $line['fk_expeditiondet']) ?></td>
<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					<td><a href="<?php echo dol_buildpath('/' . ATM_ASSET_NAME . '/fiche_lot.php?id='.$assetLot->rowid,1); ?>"><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30); ?></a></td>
<?php } ?>
					<td><a href="<?php echo dol_buildpath('/' . ATM_ASSET_NAME . '/fiche.php?id='.$asset->rowid,1); ?>"><?php echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30); ?></a></td>
					<td><?php echo $line['quantity'] . ' ' . (($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($line['quantity_unit'],$asset->assetType->measuring_units)); ?></td>
<?php
		if(! empty($conf->global->DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED))
		{
			if($expedition->statut < Expedition::STATUS_CLOSED) $form->Set_typeaff('edit');
?>
					<td>
						<?php echo $form->checkbox1('', 'TLine[' . $k . '][is_prepared]', 1, ! empty($line['is_prepared']), '', 'isPreparedCheckbox'); ?>
					</td>
<?php
			if($expedition->statut < Expedition::STATUS_CLOSED) $form->Set_typeaff('view');
		}

		$parameters = array('line' => &$line, 'k' => &$k, 'asset' => &$asset, 'form' => &$form);
		$reshook = $hookmanager->executeHooks('addColumnsLines', $parameters, $expedition, $action);
		if(empty($reshook)) echo $hookmanager->resPrint;
?>
					<td>
						<?php 
							if($canEdit) echo '<a href="?action=deleteline&k='.$k.'&id='.$expedition->id.'&rowid='.$Trowid[0].'">'.img_delete().'</a>';
						?>
					</td>
				</tr>

				<?php
			} // foreach($TImport)
		} // if(is_array($TImport))
		else
		{
?>
				<tr><td colspan="<?php echo $fullColspan; ?>" class="center"><?php echo $langs->trans('NoShipmentAssetDetail'); ?></td></tr>
<?php
		}

		if($canEdit) {
			tabImportAddLine($PDOdb, $expedition, $form, $fullColspan);
		}
?>
	</table>
	<?php

	$form->end();

	if($canEdit || $expedition->statut < Expedition::STATUS_CLOSED) {
		printJSTabImportAddLine();
	}
}


function tabImportAddLine(&$PDOdb, &$expedition, $form, $fullColspan)
{
	global $conf, $db, $hookmanager;
	$DoliFormProduct = new FormProduct($db);

	$form->Set_typeaff('edit');
?>
				<tr class="liste_titre">
					<td colspan="<?php echo $fullColspan; ?>">Nouvel équipement</td>
				</tr>
<?php
	$TLotNumber = array('-- Selectionnez un lot --');

	$TSerialNumber = array('-- Selectionnez un équipement --');

	$sql = "SELECT ed.rowid, p.rowid as fk_product,p.ref,p.label ,ed.qty
			FROM ".MAIN_DB_PREFIX."product as p
			LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON (cd.fk_product = p.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON (ed.fk_origin_line = cd.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_asset as eda ON (eda.fk_expeditiondet = ed.rowid)
			WHERE ed.fk_expedition = ".$expedition->id."
			GROUP BY ed.rowid
			HAVING COALESCE(SUM(eda.weight_reel), 0) < ed.qty";

	$PDOdb->Execute($sql);

	if($PDOdb->Get_Recordcount() > 0) {

		$productOptions = '<option value="">-- Selectionnez un produit --</option>';

		while ($obj = $PDOdb->Get_line()) {
			$prodStatic = new Product($db);
			$prodStatic->fetch($obj->fk_product);
			if (!empty($prodStatic->array_options['options_type_asset']))
			$productOptions.= '<option value="'.$obj->rowid.'" fk-product="'.$obj->fk_product.'" qty="'.$obj->qty.'">'.$obj->ref.' - '.$obj->label.' x '.$obj->qty.'</option>';
		}
?>
				<tr>
					<td>
						<select id="lineexpeditionid" name="lineexpeditionid"><?php echo $productOptions; ?></select>
<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					</td>
					<td id="newline_lot_number" style="visibility:hidden">
						<?php echo $form->combo_sexy('', 'lot_number', $TLotNumber, ''); ?>
<?php } else { ?>
						<?php echo $form->hidden('lot_number', ''); ?>
<?php } ?>
					</td>
					<td id="newline_numserie" style="visibility:hidden"><?php echo $form->combo_sexy('','numserie',$TSerialNumber,''); ?></td>
					<td id="newline_quantity" style="visibility:hidden"><input type="number" name="quantity" id="quantity" class="text" min="0" /> <?php echo $DoliFormProduct->load_measuring_units('quantity_unit" id="quantity_unit','weight'); ?></td>
<?php if(! empty($conf->global->DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED)) { ?>
					<td>&nbsp;</td>
<?php }


		$parameters = array();
		$reshook = $hookmanager->executeHooks('addColumnsNewLine', $parameters,$expedition ,$action);
		if(empty($reshook)) echo $hookmanager->resPrint;
?>
					<td><?php echo $form->btsubmit('Ajouter', 'btaddasset'); ?></td>
				</tr>
<?php
	} // if($PDOdb->Get_Recordcount() > 0)
	else {
?>
				<tr>
					<td colspan="<?php echo $fullColspan; ?>" class="center">Tous les équipements de l'expédition ont été renseignés</td>
				</tr>
<?php
	}
}


function printJSTabImportAddLine()
{
	global $conf;

?>
	<script>
		$(document).ready(function() {

			$('#lot_number').change(function() {
				var elem = $(this);
				var lot_number = elem.val();
				var expeditionid = $('#id').val();

				$('#newline_quantity').css({ visibility: 'hidden' });

				$.ajax({
					url: 'script/interface.php',
					method: 'GET',
					data: {
						expeditionid: expeditionid,
						expeditiondetid: $('#lineexpeditionid').val(),
						lot_number: lot_number,
						productid: $('#lineexpeditionid').find(':selected').attr('fk-product'),
						type:'get',
						get:'autocomplete_asset'
					}
				}).done(function(json_results) {

					$('#numserie option').remove();
					$('#numserie').append($('<option>', {
						value: '',
						text: '-- Selectionnez un équipement --',
						selected: true
					}));
					cpt = 0;
					$.each(json_results, function(index) {
						var obj = json_results[index];
						cpt ++;
						$('#numserie').append($('<option>', {
							value: obj.serial_number,
							text: obj.serial_number + ' - ' + obj.qty + ' ' +obj.unite_string
						}));

						if(cpt == 1) { // A ne faire que pour le premier résultat
							var qtyOrder = $('#lineexpeditionid option:selected').attr('qty');
							var qtyAuto = Math.min(qtyOrder, obj.qty)
							$('#quantity').val(qtyAuto).prop('max', qtyAuto);
							if(obj.unite != 'unité(s)'){
								$('#quantity_unit').show();
								$('#units_label').remove();
								$('#quantity_unit option[value='+obj.unite+']').attr("selected","selected");
							}
							else if(! $('#quantity_unit').is(':hidden'))
							{
								$('#quantity_unit').hide();
								$('#quantity_unit option[value=0]').attr("selected","selected");
								$('#quantity').after('<span id="units_label"> unité(s)</span>');
							}
						}
					});

					if((elem.is('input') && $('#lineexpeditionid').val().length > 0) || (lot_number && lot_number.length > 0))
					{
						$('#newline_numserie').css({ visibility: 'visible' });
					}
					else
					{
						$('#newline_numserie').css({ visibility: 'hidden' });
					}
				});
			});

			$('#numserie').change(function()
			{
				var numserie = $(this).val();

				if(numserie && numserie.length > 0)
				{
					$('#newline_quantity').css({ visibility: 'visible' });
				}
				else
				{
					$('#newline_quantity').css({ visibility: 'hidden' });
				}
			});

			$('#lineexpeditionid').change(function() {
				var productid = $(this).find(':selected').attr('fk-product');
				var lotNumberSelect = $('select#lot_number');

				// Si ce n'est pas un select, c'est un hidden => gestion des lots désactivée => on charge directement les numéros de série
				if(lotNumberSelect.length == 0) {
					$('#lot_number').trigger('change');
					return true;
				}

				$.ajax({
					url: 'script/interface.php',
					method: 'GET',
					data: {
						productid: productid,
						type:'get',
						get:'autocomplete_lot_number'
					}
				}).done(function(json_results) {

					$('#lot_number option').remove();

					lotNumberSelect.append($('<option>', {
						value: '',
						text: '-- Selectionnez un lot --',
						selected: true
					}));

					$.each(json_results, function(index) {
						var obj = json_results[index];

						lotNumberSelect.append($('<option>', {
							value: obj.lot_number,
							text: obj.label
						}));
					});

					if(productid && productid.length > 0)
					{
						$('#newline_lot_number').css({ visibility: 'visible' });
					}
					else
					{
						$('#newline_lot_number').css({ visibility: 'hidden' });
					}
				});
			});

<?php if(! empty($conf->global->DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED)) { ?>
			$('.isPreparedCheckbox').change(function()
			{
				var checkbox = $(this);
				var rank = parseInt(checkbox.prop('name').replace('TLine[', '').replace('][is_prepared]', ''));
				var fk_expeditiondet_asset = $('input[name=TLine\\[' + rank + '\\]\\[fk_expeditiondet_asset\\]]').val();
				var is_prepared = checkbox.is(':checked') ? 1 : 0;

				$.ajax(
				{
					url: '<?php echo dol_escape_js(dol_buildpath('/dispatch/script/interface.php', 1)); ?>'
					, method: 'POST'
					, data:
					{
						put: 'set_line_is_prepared'
						, fk_expeditiondet_asset: fk_expeditiondet_asset
						, is_prepared: is_prepared
						, dataType: 'json'
					}
					, success: function(response)
					{
						verifyIsPreparedCheckboxState();

						var preset = response.success ? 'ok' : 'error';
						var persist = ! response.success;

						$.jnotify(response.message, preset, persist);
					}
					, error: function(xhr, textStatus, errorThrown)
					{
						checkbox.prop('checked', is_prepared == 1 ? false : true);
						$.jnotify(textStatus + ' : ' + errorThrown, 'error', true);
					}
				})
			});

			$('.allPreparedCheckbox').change(function()
			{
				var checkbox = $(this);
				var expeditionid = $('#id').val();
				var is_prepared = $(this).is(':checked') ? 1 : 0;

				$.ajax({
					url: '<?php echo dol_escape_js(dol_buildpath('/dispatch/script/interface.php', 1)); ?>'
					, method: 'POST'
					, data:
						{
							put: 'set_all_lines_is_prepared'
							, fk_expedition: expeditionid
							, is_prepared: is_prepared
						}
					, dataType: 'json'
					, success: function(response)
					{
						if(response.success)
						{
							$('.isPreparedCheckbox').prop('checked', is_prepared == 1 ? true : false);
						}

						var preset = response.success ? 'ok' : 'error';
						var persist = ! response.success;

						$.jnotify(response.message, preset, persist);
					}
					, error: function(xhr, textStatus, errorThrown)
					{
						checkbox.prop('checked', is_prepared == 1 ? false : true);
						$.jnotify(textStatus + ' : ' + errorThrown, 'error', true);
					}
				});
			});

			function verifyIsPreparedCheckboxState()
			{
				$('.allPreparedCheckbox').prop('checked', $('.isPreparedCheckbox').not(':checked').length == 0);
			}

			verifyIsPreparedCheckboxState();
<?php } ?>
		});
	</script>
<?php
}

function enteteexpedition(&$expedition) {
	global $langs, $db, $user, $hookmanager, $conf;

	$form =	new Form($db);
	
	$soc = new Societe($db);
	$soc->fetch($expedition->socid);

	if (!empty($expedition->origin) && $expedition->origin_id > 0)
	{
		$typeobject = $expedition->origin;
		$origin = $expedition->origin;
		$origin_id = $expedition->origin_id;
		$expedition->fetch_origin();         // Load property $object->commande, $object->propal, ...

		if ($typeobject == 'commande' && $expedition->$typeobject->id && ! empty($conf->commande->enabled))
		{
			$objectsrc=new Commande($db);
			$objectsrc->fetch($expedition->$typeobject->id);
		}

		if ($typeobject == 'propal' && $expedition->$typeobject->id && ! empty($conf->propal->enabled))
		{
			$objectsrc=new Propal($db);
			$objectsrc->fetch($expedition->$typeobject->id);
		}
	}

	if(empty($expedition->thirdparty) && method_exists($expedition, 'fetch_thirdparty'))
	{
		$expedition->fetch_thirdparty();
	}

	$hasDolBannerTab = function_exists('dol_banner_tab');

	$linkback = '<a href="'.DOL_URL_ROOT.'/expedition/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

	if($hasDolBannerTab)
	{
		$morehtmlref='<div class="refidno">' . $langs->trans('RefCustomer') . ' : ' . $expedition->ref_customer;
		// Thirdparty
		$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $expedition->thirdparty->getNomUrl(1);
		// Project
		if (! empty($conf->projet->enabled)) {
			dol_include_once('/projet/class/project.class.php');
			$langs->load("projects");
			$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';

			// We don't have project on shipment, so we will use the project or source object instead
			$morehtmlref .= ' : ';
			if (! empty($objectsrc->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($objectsrc->fk_project);
				$morehtmlref .= '<a href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $objectsrc->fk_project . '" title="' . $langs->trans('ShowProject') . '">';
				$morehtmlref .= $proj->ref;
				$morehtmlref .= '</a>';
			}
		}
		$morehtmlref.='</div>';


		dol_banner_tab($expedition, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	}
	
    print '<table class="noborder" width="100%">';

    if(! $hasDolBannerTab)
    {
		// Ref
		print '<tr><td>' . $langs->trans("Ref") . '</td>';
		print '<td colspan="3">';
		print $form->showrefnav($expedition, 'ref', $linkback, 1, 'ref', 'ref');
		print '</td></tr>';

		// Customer
		print '<tr><td>' . $langs->trans("Customer") . '</td>';
		print '<td colspan="3">' . $soc->getNomUrl(1) . '</td>';
		print "</tr>";
	}

    // Linked documents
    if ($typeobject == 'commande' && $expedition->$typeobject->id && ! empty($conf->commande->enabled))
    {
        print '<tr><td>';
        $objectsrc=new Commande($db);
        $objectsrc->fetch($expedition->$typeobject->id);
        print $langs->trans("RefOrder").'</td>';
        print '<td colspan="3">';
        print $objectsrc->getNomUrl(1,'commande');
        print "</td>\n";
        print '</tr>';
    }
    if ($typeobject == 'propal' && $expedition->$typeobject->id && ! empty($conf->propal->enabled))
    {
        print '<tr><td>';
        $objectsrc=new Propal($db);
        $objectsrc->fetch($expedition->$typeobject->id);
        print $langs->trans("RefProposal").'</td>';
        print '<td colspan="3">';
        print $objectsrc->getNomUrl(1,'expedition');
        print "</td>\n";
        print '</tr>';
    }

	if(! $hasDolBannerTab)
	{
		// Ref customer
		print '<tr><td>' . $langs->trans("RefCustomer") . '</td>';
		print '<td colspan="3">' . $expedition->ref_customer . "</a></td>\n";
		print '</tr>';
	}

    // Date creation
    print '<tr><td style="width:20%">'.$langs->trans("DateCreation").'</td>';
    print '<td colspan="3">'.dol_print_date($expedition->date_creation,"dayhour")."</td>\n";
    print '</tr>';

    // Delivery date planed
    print '<tr><td height="10">';
    print '<table class="nobordernopadding" width="100%"><tr><td>';
    print $langs->trans('DateDeliveryPlanned');
    print '</td>';
	
    print '</tr></table>';
    print '</td><td colspan="2">';
	print $expedition->date_delivery ? dol_print_date($expedition->date_delivery,'dayhourtext') : '&nbsp;';
    print '</td>';
    print '</tr>';

    if(! $hasDolBannerTab)
    {
		// Status
		print '<tr><td>' . $langs->trans("Status") . '</td>';
		print '<td colspan="3">' . $expedition->getLibStatut(4) . "</td>\n";
		print '</tr>';
	}

    // Sending method
    print '<tr><td height="10">';
    print '<table class="nobordernopadding" width="100%"><tr><td>';
    print $langs->trans('SendingMethod');
    print '</td>';

    print '</tr></table>';
    print '</td><td colspan="2">';
    if ($expedition->shipping_method_id > 0)
    {
        // Get code using getLabelFromKey
        $code=$langs->getLabelFromKey($db,$expedition->shipping_method_id,'c_shipment_mode','rowid','code');
        print $langs->trans("SendingMethod".strtoupper($code));
    }
    print '</td>';
    print '</tr>';

    print "</table>\n";
}
