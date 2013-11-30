<?php
require_once('lib/BackupClass.php');
?>
<h1>Example of a DB Backup File</h1>
<?php
//Database Configurations Array
$dbConfig = array('host' => 'localhost',
					  'login' => '{DBUsername}',
					  'password' => '{DBPassword}',
					  'database_name' => '{DBName}');

//Amazon S3 Configurations Array (Optional)
$amazonConfig = array('accessKey' => '{YOUR S3 ACCESS KEY}',
				 	  'secretKey' =>  '{Your S3 Secret Key}',
				  	  'bucketName' => '{Your Bucket}');
					  
/*
 * Example 1: One seperate dump file per table
 */
					  
try{
	$dbBackupObj = new DbBackup($dbConfig);
	
	$dbBackupObj->excludeTable('test','logs','users');
	
	$dbBackupObj->enableS3Support($amazonConfig);//this is optional, you can remove it if you want local file system backup only
	$dbBackupObj->executeBackup();
}catch(Exception $e){
	echo $e->getMessage();
}
