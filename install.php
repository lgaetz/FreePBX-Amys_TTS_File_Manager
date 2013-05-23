<?php
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'functions.inc.php');

if($amp_conf["AMPDBENGINE"] == "mysql")  
{
	$check = $db->query("CREATE TABLE IF NOT EXISTS `" . AmyTTSMaker::GetTableName() . "` (
	`id` char(32),
	`codec` char( 2 ) NOT NULL ,	
	`filename` varchar( 50 ) NOT NULL ,
	`tts` varchar( 255 ) NOT NULL ,
	PRIMARY KEY (`id`))");
	
	if(DB::IsError($check))
		die_freepbx(_('Unable to create table in database'));
	else 
		echo 'Database table for ' . AmyTTSMaker::GetLongModuleName() . ' installed<br />';

	if (! is_dir(AmyTTSMaker::GetPathAsteriskStorage()))
		if (! mkdir(AmyTTSMaker::GetPathAsteriskStorage(),0777))
			die_freepbx(_('Unable to create directory'));
			
	sql('INSERT INTO `'.AmyTTSMaker::GetTableName().'` (`id`, `codec`, `filename`, `tts`) VALUES("539f3940752f1cedf3c5713aff48579c", "a", "edison", "Hello. Can you hear me now?")');
}
else 
{
	die_freepbx( _('Unknown database engine. Cannot create needed database table') );
}
?>