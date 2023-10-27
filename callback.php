<?php

define('REPO',      'GITHUB_REPOSITORY_TITLE');
define('USER',      'GITHUB_ACCOUNT_USER_NAME');
define('PAT',       'YOUR_PERSONAL_ACCESS_TOKEN');
define('SECRET',    'WEBHOOK_SECRET');
define('HOOK_ID',   'WEBHOOK_ID');

ini_set('memory_limit', '1G');

class GitToLive {

    protected $payload;

    public function __construct(){

        $this->payload = @json_decode(file_get_contents('php://input'), true);

        self::verifyRequest();
        self::sync();
    }

    public function verifyRequest(){
        /*CHECK 1 | Allow only post requests*/
        header("HTTP/1.1 405 Method Not Allowed");
        if($_SERVER['REQUEST_METHOD'] !== 'POST') die('Method not allowed');

        /*CHECK 2 | Validate github webhook id*/
        header("HTTP/1.1 401 Unauthorized");
        if(!isset($_SERVER['HTTP_X_GITHUB_HOOK_ID'])) die('Invalid request, Webhook identity is not present');
        if($_SERVER['HTTP_X_GITHUB_HOOK_ID'] !== HOOK_ID) die('Webhook is not configured yet!');

        /*CHECK 3 | Validate content and check if it's a valid JSON*/
        header("HTTP/1.1 400 Bad Request");
        if(!$this->payload) die('Invalid content in request body, it must be in JSON format');
        
        /*Check 4 | Validate branch*/
        header("HTTP/1.1 201 Accepted");
        if(!isset($this->payload['ref'])) die('Accepted! but can\'t be synced, as branch is not specified');
        if($this->payload['ref'] !== 'refs/heads/master') die('Accepted! but can\'t synced, as it\'s not the master branch');
    }

    public function sync (){
        header("HTTP/1.1 200 OK");
        echo "Sync Started...";
        
        foreach ($this->payload['commits'] as $commit){
            
            foreach ($commit['added'] as $added){
                echo "\nAdding File ". $added; 
                self::createOrUpdateFile($added);
            }
            
            foreach ($commit['modified'] as $modified){
                echo "\nModifying File ". $modified; 
                self::createOrUpdateFile($modified);
            }
            
            foreach ($commit['removed'] as $removed){
                echo "\nRemoving File ". $removed; 
                self::removeFile($removed);
            }
            
        }
        
        echo "\nSync Completed";
    }
    
    public function createOrUpdateFile($file){
        try{
            $dir = '../';
            $pathArray = explode('/', $file);
            $fileName = $pathArray[count($pathArray) - 1];
            
            //Compose dir from array if not exisit
            if(count($pathArray)>1){
                $dir = '..';
                array_pop($pathArray);
                foreach ($pathArray as $path){
                    $dir .= '/'. $path;
                    if(!is_dir($dir)) mkdir($dir);
                }
            }
            
            //Get File Content
            $options = [
                "http" => [
                    "header" => "Authorization: token ". PAT,
                ],
            ];
            
            $context = stream_context_create($options);
            $fileContent = file_get_contents('https://raw.githubusercontent.com/'. USER .'/'. REPO .'/master/'. $file, false, $context);
            
            //Compose File
            file_put_contents($dir.'/'. $fileName, $fileContent);
            echo "Done";
        }catch(Exception $e) {
            echo "\nError: " . $e->getMessage();
        }
        
    }
    
    public function removeFile($file){
        try{
            $dir = '../';
            $pathArray = explode('/', $file);
            $fileName = $pathArray[count($pathArray) - 1];
            
            if(count($pathArray)>1){
                $dir = '..';
                array_pop($pathArray);
                foreach ($pathArray as $path){
                    $dir .= '/'. $path;
                }
            }
            
            unlink($dir.'/'.$fileName);
            echo "Done";
        }catch(Exception $e) {
            echo "\nError: " . $e->getMessage();
        }
    }
}

$GitHub = new GitToLive;

?>
