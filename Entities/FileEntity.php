<?php
/**
 *File name : FileEntity.php  / Date: 8/13/2021 - 11:39 AM
 *Code Owner: Tke / Phone: 0367313134 / Email: thedc.it.94@gmail.com
 */

namespace App\Entities;

use App\Helpers\S3Lib;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class FileEntity
{

    protected $awsAccessId;
    protected $awsSecretKey;
    protected $awsBucketName;
    protected $awsPath;

    public function __construct()
    {
        $this->awsAccessId   = env('AWS_ACCESS_KEY_ID', null);
        $this->awsSecretKey  = env('AWS_SECRET_ACCESS_KEY', null);
        $this->awsBucketName = env('AWS_BUCKET', null);
        $this->awsPath       = env('AWS_USE_PATH_STYLE_ENDPOINT', null);
    }

    public function saveFileAmazon($file, $fileName, $path = '', $checkImage = true)
    {
        $type = $file->getClientOriginalExtension(); // getting image extension
        if ($checkImage && !$this->isExtensionImage($type)) {
            return false;
        }

        $path          = $this->makePath($path, false);
        $save_fileName = $path.$fileName.".".$type;

        $s3Lib  = new S3Lib($this->awsAccessId, $this->awsSecretKey);
        $upload = $s3Lib->uploadFile($save_fileName, $this->awsBucketName, $file, [
            'ContentType' => 'image/'.$type,
        ]);

        if (!$upload['result']) {
            return false;
        }

        return $upload['url'];
    }

    public function saveFileAmazonBase64($base64, $fileName, $path = '', $checkImage = true)
    {
        $file = base64_decode($base64);
        $type = $this->getImageMimeType($file);

        if ($checkImage && !isExtensionImage($type)) {
            return false;
        }

        $path          = $this->makePath($path, false);
        $save_fileName = $path.$fileName.".".$type;

        $s3Lib  = new S3Lib($this->awsAccessId, $this->awsSecretKey);
        $upload = $s3Lib->uploadFile($save_fileName, $this->awsBucketName, null, [
            'Body'        => $file,
            'ContentType' => 'image/'.$type,
        ]);
        if (!$upload['result']) {
            return false;
        }
        return $upload['url'];
    }

    public function makePath($folder, $createNewFolder = true)
    {
        $folder = is_array($folder) && isset($folder['path']) ? $folder['path'] : $folder;

        if ($createNewFolder) {
            $path = $this->makePathByDate($folder, Carbon::today());
        } else {
            $date  = Carbon::today();
            $day   = $date->copy()->format('d');
            $month = $date->copy()->format('m');
            $year  = $date->copy()->format('Y');
            $path  = $folder.'/'.$year.'/'.$month.'/'.$day.'/';
        }

        if ($path[0] == '/') {
            $path = ltrim($path, $path[0]);
        }

        return $path;
    }

    private function isExtensionImage($type)
    {
        $types = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'bmp', 'heic', 'heif'];

        $type = strtolower($type);

        return in_array($type, $types);
    }

    private function getImageMimeType($file)
    {
        $imageMimeTypes = [
            "jpeg" => "FFD8",
            "png"  => "89504E470D0A1A0A",
            "gif"  => "474946",
            "bmp"  => "424D",
            "tiff" => "4949",
        ];

        foreach ($imageMimeTypes as $mime => $hexBytes) {
            $bytes = $this->getBytesFromHexString($hexBytes);
            if (substr($file, 0, strlen($bytes)) == $bytes) {
                return $mime;
            }
        }

        return false;
    }

    private function getBytesFromHexString($hexData)
    {
        $bytes = [];

        for ($count = 0; $count < strlen($hexData); $count += 2) {
            $bytes[] = chr(hexdec(substr($hexData, $count, 2)));
        }

        return implode($bytes);
    }

    private function makePathByDate($folder, Carbon $date = null)
    {
        if (is_null($date)) {
            $date = Carbon::today();
        }
        $day          = $date->copy()->format('d');
        $month        = $date->copy()->format('m');
        $year         = $date->copy()->format('Y');
        $year_folder  = $this->getDirectory($folder, $year);
        $month_folder = $this->getDirectory($year_folder, $month);
        return $this->getDirectory($month_folder, $day);
    }

    private function getDirectory($mainFolder, $subFolder)
    {
        if (substr($mainFolder, -1) != '/') {
            $mainFolder .= '/';
        }
        if (substr($mainFolder, -1) != '/') {
            $mainFolder .= '/';
        }

        $newPath = $mainFolder.$subFolder;
        if (!File::exists($newPath)) {
            File::makeDirectory($newPath, 755, true);
        }

        return $newPath;
    }

}
