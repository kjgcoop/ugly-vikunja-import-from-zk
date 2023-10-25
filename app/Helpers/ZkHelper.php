<?php // KJ created on 3/21/23 based on https://stackoverflow.com/questions/28290332/how-to-create-custom-helper-functions-in-laravel

namespace App\Helpers;

use Http;

class ZkHelper
{
    public static function getHeaders($thisToo = []) {
        $zk_api_key = env('ZK_API_KEY');
        $zk_cookie_string = env('ZK_COOKIE_STRING');

        $headers = [
            'Zenkit-API-Key' => $zk_api_key,
            'Cookie' => $zk_cookie_string,
            'Content-Type' => 'application/json',
        ];

        return array_merge($headers, $thisToo);
    }

    public static function get($url) {
        echo "Getting info from $url via get\n\n";

        return Http::withHeaders(self::getHeaders())
            ->get($url);
    }


    public static function post($url, $body) {
        echo "Getting info from $url via post\n\n";

        return Http::withHeaders(self::getHeaders())
            ->withBody(json_encode($body), 'application/json')
            ->post($url);
    }


}
