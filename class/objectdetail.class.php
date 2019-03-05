<?php
/**
 * Created by PhpStorm.
 * User: atm-greg
 * Date: 05/03/19
 *
 */

/**
 * Class TObjectDetail
 *
 * Class to manage object line details (reception, shipping, bonderetour)
 *
 */
class TObjectDetail extends TObjetStd
{
	function __construct() {
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'objectdet_asset');
		parent::add_champs('fk_line,fk_product,fk_warehouse','type=entier;index;');
		parent::add_champs('rang','type=entier;');
		parent::add_champs('object_type, lot_number,carton,numerosuivi,imei,firmware,serial_number','type=chaine;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('dluo','type=date;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');

		parent::_init_vars();
		parent::start();

		$this->lines = array();
		$this->nbLines = 0;
	}

	// load asset detail for one line
	function loadLines(&$PDOdb, $fk_line, $object_type){

		if (empty($fk_line))
		{
			$this->errors[] = 'Empty line id';
		}
		if (empty($object_type))
		{
			$this->errors[] = 'Empty object_type';
		}

		if (count($this->errors)) return -1;

		$sql = "SELECT rowid FROM ".$this->get_table()." WHERE fk_line = ".$fk_line." AND object_type = '".$object_type."' ORDER BY rang";

		$TIdExpedet = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

		foreach($TIdExpedet as $idexpedet){
			$dispatchdetail_temp = new TDispatchDetail;
			$dispatchdetail_temp->load($PDOdb, $idexpedet);
			$this->lines[] = $dispatchdetail_temp;
			$this->nbLines = $this->nbLines + 1;
		}
	}
}
