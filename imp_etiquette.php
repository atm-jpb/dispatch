<?php
require('config.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
dol_include_once('/expedition/class/expedition.class.php');
dol_include_once('/core/lib/admin.lib.php');
global $db;

function _unit($unite){
	switch ($unite) {
		case -9:
			return "ug";
			break;
		case -6:
			return "mg";
			break;
		case -3:
			return "g";
			break;
		case 0:
			return "kg";
			break;
	}
}

if(isset($_REQUEST['modele'])){
	
	//Récupération des parametres transmis
	$nbVides = (!empty($_REQUEST['startpos'])) ? $_REQUEST['startpos']-1 : 0;
	$nbCopies = (!empty($_REQUEST['copie'])) ? $_REQUEST['copie'] : 1;
	$modele = $_REQUEST['modele'];
	$expedition = new Expedition($db);
	$expedition->fetch($_REQUEST['expedition']);
	$expedition->fetch_lines();
	
	$TetiquettesVides = array();
	$Tetiquettes = array();
	
	//création des div vides
	for($i=0; $i< $nbVides; $i++){
		$TetiquettesVides[$i] = array($i);
	}
	
	$TPDOdb = new TPDOdb;
	
	//création des div pleines
	foreach($expedition->lines as $ligne){
		
		$TPDOdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."expeditiondet_asset WHERE fk_expeditiondet = ".$ligne->rowid);
		$TidExepeditiondetAsset = array();
		
		while($TPDOdb->Get_line()) {
			$TidExepeditiondetAsset[] = $TPDOdb->Get_field('rowid');
		}
		
		foreach($TidExepeditiondetAsset as $idExpeditiondetAsset){
			$sql = "SELECT p.ref, p.label as nom, p.note as descritpion, eda.tare as tare, a.serial_number as code, a.lot_number as lot, eda.weight_reel as poids, eda.weight_reel_unit as poids_unit, eda.tare_unit as tare_unit
					FROM ".MAIN_DB_PREFIX."expeditiondet_asset as eda
						LEFT JOIN ".MAIN_DB_PREFIX.ATM_ASSET_NAME." as a ON (a.rowid = eda.fk_asset)
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = a.fk_product)
					WHERE eda.rowid = ".$idExpeditiondetAsset;
			
			$TPDOdb->Execute($sql);
			$TPDOdb->Get_line();
			
			$tare_unit = _unit($TPDOdb->Get_field('tare_unit'));
			$poids_unit = _unit($TPDOdb->Get_field('poids_unit'));
			
			//On duplique l'étiquette autant de fois que demandé en paramètre
			for($i=0; $i< $nbCopies; $i++){
				$Tetiquettes[] = array(
								"ref" => $TPDOdb->Get_field('ref'),
								"nom" => $TPDOdb->Get_field('nom'),
								"description" => ((int)$TPDOdb->Get_field('description') != 0) ? $TPDOdb->Get_field('description') : "",
								"tare" => number_format($TPDOdb->Get_field('tare'),2,',',' '),
								"tare_unit" => $tare_unit,
								"code" => $TPDOdb->Get_field('code'),
								"lot" => $TPDOdb->Get_field('lot'),
								"poids" => number_format($TPDOdb->Get_field('poids'),2,',',' '),
								"poids_unit" => $poids_unit
							);
			}
		}
	}
	
	if (!empty($_REQUEST['margetop'])) dolibarr_set_const($db, 'ETIQUETTE_MARGE_TOP_'.$modele, $_REQUEST['margetop'],'chaine',1,'Marge en mm');
	if (!empty($_REQUEST['margeleft'])) dolibarr_set_const($db, 'ETIQUETTE_MARGE_LEFT_'.$modele, $_REQUEST['margeleft'],'chaine',1,'Marge en mm');
	
	$TMarges = array(
		'margetop'=> $_REQUEST['margetop'],
		'margeleft'=> $_REQUEST['margeleft']
	);
	
	$TBS = new TTemplateTBS();
	
	$rendu = $TBS->Render("modele/".$modele,
					array('etiquette_vide'=>$TetiquettesVides,
						  'etiquette'=>$Tetiquettes
					)
					,array(
						  'marge'=>$TMarges
					)
				);
				
	echo $rendu;
}