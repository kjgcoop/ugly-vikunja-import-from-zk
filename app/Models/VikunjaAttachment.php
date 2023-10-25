<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;

use GuzzleHttp;
use GuzzleHttp\Psr7;

use App\Models\ZkAttachment;
use App\Helpers\VikunjaHelper;

class VikunjaAttachment extends Model
{
    use HasFactory;

    protected $zkApiDump;
    protected $localPath;
    protected $mimetype;
    protected $fileName;
    protected $fileUrl;



    public function __construct() {
    }

    public function convertFromZk(ZkAttachment $zkAttachment) {
//        $this->zkEquivalent = $zkAttachment;
        $this->localPath = $zkAttachment->getLocalPath();
        $this->mimetype = $zkAttachment->getMimetype();
        $this->fileUrl = $zkAttachment->getFileUrl();
        $this->fileName = $zkAttachment->getFileName();
    }

    public function isDownloadable() {
        Log::stack(['daily', 'stderr'])->info($this->localPath.' checking if downloadable; is $this->mimetype !== null?');
        return $this->mimetype !== null;
    }

    public function getMimetype() {
        return $this->mimetype;
    }

    public function getFileUrl() {
        return $this->fileUrl;
    }

    public function convertToMarkdown(): string {
        if ($this->isDownloadable()) {
            throw new \Exception("You're trying to convert a downloadable file (mimetype ".$this->mimetype.") to Markdown. Instead, try downloading it from ".$this->getFileUrl());
        }

        return 'Confusing attachment (link?): ['.$this->fileName.']('.$this->fileUrl.")\n\n";
    }

    public function upload(int $task_id) {

        Log::stack(['daily', 'stderr'])->info('About to begin uploading '.$this->localPath);
        $url = env('VIKUNJA_URL_START').'/tasks/'.$task_id.'/attachments';
        Log::stack(['daily', 'stderr'])->info('Will be put to '.$url);

        if (!$this->isDownloadable()) {
            Log::stack(['daily', 'stderr'])->info($this->localPath.' is not a downloadable file');
            // Yeah yeah, whatever.
            return true;
        }

        Log::stack(['daily', 'stderr'])->info($this->fileName.' is downloadable/uploadable');

        // Initialize cURL session
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . env('VIKUNJA_API_KEY')
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'files' => new \CURLFile($this->localPath)
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Check for cURL errors and handle the response as needed
        if ($response === false) {
            Log::stack(['daily', 'stderr'])->debug('cURL error uploading file '.$this->localPath.': ' . curl_error($ch));
        }

        Log::stack(['daily', 'stderr'])->info($response);

        return true;
    }

    public function getLocalPath() {
        return $this->getLocalPath();
    }
}
