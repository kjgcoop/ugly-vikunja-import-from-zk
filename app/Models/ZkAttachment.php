<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;
use Illuminate\Support\Facades\Storage;

use ZkHelper;


class ZkAttachment extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $fileUrl;
    protected $fileName;
    protected $localPath;
    protected $mimetype;


    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->fileUrl = $zkApiDump->fileUrl;
        $this->fileName = $zkApiDump->fileName;
        $this->mimetype = $zkApiDump->mimetype;
    }

    public function isDownloadable() {
        return $this->mimetype !== null && $this->fileUrl !== '';
    }

    public function download() {
        if (isset($this->fileUrl) && $this->fileUrl !== null && $this->isDownloadable()) {
//            dd('Why is it saying the URL is blank?', $this);

            Log::stack(['daily', 'stderr'])->info("Attempting to download attachment from ".$this->fileUrl);
        } else {
  //          dd('Why is it saying the URL is blank?', $this);

            Log::stack(['daily', 'stderr'])->info("Attempted to download an attachment that can't be: ".$this->fileUrl);
            throw new \Exception("Attempted to download an attachment that can't be: ".$this->fileUrl);
        }

        $result = Http::get($this->getFileUrl());

        if (!$result->successful()) {
//            throw new \Exception('Unable to download attachment from '.$this->getFileUrl());
            Log::stack(['daily', 'stderr'])->error("Failed to download attachment from ".$this->fileUrl);
        }

        $bits = $result->getBody()->getContents();
        file_put_contents($this->getLocalPath(), $bits);
        unset($bits); // Free up the memory
    }

    public function getFileUrl() {
        return $this->fileUrl;
    }
    public function getMimetype() {
        return $this->mimetype;
    }

    public function getLocalPath() {
        if ($this->localPath === null) {
            $this->localPath = env('DOWNLOAD_ZK_ATTACHMENTS_TO').date('U').'_'.basename($this->fileUrl);
        }
        return $this->localPath;
    }

    public function getFileName() {
        return $this->fileName;
    }
}
