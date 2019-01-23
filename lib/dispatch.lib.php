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

	$canBeClosed = true;

	foreach($shipment->lines as $line)
	{
		$dispatchDetail = new TDispatchDetail;
		$detailLoaded = $dispatchDetail->loadBy($PDOdb, $line->id, 'fk_expeditiondet');

		// Pas d'équipement associé => ligne suivante
		if(empty($detailLoaded))
		{
			continue;
		}

		if(empty($dispatchDetail->is_prepared))
		{
			$canBeClosed = false;
			break;
		}
	}

	$PDOdb->close();

	// return $canBeClosed;
	return false;
}