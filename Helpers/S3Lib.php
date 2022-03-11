<?php


namespace App\Helpers;


use Aws\Credentials\CredentialProvider;
use Aws\Rekognition\RekognitionClient;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Log;

class S3Lib
{
    protected $access_key_id;
    protected $secret_access_key;

    public function __construct($access_key_id=null,$secret_access_key=null)
    {
        $this->access_key_id = $access_key_id;
        $this->secret_access_key = $secret_access_key;
    }

    public function uploadFile($fileName, $bucketName, $file=null, $other_params=[])
    {

        $client = $this->getS3Client();
        try {
            $config = [
                'ACL'        => 'public-read',
                'Bucket'     => $bucketName,
                'Key'        => $fileName
            ];
            if(!is_null($file))
                $config['SourceFile'] = $file;
            $config = array_merge($config,$other_params);
            $result = $client->putObject($config);
        } catch (AwsException $exception) {
            $result = ['result' => false, 'message' => $exception->getMessage()];
            Log::info("Upload s3 failed:".json_encode($result));

            return $result;
        }
        if ($result["@metadata"]["statusCode"] == '200') {
            return [
                'result' => true,
                'url'    => $result["ObjectURL"],
                'tag'    => $result['ETag']
            ];
        }

        return [
            'result'  => false,
            'message' => ""
        ];
    }

    public function getS3Client()
    {
        if(is_null($this->access_key_id) || is_null($this->secret_access_key))
            $credentials = CredentialProvider::env();
        else{
            $credentials = new Credentials($this->access_key_id, $this->secret_access_key);
        }

        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'credentials' => $credentials
        ]);

        return $s3Client;
    }

    public function getS3RekognitionClient()
    {
        $provider = CredentialProvider::env();
        $client = new RekognitionClient([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'credentials' => $provider
        ]);

        return $client;
    }

    public function deleteFile($fileName, $bucketName){
        $client = $this->getS3Client();
        try {
            $config = [
                'ACL'        => 'public-read',
                'Bucket'     => $bucketName,
                'Key'        => $fileName
            ];
            $client->deleteObject($config);
        } catch (AwsException $exception) {
            $result = ['result' => false, 'message' => $exception->getMessage()];
            Log::info("Delete s3 failed:".json_encode($result));

            return $result;
        }
    }

}
