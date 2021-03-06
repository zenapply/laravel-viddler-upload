<?php

namespace LeadThread\Viddler\Upload\Components;

use Exception;
use LeadThread\Viddler\Upload\Traits\CanLog;
use LeadThread\Viddler\Api\Exceptions\ViddlerException;
use LeadThread\Viddler\Api\Viddler as ViddlerV2;
use LeadThread\Viddler\Upload\Models\Viddler;
use LeadThread\Viddler\Upload\Events\ViddlerProgress;

class ViddlerClient
{
    use CanLog;
    
    protected $client;
    protected $session_id;
    protected $record_token;

    protected function prepareUpload()
    {
        if (empty($this->session_id)) {
            $this->auth();
        }

        return $this->client->viddler_videos_prepareUpload([
            'response_type' => 'json',
            'sessionid' => $this->session_id
        ]);
    }

    protected function executeUpload($endpoint, $postFields)
    {
        try {
            $ch = curl_init();

            if (false === $ch) {
                throw new Exception('failed to initialize');
            }

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $response     = curl_exec($ch);

            if (false === $response) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }

            $info         = curl_getinfo($ch);
            $header_size  = $info['header_size'];
            $header       = substr($response, 0, $header_size);
            $result       = unserialize(substr($response, $header_size));
            curl_close($ch);
        } catch (Exception $e) {
            $err = sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage());
            throw new Exception($err);
        }

        return $result;
    }

    protected function executeCheck(Viddler $model)
    {
        if (empty($this->session_id)) {
            $this->auth();
        }

        return $this->client->viddler_encoding_getStatus2([
            'video_id' => $model->viddler_id,
            'response_type' => 'json',
            'sessionid' => $this->session_id
        ]);
    }

    public function check(Viddler $model)
    {
        if ($model->status === "encoding") {
            $this->info("{$model} is checking encoding status on viddler.com");
            $response = $this->executeCheck($model);
            
            $files = collect($response["list_result"]["video_encoding_list"][0]["video_file_encoding_list"]);

            if ($files->count() < 1) {
                throw new ViddlerException("No files were returned from viddler");
            } else {
                $progressAll = $files->sum('encoding_progress')/$files->count();

                $files = $files->filter(function ($file) {
                    return $file["profile_name"] === "360p";
                });

                // Status
                $x = 0;
                foreach ($files as $file) {
                    $x += $file['status'] === 'ready' ? 1 : 0;
                }
                $status = $x/$files->count() === 1;

                // Progress percentage
                $progress360p = $files->sum('encoding_progress')/$files->count();
                $oldProgress = $model->encoding_progress;
                $model->encoding_progress = round(max($progress360p, $progressAll));

                // Check it
                if ($model->encoding_progress == 100 && $status) {
                    // This method will save the model
                    $model->updateStatusTo('finished');
                } else {
                    if ($model->encoding_progress == 100) {
                        $model->encoding_progress = 99;
                    }
                    $model->save();

                    if ($oldProgress !== $model->encoding_progress) {
                        $this->info("{$model}'s encoding progress: {$model->encoding_progress}");
                        event(new ViddlerProgress($model));
                    }
                }
            }
        }

        return $model;
    }

    public function upload(Viddler $model)
    {
        $this->info("{$model} is uploading to viddler.com");
        //Fire Event
        $model->updateStatusTo('uploading');

        //Path
        $file = $model->getFile();
        $path = $file->getFullPath();

        $response = $this->prepareUpload();

        $token      = $response['upload']['token'];
        $endpoint   = $response['upload']['endpoint'];

        //Prepare the data!
        $postFields = array();
        $postFields['description'] = "";
        $postFields['tags'] = "";
        $postFields['title'] = $model->title;
        $postFields['uploadtoken'] = $token;
        $postFields['view_perm'] = "embed";
        $postFields['file'] = curl_file_create($path, $model->mime);

        //Send it!
        $result = $this->executeUpload($endpoint, $postFields);

        if (empty($result['video']['id'])) {
            throw new ViddlerException('Viddler did not return a video id!');
        }

        $model->viddler_id = $result['video']['id'];
        $model->uploaded = true;
        $model->updateStatusTo('encoding');

        return $model;
    }

    /**
     * Authenticate with viddler
     */
    protected function auth()
    {
        $key  = config('viddler.auth.key');
        $user = config('viddler.auth.user');
        $pass = config('viddler.auth.pass');

        //Create Client
        if (empty($this->client)) {
            $this->client = new ViddlerV2($key);
        }

        $resp = $this->client->viddler_users_auth(array('user' => $user, 'password' => $pass));

        $this->session_id = $resp['auth']['sessionid'];
        
        if (!empty($resp['auth']['record_token'])) {
            $this->record_token = $resp['auth']['record_token'];
        }
    }

    /**
     * Return the session id
     */
    protected function getSessionId()
    {
        if (empty($this->session_id)) {
            $this->auth();
        }
        return $this->session_id;
    }

    /**
     * Return the record token for this session
     */
    protected function getRecordToken()
    {
        if (empty($this->session_id)) {
            $this->auth();
        }
        return $this->record_token;
    }
}
