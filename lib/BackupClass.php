<?php
/** 
* Backup a MySQL database running on a linux server Automatically
*
* @author Ayman Bedair <ayman@aymanrb.com>
* @version 1.0
* @access public
* @copyright http://www.AymanRB.com
*
*/
class DbBackup {
	/** 
	* Full absolute path to database backup directory on the server without prevailing slashes
	* @var String
	* @access private
	* @see setBackupDirectory()
	*/
	private $backupDir = NULL;
	
	/** 
	* Allows the choice between dumping the whole file as one SQL script or as a seperate script for each table
	* @var Boolean
	* @access private
	* @see setDumpType()
	*/
	private $dumpTableFiles = true;
	
	
	/** 
	* All database configuration in one array
	* @var Array
	* @access private
	* @see __construct()
	*/
	private $databaseVars = NULL;
	
	
	/** 
	* All S3 related configuration in one array (Optional Only if S3 backup is required)
	* @var Array
	* @access private
	* @see transferToAmazon()
	*/
	private $s3Config = NULL;
								
	/** 
	* Database Class Object
	* @var DbObject
	* @access private
	* @see createNewDbConnection()
	*/
	private $dbObject = NULL;
	
	/** 
	* Excluded Tables Array
	* @var Array
	* @access private
	* @see executeBackup()
	*/
	private $excludeTables = array();
	
	/** 
	* A boolean used to tell the class whethere you want to take the extra mile of saving your backup to amazon S3 Servers
	* @var boolean
	* @access private
	* @see enableS3Support();executeBackup();
	*/
	private $transferToS3 = false;
	
	
	/** 
	* ===========================================================
	* ===================  CLASS METHODS  =======================
	* ===========================================================
	*/
	
	/**
	* Constructor of the class
	*
	* @param array configVars; Array of database config values (host,login,password,database_name)
	* @param array S3ConfigVars; Array of Amazon config values (accessKey,secretKey,bucketName)
	* @return void
	* 
	*/							
	public function __construct(Array $dbConfigVars, Array $S3ConfigVars = NULL){
		
		//Just to make sure the user provided all Database Connection fields
		if(!isset($dbConfigVars['host']) || !isset($dbConfigVars['login'])  || !isset($dbConfigVars['password'])  || !isset($dbConfigVars['database_name'])){
			throw new Exception("<h3>Missing one or more Database configuration Array keys<h3>
			<br>Please validate your array has the following keys: <br>
				1- host<br>
				2- login<br>
				3- password<br>
				4- database_name<br>");
		}
		
		$this->databaseVars = $dbConfigVars;
		$this->s3Config = $S3ConfigVars;
		$this->createNewDbConnection();
	}
	
	/** 
	* Creates a New MySQLi connection to the database using the user supplied connection vars and assigns it to the dbObject class property
	*
	* @return void
	* @access private
	* @see __construct()
	*
	*/
	
	private function createNewDbConnection(){
		$this->dbObject = @new mysqli($this->databaseVars['host'],$this->databaseVars['login'],$this->databaseVars['password'],$this->databaseVars['database_name']);
		
		if (mysqli_connect_error()) {
			throw new Exception('Database Connection Error (' . mysqli_connect_errno() . ') '. mysqli_connect_error());
		}
	}
	
	/** 
	* Executes a Query on the Database to list all tables of the Selected DB
	*
	* @return MySQLi Results
	* @access private
	* @see executeBackup()
	*
	*/
	
	private function listDbTables(){
		return $this->dbObject->query('SHOW TABLES;');
	}
	
	
	/** 
	* Enables Amazon Cloud Storage - S3 - Suport and sets the settings (Should be called before Executing the backup)
	*
	* @param array S3ConfigVars; Array of Amazon config values (accessKey,secretKey,bucketName)
	* @return Void
	* @access private
	* @see finalizeBackup()
	*
	*/
	
	public function enableS3Support(Array $S3ConfigVars = NULL){
		if(!empty($S3ConfigVars)){
			$this->s3Config = $S3ConfigVars;
		}
		
		if(empty($this->s3Config)){
			throw new Exception("Error ::: Missing Amazon S3 Configuration Values");
		}else{
			
			//Just to make sure the user provided all S3 Connection fields
			if(!isset($this->s3Config['accessKey']) || !isset($this->s3Config['secretKey'])  || !isset($this->s3Config['bucketName'])){
				throw new Exception("<h3>Missing one or more Amazon S3 configuration Array keys<h3>
				<br>Please validate you array has the following keys: <br>
					1- accessKey<br>
					2- secretKey<br>
					3- bucketName");
			}
			
			$this->transferToS3 = true;
		}
	}
	
	/** 
	* Executes the backup process itself and creates SQL Dumps in the folder
	*
	* @return void
	* @access public
	*
	*/
	public function executeBackup(){
		//Prepare a new Empty directory to hold up the backup files
		$this->createNewBackupDirectory();
		
		if($this->dumpTableFiles){
			//Execute a list all tables query to select all DB Table names
			$dbTablesList = $this->listDbTables();
			
			while($row = $dbTablesList->fetch_assoc()){ //loop on eatch table of the query result
				//extract the table name from the curren row
				$table_name = $row["Tables_in_".$this->databaseVars['database_name']];
				
				
				if(!in_array($table_name,$this->excludeTables)){//validate the table name is not within the excluded tables, if excluded nothing will happen and we will shift to the next table in the list 
					
					//create the file name (Prefixed with db_backup and suffixed with date and time)
					$file_name = "db_backup_".$table_name."_".date('Y_m_d_H_i').".sql";
					
					//Execute the dump command
					system("mysqldump --opt --user='".$this->databaseVars['login']."' --password='".$this->databaseVars['password']."' ".$this->databaseVars['database_name']." ".$table_name." > ".$this->folderName.'/'.$file_name);
				}
			}
		}else{
			$file_name = "db_backup_ALL_".date('Y_m_d_H_i').".sql";
			system("mysqldump --opt --user='".$this->databaseVars['login']."' --password='".$this->databaseVars['password']."' ".$this->databaseVars['database_name']." > ".$this->folderName.'/'.$file_name);
		}
		$this->finalizeBackup();
	}
	
	
		/** 
	* Compresses generated dump file(s), deletes raw sql file(s) and closes all opened connection
	*
	* @return void
	* @access private
	*
	*/
	private function finalizeBackup(){
		//Compress files in the dump folder
		system("tar -zcvf ".$this->folderName.".tar.gz ".$this->folderName);
		
		//Delete nested files and directories
		$this->recursiveDirRemove($this->folderName);
		
		//Close DB Connection
		$this->dbObject->close();
		
		//transfer the compressed file to Amazon S3 storage
		if($this->transferToS3){
			$this->transferToAmazon();
		}
	}
	
	
	
	/** 
	* Sets the directory for the backup files
	*
	* @param string $directory_path - backup directory path
	* @param boolean $force_create - backup directory path
	* @return Boolean
	* @access public
	* @see createDir()
	*
	*/
	public function setBackupDirectory($directory_path,$force_create = true){
		//if directory doesn't exist attempt to create it after checking the $force_create param 
		if(!is_dir($directory_path)){
			if($force_create){
				$this->createDir($directory_path);
			}else{
				throw new Exception("Specified Backup directory doesn't exist");
			}
		}
		$this->backupDir = $directory_path;
		return true;
	}
	
	/** 
	* Creates a directory recursively with a full permission access 
	*
	* @param string $directory_path - absolute directory path
	* @return Boolean
	* @access private
	*
	*/
	private function createDir($directory_path){
		if(mkdir($directory_path,0777,true)){
			return true;
		}else{
			throw new Exception("<h3>Failed to create Directroy:</h3> '<b>".$directory_path."</b>' !");
		}
	}
	
	
	/** 
	* Removes directory and all its contents recursively 
	*
	* @param string $directory_path - absolute directory path
	* @return Boolean
	* @access private
	* @see clearDirectoryContents()
	*
	*/
	private function recursiveDirRemove($directory_path) {
		 $this->clearDirectoryContents($directory_path);
		 return rmdir($directory_path);
	}
	
	/** 
	* Clear all directory contents (Files and Directories) 
	*
	* @param string $directory_path - absolute directory path
	* @return void
	* @access private
	*
	*/
	private function clearDirectoryContents($directory_path){
		if (is_dir($directory_path)) {
			 	$dir_contents = scandir($directory_path);
			 	foreach ($dir_contents as $content){
			 		if ($content != "." && $content != "..") {
						if(filetype($directory_path."/".$content) == "dir"){
							$this->clearDirectoryContents($directory_path."/".$content);
						}else{ 
							unlink($directory_path."/".$content);
						}
					}
				}
		 		reset($dir_contents);
		}
	}
	/** 
	* Generates a New Folder for the current Backup Execution Session
	*
	* @return void
	* @access private
	*
	*/
	private function createNewBackupDirectory(){
		$folder_name = $this->databaseVars['database_name']."_backup_".time();
		mkdir($this->backupDir."/".$folder_name);
		$this->folderName = $this->backupDir."/".$folder_name;
	}
	
	/** 
	* Sets the type of dump that will result from the execution process
	*
	* @param int $type 1=Single file for each table, 0=One file for the whole database 
	* @return void
	* @access private
	*
	*/
	public function setDumpType($type){
		switch($type){
			case 1:
				$dumpTableFiles = true;
			break;
			case 0:
				$dumpTableFiles = false;
			break;
			default:
				$dumpTableFiles = true;
		}
	}
	
	
	/** 
	* Transfers the Compressed Backup file to Amazon S3
	*
	* @return void
	* @access private
	*
	*/
	private function transferToAmazon(){
		$uploadFile = $this->folderName.".tar.gz";
		require_once('S3.php');
		//Create a new Instance of the S3 Object
		$s3 = new S3($this->s3Config['accessKey'], $this->s3Config['secretKey'], false);
			
		// Put our file with Private access
		if ($s3->putObjectFile($uploadFile, $this->s3Config['bucketName'], $uploadFile, S3::ACL_PRIVATE)) {
			throw new Exception("S3::putObjectFile(): File copied to {".$this->s3Config['bucketName']."}".$uploadFile);
		} else {
			throw new Exception("S3::putObjectFile(): Failed to copy file");
		}
	}
}