<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.04.19
 * Time: 18:47
 */

namespace somov\lfd;


use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client as BaseClient;
use yii\httpclient\Request;
use yii\httpclient\Transport as BaseTransport;


/**
 * Class LargeFileDownloaderTransport
 * @package somov\lfd
 *

 */
class Transport extends BaseTransport
{

    /**
     * @var int
     */
    public $threadCount = 2;

    /**
     * @param Request $request
     * @return \yii\httpclient\Response
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function send($request)
    {
        /**
         * @var Client $client
         */
        $client = $request->client;

        $threads = Thread::calculate($client, $request->getUrl(), $this->threadCount);
        $requests = [];
        $progress = 0;

        foreach ($threads as $thread) {

            if ($thread->totalExists() < $thread->length) {
                $request = new Request(ArrayHelper::merge($client->requestConfig, [
                    'client' => $client,
                    'options' => [
                        CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadSize, $downloaded)
                        use (&$threads, &$progress, $client) {
                            if ($downloadSize > 0) {
                                $p = Thread::progress($resource, $downloaded, $threads);
                                if ($p['percent'] !== $progress) {
                                    $progress = ['percent'];
                                    $client->trigger(Client::EVENT_PROGRESS, new ProgressEvent($p));
                                }
                            }
                        },
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_FILE => $thread->getFileResource(),
                    ],
                    'headers' => [
                        'range' => "bytes=$thread->start-$thread->end",
                        'Cache-Control' => 'no-cache'
                    ]
                ]));

                $thread->request = $request;
                $requests[] = $request;
            }
        }

        $this->batchSendThreads($requests, $threads);

        return $client->createResponse($threads);

    }


    /**
     * @param Request|\yii\httpclient\Request $request
     * @return Client
     * @throws InvalidConfigException
     */
    protected function getClient($request)
    {
        if ($request->client instanceof Client) {
            return $request->client;
        }

        throw new InvalidConfigException('Type of client mast be ' . Client::class);
    }


    /**
     * @param Request[] $requests
     * @param Thread[] $threads
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function batchSendThreads(array $requests, &$threads)
    {
        $curlBatchResource = curl_multi_init();

        $token = '';
        $curlResources = [];
        $responseHeaders = [];
        foreach ($requests as $key => $request) {

            $this->getClient($request);

            $curlOptions = $this->prepare($request);
            $curlResource = $this->initCurl($curlOptions);

            $threads[$key]->setCurlResource($curlResource);

            $token .= $request->client->createRequestLogToken($request->getMethod(), $curlOptions[CURLOPT_URL],
                    $curlOptions[CURLOPT_HTTPHEADER], $threads[$key]->getHash()) . "\n\n";

            $responseHeaders[$key] = [];
            $this->setHeaderOutput($curlResource, $responseHeaders[$key]);
            $curlResources[$key] = $curlResource;
            curl_multi_add_handle($curlBatchResource, $curlResource);
        }

        Yii::info($token, BaseClient::class);
        Yii::beginProfile($token, BaseClient::class);

        try {
            $isRunning = null;
            do {
                // See https://bugs.php.net/bug.php?id=61141
                if (curl_multi_select($curlBatchResource) === -1) {
                    usleep(100);
                }
                do {
                    $curlExecCode = curl_multi_exec($curlBatchResource, $isRunning);
                } while ($curlExecCode === CURLM_CALL_MULTI_PERFORM);
            } while ($isRunning > 0 && $curlExecCode === CURLM_OK);
        } catch (\Exception $e) {
            Yii::endProfile($token, __METHOD__);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        Yii::endProfile($token, __METHOD__);

        $responseContents = [];
        foreach ($curlResources as $key => $curlResource) {
            $responseContents[$key] = '';
            //$responseContents[$key] = curl_multi_getcontent($curlResource);
            curl_multi_remove_handle($curlBatchResource, $curlResource);
        }

        curl_multi_close($curlBatchResource);

        foreach ($requests as $key => $request) {
            $response = $request->client->createResponse($responseContents[$key], $responseHeaders[$key]);
            $request->afterSend($response);
            $threads[$key]->response = $response;
        }

    }

    /**
     * Prepare request for execution, creating cURL resource for it.
     * @param Request $request request instance.
     * @return array cURL options.
     */
    private function prepare($request)
    {
        $request->prepare();

        $curlOptions = $this->composeCurlOptions($request->getOptions());

        $method = strtoupper($request->getMethod());
        switch ($method) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                break;
            default:
                $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        $content = $request->getContent();
        if ($content === null) {
            if ($method === 'HEAD') {
                $curlOptions[CURLOPT_NOBODY] = true;
            }
        } else {
            $curlOptions[CURLOPT_POSTFIELDS] = $content;
        }

        $curlOptions[CURLOPT_URL] = $request->getFullUrl();
        $curlOptions[CURLOPT_HTTPHEADER] = $request->composeHeaderLines();

        return $curlOptions;
    }

    /**
     * Initializes cURL resource.
     * @param array $curlOptions cURL options.
     * @return resource prepared cURL resource.
     */
    private function initCurl(array $curlOptions)
    {
        $curlResource = curl_init();
        foreach ($curlOptions as $option => $value) {
            curl_setopt($curlResource, $option, $value);
        }

        return $curlResource;
    }

    /**
     * Composes cURL options from raw request options.
     * @param array $options raw request options.
     * @return array cURL options, in format: [curl_constant => value].
     */
    private function composeCurlOptions(array $options)
    {
        static $optionMap = [
            'protocolVersion' => CURLOPT_HTTP_VERSION,
            'maxRedirects' => CURLOPT_MAXREDIRS,
            'sslCapath' => CURLOPT_CAPATH,
            'sslCafile' => CURLOPT_CAINFO,
            'sslLocalCert' => CURLOPT_SSLCERT,
            'sslLocalPk' => CURLOPT_SSLKEY,
            'sslPassphrase' => CURLOPT_SSLCERTPASSWD,
        ];

        $curlOptions = [];
        foreach ($options as $key => $value) {
            if (is_int($key)) {
                $curlOptions[$key] = $value;
            } else {
                if (isset($optionMap[$key])) {
                    $curlOptions[$optionMap[$key]] = $value;
                } else {
                    $key = strtoupper($key);
                    if (strpos($key, 'SSL') === 0) {
                        $key = substr($key, 3);
                        $constantName = 'CURLOPT_SSL_' . $key;
                        if (!defined($constantName)) {
                            $constantName = 'CURLOPT_SSL' . $key;
                        }
                    } else {
                        $constantName = 'CURLOPT_' . strtoupper($key);
                    }
                    $curlOptions[constant($constantName)] = $value;
                }
            }
        }
        return $curlOptions;
    }

    /**
     * Setup a variable, which should collect the cURL response headers.
     * @param resource $curlResource cURL resource.
     * @param array $output variable, which should collection headers.
     */
    private function setHeaderOutput($curlResource, array &$output)
    {
        curl_setopt($curlResource, CURLOPT_HEADERFUNCTION, function ($resource, $headerString) use (&$output) {
            $header = trim($headerString, "\n\r");
            if (strlen($header) > 0) {
                $output[] = $header;
            }
            return mb_strlen($headerString, '8bit');
        });
    }

}
