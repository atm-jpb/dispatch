<?php
require('config.php');
dol_include_once('/core/lib/product.lib.php');

$langs->load("other");

llxHeader('',$langs->trans("LIST_SHIPMENTS_TO_PREPARE"),'','');

$ATMdb = new TPDOdb;

$sql = "SELECT s.rowid as soc_id, s.nom as soc_nom, e.rowid as expe_id, e.ref as expe_ref, c.rowid as comm_id, c.ref as comm_ref, ";
$sql.= "p.rowid as prod_id, CONCAT(p.ref, ' ', p.label) as prod_ref, cd.qty, cd.tarif_poids, cd.poids, cd.asset_lot ";
$sql.= "FROM ".MAIN_DB_PREFIX."expedition e ";
$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.fk_expedition = e.rowid ";
//$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON ee.fk_target = e.rowid AND ee.targettype = 'shipping' ";
//$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."commande c ON ee.fk_source = c.rowid AND ee.sourcetype = 'commande' ";
$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON ed.fk_origin_line = cd.rowid ";
$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."commande c ON cd.fk_commande = c.rowid ";
$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."product p ON cd.fk_product = p.rowid ";
$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."societe s ON c.fk_soc = s.rowid ";
$sql.= "WHERE e.fk_statut = 0 ";
$sql.= "ORDER BY prod_ref, soc_nom";

$r = new TListviewTBS('expe');

$measuring_units=array(-9=>1,-6=>1,-3=>1,0=>1,3=>1,99=>1,100=>1);
foreach ($measuring_units as $key => $value) {
	$TPoids[$key] = measuring_units_string($key,'weight');
}

echo $r->render($ATMdb, $sql, array(
	'limit'=>array(
		'nbLine'=>'30'
	)
	,'link'=>array(
		'soc_nom'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@soc_id@">'.img_picto('','object_company.png','',0).' @val@</a>'
		,'expe_ref'=>'<a href="'.DOL_URL_ROOT.'/expedition/fiche.php?id=@expe_id@">'.img_picto('','object_sending.png','',0).' @val@</a>'
		,'comm_ref'=>'<a href="'.DOL_URL_ROOT.'/commande/fiche.php?id=@comm_id@">'.img_picto('','object_order.png','',0).' @val@</a>'
		,'prod_ref'=>'<a href="'.DOL_URL_ROOT.'/product/fiche.php?id=@prod_id@">'.img_picto('','object_product.png','',0).' @val@</a>'
	)
	,'translate'=>array('poids'=>$TPoids)
	,'hide'=>array('soc_id', 'expe_id', 'comm_id', 'prod_id')
	,'type'=>array('tarif_poids'=>'number','qty'=>'number')
	,'liste'=>array(
		'titre'=>$langs->trans("LIST_SHIPMENTS_TO_PREPARE")
		,'image'=>img_picto('','title.png', '', 0)
		,'picto_precedent'=>img_picto('','back.png', '', 0)
		,'picto_suivant'=>img_picto('','next.png', '', 0)
		,'noheader'=> (int)isset($_REQUEST['fk_soc']) | (int)isset($_REQUEST['fk_product'])
		,'messageNothing'=>$langs->trans("LIST_NO_SHIPMENTS")
		,'picto_search'=>img_picto('','search.png', '', 0)
	)
	,'title'=>array(
		'soc_nom'=>$langs->trans('Client')
		,'expe_ref'=>'Expédition'
		,'comm_ref'=>'Commande'
		,'prod_ref'=>$langs->trans('Product')
		,'qty'=>$langs->trans('Quantity')
		,'tarif_poids'=>'Poids'
		,'asset_lot'=>'Lot'
	)
));
		
llxFooter();