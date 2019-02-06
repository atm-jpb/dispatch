<?php


/**
 * Determines whether a shipment can be closed
 *
 * @param	Expedition	$shipment	Shipment to be examined
 *
 * @return	boolean			true if shipment can be closed, false otherwise
 */
function dispatch_shipment_can_be_closed(Expedition &$shipment)
{
	dol_include_once('/dispatch/class/dispatchdetail.class.php');

	$PDOdb = new TPDOdb;

	if(empty($shipment->lines) && method_exists($shipment, 'fetch_lines'))
	{
		$shipment->fetch_lines();
	}

	foreach($shipment->lines as $line)
	{
		$dispatchDetailStatic = new TDispatchDetail;
		$TDetail = $dispatchDetailStatic->LoadAllBy($PDOdb, array('fk_expeditiondet' => $line->id));

		// Pas d'équipement associé => ligne suivante
		if(empty($TDetail))
		{
			continue;
		}

		foreach($TDetail as $dispatchDetail)
		{
			if(empty($dispatchDetail->is_prepared))
			{
				$PDOdb->close();

				return false;
			}
		}
	}

	$PDOdb->close();

	return true;
}