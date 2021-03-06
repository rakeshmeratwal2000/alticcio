<?php

$_SERVER['HTTP_HOST'] = "";
$_SERVER['REQUEST_URI'] = $argv[1];

$dump_file = dirname(__FILE__)."/install.sql";

if (!file_exists($dump_file)) {
	die("No SQL file for installation...");
}

include dirname(__FILE__)."/../includes/config.php";
include dirname(__FILE__)."/../includes/update.inc.php";

$config->core_include("outils/mysql", "database/database", "outils/update");

$sql = new MySQL($config->db());
$database = new Database($sql);

$sql->file($dump_file);

$update = new Update($sql);

foreach (scandir(dirname(__FILE__)."/init") as $file) {
	if (substr($file, -4) == ".php") {
		$table = substr($file, 0, -4);
		include dirname(__FILE__)."/init/".$file;
		foreach ($$table as $record) {
			$database->insert($table, $record);
		}
	}
}

$sql->query("UPDATE dt_infos SET valeur = {$update->last_version()} WHERE champ = 'version'");
