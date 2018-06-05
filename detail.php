<?php

	require('config.php');

	dol_include_once('/expedition/class/expedition.class.php' );
	dol_include_once('/dispatch/class/dispatchdetail.class.php' );
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/core/lib/admin.lib.php' );
	dol_include_once('/core/lib/sendings.lib.php' );
	dol_include_once('/core/lib/product.lib.php');
	dol_include_once('/asset/class/asset.class.php');
	
	global $langs, $user,$db;
	$langs->load('orders');
	$PDOdb=new TPDOdb;

	$id = GETPOST('id');

	$expedition = new Expedition($db);
	$expedition->fetch($id);
	
	$action = GETPOST('action');
	$TImport = _loadDetail($PDOdb, $expedition);
	
	if(isset($_FILES['file1']) && $_FILES['file1']['name']!='') {
		$f1  =file($_FILES['file1']['tmp_name']);

		$TImport = array();

		foreach($f1 as $line) {

			list($ref, $numserie, $imei, $firmware)=str_getcsv($line,';','"');

			$TImport = _addExpeditiondetLine($PDOdb,$TImport,$expedition,$numserie);
		}
		
	}
	else if($action=='DELETE_LINE') {
		unset($TImport[(int)GETPOST('k')]);
		
		$rowid = GETPOST('rowid');
		
		$dispatchdetail = new TDispatchDetail;
		$dispatchdetail->load($PDOdb, $rowid);
		$dispatchdetail->delete($PDOdb);
		
		setEventMessage('Ligne supprimée');
	}
	elseif(isset($_POST['btaddasset'])) {
		//var_dump($_POST);exit;
		$numserie = GETPOST('numserie');
		
		$asset = new TAsset;
		if($asset->loadBy($PDOdb, $numserie, 'serial_number')){
				
			_addExpeditiondetLine($PDOdb,$TImport,$expedition,$numserie);

			setEventMessage('Numéro de série enregistré');
		}
		else{
			setEventMessage('Aucun équipement pour ce numéro de série','errors');
		}		
	}

	fiche($PDOdb,$expedition, $TImport);

function _loadDetail(&$PDOdb,&$expedition){
		
		$TImport = array();

		foreach($expedition->lines as $line){
		
			$sql = "SELECT a.rowid as id,a.serial_number,p.ref,p.rowid, ea.fk_expeditiondet, ea.lot_number, ea.weight_reel, ea.weight_reel_unit
					FROM ".MAIN_DB_PREFIX."expeditiondet_asset as ea
						LEFT JOIN ".MAIN_DB_PREFIX."asset as a ON ( a.rowid = ea.fk_asset)
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = a.fk_product)
					WHERE ea.fk_expeditiondet = ".$line->line_id."
						ORDER BY ea.rang ASC";

			$PDOdb->Execute($sql);
			$Tres = $PDOdb->Get_All();
			
			foreach ($Tres as $res) {
				
				$TImport[] =array(
					'ref'=>$res->ref
					,'numserie'=>$res->serial_number
					,'fk_product'=>$res->rowid
					,'fk_expeditiondet'=>$res->fk_expeditiondet
					,'lot_number'=>$res->lot_number
					,'quantity'=>$res->weight_reel
					,'quantity_unit'=>$res->weight_reel_unit
				);
			}
		}

		return $TImport;
	}

	function _addExpeditiondetLine(&$PDOdb,&$TImport,&$expedition,$numserie){
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

			$dispatchdetail->save($PDOdb);
			
			//Rempli le tableau utilisé pour l'affichage des lignes
			$TImport[] =array(
				'ref'=>$prodAsset->ref
				,'numserie'=>$numserie
				,'fk_product'=>$prodAsset->id
				,'fk_expeditiondet'=>$expedition->id
				,'lot_number'=>$asset->lot_number
				,'quantity'=> (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_value
				,'quantity_unit'=> (GETPOST('quantity')) ? GETPOST('quantity') : $asset->contenancereel_units
			);
			

		}
		//pre($TImport,true);
		return $TImport;

	}
function fiche(&$PDOdb,&$expedition, &$TImport) {
	global $langs, $db, $conf;

	llxHeader();

	$head = shipping_prepare_head($expedition);
	
	$title=$langs->trans("Shipment");
	dol_fiche_head($head, 'dispatch', $title, 0, 'dispatch');
	
	enteteexpedition($expedition);
	
	echo '<br>';
	
	if($expedition->statut == 0){
		//Form pour import de fichier
		if($conf->global->DISPATCH_USE_IMPORT_FILE){
			$form=new TFormCore('auto','formimport','post', true);
			
			echo $form->hidden('action', 'SAVE');
			echo $form->hidden('id', $expedition->id);
			
			echo $form->fichier('Fichier à importer','file1','',80);
			echo $form->btsubmit('Envoyer', 'btsend');
		
			$form->end();
		}
		
		?>
		<script>
			$(document).ready(function() {

				$('#lot_number').change(function() {
					var lot_number = $(this).val();

					$.ajax({
						url: 'script/interface.php',
						method: 'GET',
						data: {
							lot_number: lot_number,
							productid: $('#lineexpeditionid').find(':selected').attr('fk-product'),
							type:'get',
							get:'autocomplete_asset'
						}
					}).done(function(results) {
						var json_results = $.parseJSON(results);

						$('#numserie option').remove();
						cpt = 0;
						$.each(json_results, function(index) {
							var obj = json_results[index];
							cpt ++;
							$('#numserie').append($('<option>', {
								value: obj.serial_number,
								text: obj.serial_number + ' - ' + obj.qty + ' ' +obj.unite_string
							}));

							if(cpt == 1) { // A ne faire que pour le premier résultat
								$('#quantity').val(obj.qty);
								if(obj.unite != 'unité(s)'){
									$('#quantity_unit').show();
									$('#units_lable').remove();
									$('#quantity_unit option[value='+obj.unite+']').attr("selected","selected");
								}
								else{
									$('#quantity_unit').hide();
									$('#quantity_unit option[value=0]').attr("selected","selected");
									$('#quantity').after('<span id="units_lable"> unité(s)</span>');
								}
							}
						});
					});
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
					}).done(function(results) {
						var json_results = $.parseJSON(results);

						$('#lot_number option').remove();
						
						$.each(json_results, function(index) {
							var obj = json_results[index];

							lotNumberSelect.append($('<option>', {
								value: obj.lot_number,
								text: obj.label
							}));
						});
					});
				});
			});
		</script>
		<?php
		
		//Form pour ajouter un équipement directement
		$DoliForm = new FormProduct($db);
		$form=new TFormCore('auto', 'formaddasset','post', true);	
		echo $form->hidden('action','edit');
		echo $form->hidden('mode','addasset');
		
		echo $form->hidden('id', $expedition->id);
		
		$TLotNumber = array(' -- aucun produit sélectionné -- ');
		/*$sql = "SELECT DISTINCT(lot_number),rowid, SUM(contenancereel_value) as qty, contenancereel_units as unit FROM ".MAIN_DB_PREFIX."asset GROUP BY lot_number ORDER BY lot_number ASC";

		$PDOdb->Execute($sql);
		$Tres = $PDOdb->Get_All();
		foreach($Tres as $res){
			
			$asset = new TAsset;
			$asset->load($PDOdb, $res->rowid);
			$asset->load_asset_type($PDOdb);
			//pre($asset,true);exit;
			$TLotNumber[$res->lot_number] = $res->lot_number." / ".$res->qty." ".(($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($res->unit,$asset->assetType->measuring_units));
		}
		*/
		
		$TSerialNumber = array(' -- aucun lot sélectionné -- ');
		/*$sql = "SELECT DISTINCT(serial_number),contenancereel_value, contenancereel_units FROM ".MAIN_DB_PREFIX."asset ORDER BY serial_number ASC";
		$PDOdb->Execute($sql);
		while ($PDOdb->Get_line()) {
			$TSerialNumber[$PDOdb->Get_field('serial_number')] = $PDOdb->Get_field('serial_number').' / '.$PDOdb->Get_field('contenancereel_value')." ".measuring_units_string($PDOdb->Get_field('contenancereel_units'),'weight');
		}
		*/
		
		echo 'Produit expédié<select id="lineexpeditionid" name="lineexpeditionid"><option value=""></option>';
		
		$TProduct = array('');
		$sql = "SELECT DISTINCT(ed.rowid),p.rowid as fk_product,p.ref,p.label ,ed.qty
				FROM ".MAIN_DB_PREFIX."product as p
					LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON (cd.fk_product = p.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON (ed.fk_origin_line = cd.rowid)
				WHERE ed.fk_expedition = ".$expedition->id."";
		
		$PDOdb->Execute($sql);
		while ($obj = $PDOdb->Get_line()) {
			//$TProduct[$PDOdb->Get_field('rowid')] = $PDOdb->Get_field('ref').' - '.$PDOdb->Get_field('label');
			
 			echo '<option value="'.$obj->rowid.'" fk-product="'.$obj->fk_product.'">'.$obj->ref.' - '.$obj->label.' x '.$obj->qty.'</option>';
			
		}
		
		
		echo '</select><br />';
		
		//echo $form->combo('Produit expédié', 'lineexpeditionid', $TProduct, '').'<br>';
		if(! empty($conf->global->USE_LOT_IN_OF)) echo $form->combo('Numéro de Lot', 'lot_number', $TLotNumber, '').'<br>';
		else echo $form->hidden('lot_number', '');
		echo $form->combo('Numéro de série à ajouter','numserie',$TSerialNumber,'').'<br>';
		echo $form->texte('Quantité','quantity','',10)." ".$DoliForm->load_measuring_units('quantity_unit" id="quantity_unit','weight');
		echo $form->btsubmit('Ajouter', 'btaddasset');
		
		$form->end();
		
		echo '<br>';
	}
	
	tabImport($TImport,$expedition);
	
	llxFooter();
}

function tabImport(&$TImport,&$expedition) {
	global $langs, $db, $conf;
	
	$form=new TFormCore;
	$formDoli =	new Form($db);
	$formproduct=new FormProduct($db);
	$PDOdb=new TPDOdb;
	
	print count($TImport).' équipement(s) dans votre expédition';
	
	?>
	<table width="100%" class="border">
		<tr class="liste_titre">
			<td>Produit</td>
			<td>Numéro de série</td>
<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
			<td>Numéro de Lot</td>
<?php } ?>
			<td>Quantité</td>
			<td>&nbsp;</td>
		</tr>
		
	<?php
		$prod = new Product($db);
		
		$form->Set_typeaff('view');
		
		if(is_array($TImport)){
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
				
				?><tr>
					<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref) ?></td>
					<td><a href="<?php echo dol_buildpath('/asset/fiche.php?id='.$asset->rowid,1); ?>"><?php echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30)   ?></a></td>
<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					<td><a href="<?php echo dol_buildpath('/asset/fiche_lot.php?id='.$assetLot->rowid,1); ?>"><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30)   ?></a></td>
<?php } ?>
					<td><?php echo $line['quantity']." ".(($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($line['quantity_unit'],$asset->assetType->measuring_units)); ?></td>
					<td>
						<?php 
							if($expedition->statut != 1) echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$expedition->id.'&rowid='.$Trowid[0].'">'.img_delete().'</a>';
						?>
					</td>
				</tr>
				
				<?php
				
			}
		}	
		?>
			
		
	</table>
	<br>
	<?php
	
}

function enteteexpedition(&$expedition) {
	global $langs, $db, $user, $hookmanager, $conf;

	$form =	new Form($db);
	
	$soc = new Societe($db);
	$soc->fetch($expedition->socid);
	
	if (!empty($expedition->origin))
	{
		$typeobject = $expedition->origin;
		$origin = $expedition->origin;
		$expedition->fetch_origin();
	}
	
    print '<table class="border" width="100%">';

    $linkback = '<a href="'.DOL_URL_ROOT.'/expedition/liste.php">'.$langs->trans("BackToList").'</a>';

    // Ref
    print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
    print '<td colspan="3">';
    print $form->showrefnav($expedition, 'ref', $linkback, 1, 'ref', 'ref');
    print '</td></tr>';

    // Customer
    print '<tr><td width="20%">'.$langs->trans("Customer").'</td>';
    print '<td colspan="3">'.$soc->getNomUrl(1).'</td>';
    print "</tr>";

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

    // Ref customer
    print '<tr><td>'.$langs->trans("RefCustomer").'</td>';
    print '<td colspan="3">'.$expedition->ref_customer."</a></td>\n";
    print '</tr>';

    // Date creation
    print '<tr><td>'.$langs->trans("DateCreation").'</td>';
    print '<td colspan="3">'.dol_print_date($expedition->date_creation,"day")."</td>\n";
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

    // Status
    print '<tr><td>'.$langs->trans("Status").'</td>';
    print '<td colspan="3">'.$expedition->getLibStatut(4)."</td>\n";
    print '</tr>';

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
