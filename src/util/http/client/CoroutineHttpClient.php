<?php
namespace rap\util\http\client;

use rap\util\http\HttpClient;
use rap\util\http\HttpResponse;
use Swoole\Coroutine\Http\Client;

class CoroutineHttpClient implements HttpClient  {
    private static function parseUrl($url) {
        $port = 80;
        if (strpos($url, 'http://') === 0) {
            $url = str_replace('http://', '', $url);
        } elseif (strpos($url, 'https://') === 0) {
            $url = str_replace('https://', '', $url);
            $port = 443;
        }
        $po = strpos($url, '/');
        if ($po) {
            $host = substr($url, 0, $po);
            $path = substr($url, $po);
        } else {
            $host = $url;
            $path = '/';
        }
        if (strpos($host, ':') > 0) {
            $hp = explode(':', $host);
            $host = $hp[ 0 ];
            $port = $hp[ 1 ];
        }
        return [$host, $path, $port];
    }

    public function get($url, $header = [], $timeout = 0.5) {
        $hostPath = self::parseUrl($url);
        if (!$hostPath[ 0 ]) {
            return new HttpResponse(-1, [], '');
        }
        $cli = new Client($hostPath[ 0 ], $hostPath[ 2 ], $hostPath[ 2 ] == 443);
        $cli->set(['timeout' => $timeout]);
        if ($header) {
            $cli->setHeaders($header);
        }
        $cli->get($hostPath[ 1 ]);
        $response = new HttpResponse($cli->statusCode, $cli->headers, $cli->body);
        $cli->close();
        return $response;
    }

    public function post($url, $header = [], $data = [], $timeout = 0.5) {
        $hostPath = self::parseUrl($url);
        if (!$hostPath[ 0 ]) {
            return new HttpResponse(-1, [], '');
        }
        $cli = new Client($hostPath[ 0 ], $hostPath[ 2 ], $hostPath[ 2 ] == 443);
        $cli->set(['timeout' => $timeout]);
        if ($header) {
            $cli->setHeaders($header);
        }
        $cli->post($hostPath[ 1 ], $data);
        $response = new HttpResponse($cli->statusCode, $cli->headers, $cli->body);
        $cli->close();
        return $response;
    }

    public function put($url, $header = [], $data = [], $timeout = 0.5) {
        $hostPath = self::parseUrl($url);
        if (!$hostPath[ 0 ]) {
            return new HttpResponse(-1, [], '');
        }
        $cli = new Client($hostPath[ 0 ], $hostPath[ 2 ], $hostPath[ 2 ] == 443);
        $cli->set(['timeout' => $timeout]);
        if ($header) {
            $cli->setHeaders($header);
        }
        if ($data && is_string($data)) {
            $cli->post($hostPath[ 1 ], $data);
        } else {
            $cli->post($hostPath[ 1 ], json_encode($data));
        };
        $response = new HttpResponse($cli->statusCode, $cli->headers, $cli->body);
        $cli->close();
        return $response;
    }

    public function upload($url, $header = [], $data = [], $files = [], $timeout = 5) {
        $hostPath = self::parseUrl($url);
        if (!$hostPath[ 0 ]) {
            return new HttpResponse(-1, [], '', $hostPath[ 2 ] == 443);
        }
        $cli = new Client($hostPath[ 0 ], $hostPath[ 2 ]);
        $cli->set(['timeout' => $timeout]);
        if ($header) {
            $cli->setHeaders($header);
        }
        foreach ($files as $file => $name) {
            $cli->addFile($file, $name);
        }
        $cli->post($hostPath[ 1 ], $data);
        $response = new HttpResponse($cli->statusCode, $cli->headers, $cli->body);
        $cli->close();
        return $response;
    }


}