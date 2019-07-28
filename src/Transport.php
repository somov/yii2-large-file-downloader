<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 15.04.19
 * Time: 18:47
 *
 * Transport performs actual HTTP request sending.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
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
 * @property-read Thread[] $threads
 */
class Transport extends BaseTransport
{

    /**
     * @var int
     */
    public $threadCount = 2;

    /**
     * @var Thread[]
     */
    private $_threads;

    /**
     * @var integer
     */
    private $_previousDownloadedSize = 0;

    /**
     * @var float
     */
    private $_previousDownloadedTime = 0;

    /**
     * @var float
     */
    private $_startTime = 0;


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

        $this->_threads = Thread::calculate($client, $request->getUrl(), $this->threadCount);
        $requests = [];

        $this->_startTime = $this->_previousDownloadedTime = microtime(true);

        foreach ($this->_threads as $thread) {

            if ($thread->getDownloaded() + $thread->getExists() < $thread->length) {
                $request = new Request(ArrayHelper::merge($client->requestConfig, [
                    'client' => $client,
                    'headers' => [
                        'range' => "bytes=$thread->start-$thread->end",
                        'Cache-Control' => 'no-cache'
                    ],
                    'options' => [
                        CURLOPT_FILE => $thread->getFileResource(),
                    ]
                ]));

                if ($client->hasEventHandlers(Client::EVENT_PROGRESS)) {

                    $request->addOptions([
                        CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadSize, $downloaded)
                        use (&$threads, $client) {
                            if ($downloadSize > 0) {
                                $event = new ProgressEvent($this, $resource, $downloaded);

                                $this->_previousDownloadedTime = microtime(true);
                                $this->_previousDownloadedSize = $event->done;

                                $client->trigger(Client::EVENT_PROGRESS, $event);
                            }
                        },
                        CURLOPT_NOPROGRESS => false
                    ]);
                }
                $thread->request = $request;
                $requests[] = $request;
            }

            $request->beforeSend();
        }

        $this->batchSendThreads($requests);

        return $client->createResponse($this->_threads);

    }

    /**
     * @return Thread[]
     */
    public function getThreads()
    {
        return $this->_threads;
    }

    /**
     * @return int
     */
    public function getPreviousDownloadedSize()
    {
        return $this->_previousDownloadedSize;
    }

    /**
     * @return float
     */
    public function getPreviousDownloadedTime()
    {
        return $this->_previousDownloadedTime;
    }

    /**
     * @return float
     */
    public function getStartTime()
    {
        return $this->_startTime;
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
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function batchSendThreads(array $requests)
    {
        $curlBatchResource = curl_multi_init();

        $token = '';
        $curlResources = [];
        $responseHeaders = [];
        foreach ($requests as $key => $request) {

            $this->getClient($request);

            $curlOptions = $this->prepare($request);
            $curlResource = $this->initCurl($curlOptions);

            $this->_threads[$key]->setCurlResource($curlResource);

            $token .= $request->client->createRequestLogToken($request->getMethod(), $curlOptions[CURLOPT_URL],
                    $curlOptions[CURLOPT_HTTPHEADER], $this->_threads[$key]->getHash()) . "\n\n";

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
            curl_multi_remove_handle($curlBatchResource, $curlResource);
        }

        curl_multi_close($curlBatchResource);

        foreach ($requests as $key => $request) {
            $response = $request->client->createResponse($responseContents[$key], $responseHeaders[$key]);
            $request->afterSend($response);
            $this->_threads[$key]->response = $response;
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
