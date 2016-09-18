<?php

namespace Zenapply\Viddler;

use Illuminate\Http\UploadedFile;
use Viddler_V2;
use Storage;
use Zenapply\Viddler\Models\Video;

class Viddler {
	protected $client;
	protected $session_id;
	protected $record_token;

	/**
	 * Upload and store in database a new viddler video from a file
	 */
    public function upload(UploadedFile $file, $filename)
    {
        //Check file type
        $ok = $this->isMimeSupported($file->getMimeType());

        if($ok === true){
            //Store the file
            $this->saveVideoToNewDisk($file, $filename);
            $video = $this->saveVideoToDatabase($file, $filename);
            
            //Convert File
            $video = $video->start();

            //Done
            return $video;
        } else {
        	$msg = [];
        	$msg[] = "Incorrect file type!";
            if(is_object($file)){
                $msg[] = $file->getClientOriginalExtension();
                $msg[] = "(".$file->getMimeType().")";
            }
            throw new IncorrectVideoTypeException(implode(" ", $msg));
        }
    }

    /**
     * Get the status of a viddler video
     */
    public function status($id) 
    {
    	return "complete";	
    }

	/**
	 * Return the session id
	 */
	public function getSessionId() {
		if(empty($this->session_id)) {
			$this->auth();
		}
		return $this->session_id;
	}

	/**
	 * Return the record token for this session
	 */
	public function getRecordToken() {
		if(empty($this->session_id)) {
			$this->auth();
		}
		return $this->record_token;
	}

	/**
	 * Saves the uploaded file to the waiting disk
	 */
	protected function saveVideoToNewDisk(UploadedFile $file, $filename) {
		$disk = Storage::disk(config('viddler.storage.disks.new'));
		$contents = file_get_contents($file->getRealPath());
		$disk->put($filename, $contents);
	}

	/**
	 * Saves the uploaded file to the waiting disk
	 */
	protected function saveVideoToDatabase(UploadedFile $file, $filename) {
		$video = Video::create([
			'disk' => config('viddler.storage.disks.new'),
			'filename' => $filename,
			'status' => 'new',
			'mime' => $file->getMimeType(),
			'extension' => $file->extension(),
		]);

		return $video;
	}

	/**
	 * Check if a mime is in the supported list
	 */
	protected function isMimeSupported($mime) {
		$supported = config('viddler.mimes');
		return in_array($mime, $supported);
	}

	/**
	 * Authenticate with viddler
	 */
	protected function auth()
	{
		$key  = Config::get('viddler.auth.key');
        $user = Config::get('viddler.auth.user');
        $pass = Config::get('viddler.auth.pass');

        //Create Client
        if (empty($this->client)) {
        	$this->client = new ViddlerV2($key);
        }

        $resp = $this->client->viddler_users_auth(array('user' => $user, 'password' => $pass));
        $resp = $this->checkResponseForErrors($resp);
        $this->$session_id = $this->auth['auth']['sessionid'];
        $this->$record_token = $this->auth['auth']['record_token'];
	}

	protected function checkResponseForErrors ($response) {
		if(isset($response["error"])){
			$msg = [];
			$msg[] = "Viddler Error Code: ".$response["error"]["code"];
			$msg[] = $response["error"]["description"];
			$msg[] = $response["error"]["details"];

			$msg = implode(" | ", $msg);

			switch($response["error"]["code"]){
			case "100":
				throw new ViddlerVideoNotFoundException($msg);
				break;
			default:
				throw new ViddlerException($msg);
				break;
			}
		}

		return $response;
	}
}