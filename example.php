<?php
require_once('lib/BackupClass.php');
?>
<h1>Example of a DB Backup File</h1>
<?php
//Database Configurations Array
$dbConfig = array('host' => 'localhost',
				  'login' => '{DBUsername}',
				  'password' => 'DBPassword',
				  'database_name' => 'DBName');

//Amazon S3 Configurations Array (Optional)
$amazonConfig = array('accessKey' => '{YOUR S3 ACCESS KEY}',
				 	  'secretKey' =>  '{Your S3 Secret Key}',
				  	  'bucketName' => '{Your Bucket}');
					  
try{
	$dbBackupObj = new DbBackup($dbConfig);
	$dbBackupObj->setBackupDirectory('backups/yourFolderName');
	$dbBackupObj->enableS3Support($amazonConfig);
	$dbBackupObj->executeBackup();
}catch(Exception $e){
	echo $e->getMessage();
}
