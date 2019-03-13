<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	
 	if(!defined('INC_FROM_DOLIBARR')) {
        define('INC_FROM_CRON_SCRIPT', true);
        require('../config.php');
        $PDOdb=new TPDOdb;
        $PDOdb->debug=true;
    }
    else{
        $PDOdb=new TPDOdb;
    }

	dol_include_once('/dispatch/class/dispatchdetail.class.php');
	dol_include_once('/dispatch/class/dispatchasset.class.php');
	dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
 
	$o=new TDispatchDetail;
	$o->init_db_by_vars($PDOdb);

	$o=new TRecepDetail;
	$o->init_db_by_vars($PDOdb);

	$o=new TRecepBDRDetail;
	$o->init_db_by_vars($PDOdb);

	$o=new TRecepBDRDispatch;
	$o->init_db_by_vars($PDOdb);

	$o=new TDispatch;
	$o->init_db_by_vars($PDOdb);

	$o=new TDispatchAsset;
	$o->init_db_by_vars($PDOdb);
