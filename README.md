MySQL Backup Class
==================
This project provides a useful tool (PHP Class) to backup any MySQL database automaticall with Amazon S3 Support.

# Features
	* Backup each DB table in a seperate SQL Dump File
	* Exclude specific tables from your Backup
	* Backup data on Amazon S3(Optional)

# Currently Working On:

	* Add a Code Generation File
	* Validate user provided settings
	* Modify the Class to allow data restoring on the fly too

# Code Examples

```
<?php
require_once('lib/BackupClass.php');
$dbConfig = array('host' => 'localhost',
				  'login' => '{DBUsername}',
				  'password' => 'DBPassword',
				  'database_name' => 'DBName');

$dbBackupObj = new DbBackup($dbConfig);
$dbBackupObj->setBackupDirectory('backups/yourFolderName');
$dbBackupObj->executeBackup();
?>
```