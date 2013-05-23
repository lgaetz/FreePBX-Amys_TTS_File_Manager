<?php
global $db;

echo 'Deleting tables from database<br />';
$sql = 'DROP TABLE `' . AmyTTSMaker::GetTableName() . '`';
$result = $db->query($sql);