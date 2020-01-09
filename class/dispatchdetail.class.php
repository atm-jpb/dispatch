<?php

class TDispatchDetail extends TObjetStd {

	var $fk_expeditiondet;
	var $fk_asset;
	var $rang;
	var $is_prepared;
	var $lot_number;
	var $carton;
	var $numerosuivi;
	var $weight;
	var $weight_reel;
	var $tare;
	var $weight_unit;
	var $weight_unit_reel;
	var $tare_unit;

	function __construct() {
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'expeditiondet_asset');
		parent::add_champs('fk_expeditiondet,fk_asset','type=entier;index;');
		parent::add_champs('rang,is_prepared','type=entier;');
		parent::add_champs('lot_number,carton,numerosuivi','type=chaine;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');

		parent::_init_vars();
		parent::start();

		$this->lines = array();
		$this->nbLines = 0;
	}

	//Charges les lignes de flacon associé à la ligne d'expédition passé en paramètre
	function loadLines(&$PDOdb, $id_expeditionLine){

		$sql = "SELECT rowid FROM ".$this->get_table()." WHERE fk_expeditiondet = ".$id_expeditionLine." ORDER BY rang";

		$TIdExpedet = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

		foreach($TIdExpedet as $idexpedet){
			$dispatchdetail_temp = new TDispatchDetail;
			$dispatchdetail_temp->load($PDOdb, $idexpedet);
			$this->lines[] = $dispatchdetail_temp;
			$this->nbLines = $this->nbLines + 1;
		}
	}

	function getPoidsExpedie(&$PDOdb,$id_expeditionLine,$product){
		$sql = "SELECT SUM(eda.weight) as Total, eda.weight_reel_unit as Unite
				FROM ".MAIN_DB_PREFIX."expeditiondet_asset as eda
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON (eda.fk_expeditiondet = ed.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."commandedet as c ON (c.rowid = ed.fk_origin_line)
				WHERE ed.fk_origin_line IN (SELECT fk_origin_line FROM ".MAIN_DB_PREFIX."expeditiondet WHERE rowid = ".$id_expeditionLine.")
				GROUP BY Unite";

		$total = 0;
		$PDOdb->Execute($sql);
		while($PDOdb->Get_line()){
			$total += $PDOdb->Get_field('Total') * pow(10,$PDOdb->Get_field('Unite'));
		}

		return $total * pow(10,-$product->weight_units) ;
	}
}

class TRecepDetail extends TObjetStd {
	function __construct() {
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'commande_fournisseurdet_asset');
		parent::add_champs('fk_commandedet,fk_product,fk_warehouse','type=entier;index;');
		parent::add_champs('rang','type=entier;');
		parent::add_champs('lot_number,carton,numerosuivi,imei,firmware,serial_number','type=chaine;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('dluo','type=date;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit, already_dispatch','type=entier;');

		parent::_init_vars();
		parent::start();
	}
}

class TRecepBDRDetail extends TObjetStd {
	function __construct() {
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'bonderetourdet_asset');
		parent::add_champs('fk_bonderetourdet,fk_product,fk_warehouse','type=entier;index;');
		parent::add_champs('rang','type=entier;');
		parent::add_champs('lot_number,carton,numerosuivi,imei,firmware,serial_number','type=chaine;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('dluo','type=date;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');

		parent::_init_vars();
		parent::start();
	}

	function loadLines(&$PDOdb, $id_bdrLine){

		$sql = "SELECT rowid FROM ".$this->get_table()." WHERE fk_bonderetourdet = ".$id_bdrLine." ORDER BY rang";

		$TIdBDRdet = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

		foreach($TIdBDRdet as $idbdrdet){
			$detail_temp = new TRecepBDRDetail;
			$detail_temp->load($PDOdb, $idbdrdet);
			$this->lines[] = $detail_temp;
			$this->nbLines = $this->nbLines + 1;
		}
	}
}

class TRecepBDRDispatch extends TObjetStd {
	function __construct()
	{
		parent::set_table(MAIN_DB_PREFIX.'bonderetour_dispatch');
		parent::add_champs('fk_bonderetour,fk_bonderetourdet,fk_product,fk_entrepot','type=entier;index;');
		parent::add_champs('qty','type=float;');
		parent::add_champs('eatby,sellby,batch,comment','type=chaine;');

		parent::_init_vars();
		parent::start();
	}
}
