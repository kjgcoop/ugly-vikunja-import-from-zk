<?php // KJ created on 3/21/23 based on https://stackoverflow.com/questions/28290332/how-to-create-custom-helper-functions-in-laravel

namespace App\Helpers;

use Http;
use Log;
use App\Models\VikunjaLabel;
use App\Models\VikunjaLabelPool;

class VikunjaHelper
{
    public static function getHeaders($thisToo = []) {
        $headers = [
            'Authorization' => 'Bearer '.env('VIKUNJA_API_KEY')
        ];

        return array_merge($headers, $thisToo);
    }

    public static function get($url, $body = '') {
        Log::stack(['daily', 'stderr'])->info("Getting info from $url via get");

        return Http::withHeaders(self::getHeaders())
//            ->withBody(json_encode($body), 'application/json')
            ->get($url);
    }


    public static function post($url, $body) {
        Log::stack(['daily', 'stderr'])->info("Posting info to $url");

        return Http::withHeaders(self::getHeaders())
            ->withBody(json_encode($body), 'application/json')
            ->post($url);
    }

    public static function put($url, $body) {
        Log::stack(['daily', 'stderr'])->info("Putting info to $url");

        return Http::withHeaders(self::getHeaders())
            ->withBody(json_encode($body), 'application/json')
            ->put($url);
    }

    public static function putFile($url, $filename, $mimetype) {
//        dd('In putFile');
        Log::stack(['daily', 'stderr'])->info('In put file with file '.$filename);

        $client = new \GuzzleHttp\Client();
        $result = $client->request('PUT', $url, [
            'headers' => ['Authorization' => 'Bearer '.env('VIKUNJA_API_KEY')],
            'multipart' => [
                [
                    'name' => 'files',
                    'contents' => file_get_contents($filename),
//                    'headers' => [ 'Content-Type' => $mimetype ]
                    'headers' => [ 'Content-Type' => 'multipart/form-data' ]
                ]
            ],
        ]);

        \Log::stack(['daily', 'stderr'])->info("Attempted to upload attachment to ".$url);

        return $result;
    }
}
