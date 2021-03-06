<?php
require __DIR__ . '/vendor/autoload.php';

use Ifsnop\Mysqldump as IMysqldump;

try {
  writeLog("=========================================================", false);
  $dotenv = new Dotenv\Dotenv(__DIR__);
  $dotenv->load();  
} catch (Exception $e) {
  echo "Please provide .env config . see .env.example!\n";die();
}

define('APPLICATION_NAME', getenv('APPLICATION_NAME'));
define('CREDENTIALS_PATH', '~/.credentials/' . getenv('CREDENTIALS_NAME'));
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
define('DIRECTORY_TO_SAVE', getenv('DIRECTORY_TO_SAVE'));
// If modifying these scopes, delete your previously saved credentials
// at ~/.credentials/drive-php-quickstart.json
define('SCOPES', implode(' ', array(
  Google_Service_Drive::DRIVE,
  )
));

if (php_sapi_name() != 'cli') {
  writeLog("Check the Request Come From", false);
  throw new Exception('This application must be run on the command line.');
  writeLog("Check the Request Come From: OK!", false);
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  writeLog("Getting Permission from Google Drive!");

  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    writeLog("Use saved credentials");
    $accessToken = file_get_contents($credentialsPath);
  } else {
    writeLog("Get new credentials");
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, $accessToken);
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

/**
 * Upload file to Goole Drive
 * @param string $fileName Name of file
 * @return void
 */
function uploadFileToDrive($fileName)
{
  // Get the API client and construct the service object.
  $client = getClient();
  $service = new Google_Service_Drive($client);

  $file = new Google_Service_Drive_DriveFile();

  $currentFile = __DIR__ . '/' . $fileName;
  $currentFileInfo = pathinfo($currentFile);
  $currentFileMime = mime_content_type($currentFile);

  // Set the metadata
  $file->setName($currentFileInfo['filename']);
  $file->setDescription( $currentFileInfo['filename'] . " is uploaded by automated system!" );
  $file->setMimeType($currentFileMime);

  $isDirectory = DIRECTORY_TO_SAVE;
  if ($isDirectory) {
    $file->setParents(array(DIRECTORY_TO_SAVE));
  }

  writeLog("Upload file to Google Drive Started");

  try {
    $createdFile = $service->files->create($file, array(
                    'data' => file_get_contents($currentFile),
                    'mimeType' => $currentFileMime,
                    'uploadType'=> 'multipart'
                  ));
  } catch (\Exception $e) {
    writeLog("Upload canceled! We got an error! : " . $e->getMessage()); die;
  }


  writeLog("Upload file to Google Drive Finished");

  return $createdFile;
}

/**
 * Backup & Compress DB to BZip2
 * @return string Filename of DB.bz2
 */
function backupDB() {
  $dumpSettings = array(
    'compress' => 'Bzip2',
  );
  try {
    writeLog("Dumping & Compressing DB Started");
    $mysqlPath = __DIR__ . "/";
    $mysqlFile = 'MYSQL_DUMP_' . date('Y_m_d') . ".bz2";
    $dump = new IMysqldump\Mysqldump('mysql:host='. getenv('MYSQL_HOST') .';dbname=' . getenv('MYSQL_DB_NAME'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), $dumpSettings);
    $dump->start($mysqlPath . $mysqlFile);
    writeLog("Dumping & Compressing DB Finished");

    return $mysqlFile;
  } catch (\Exception $e) {
    writeLog('mysqldump-php error: ' . $e->getMessage());
    echo 'mysqldump-php error: ' . $e->getMessage();
  }
}

function writeLog($message = "", $postToSlack = true) {
  if ($postToSlack) {
    postToSlack($message);
  }

  echo "[" . date('d-m-Y H:i:s') . "] " . $message . "\n";  
}

/**
 * Post to Slack
 * @return void
 */ 
function postToSlack($message)
{
  if ((bool)getenv('SLACK') && trim($message)!=='') {
    $slack = new Maknz\Slack\Client(getenv('SLACK_URL'));
    $slack->send('*[BACKUP DB]* ' . $message);
  }
}

$mysqlFile = backupDB();
uploadFileToDrive($mysqlFile);

writeLog("Deleting local file!");
unlink(__DIR__ . "/" . $mysqlFile);
writeLog("Local file deleted");
writeLog("=========================================================", false);