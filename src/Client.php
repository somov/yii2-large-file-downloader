<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 15.04.19
 * Time: 18:49
 */

namespace somov\lfd;


use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;
use yii\httpclient\Client as BaseClient;
use yii\httpclient\CurlTransport;

/**
 * Class Client
 * @property int threadCount
 * @package somov\lfd
 */
class Client extends BaseClient
{
    const EVENT_PROGRESS = 'progress';
    const EVENT_BEFORE_DOWNLOAD = 'beforeDownload';
    const EVENT_AFTER_DOWNLOAD = 'afterDownload';


    /** @var  \yii\httpclient\Response[] */
    private $_preResponses;

    /**
     * @var bool
     */
    public $resumeDownload = true;

    /**
     * @var string
     */
    public $tmpAlias = '@runtime/downloads';


    /**
     * @var bool
     */
    public $followLocation = true;

    /**
     * @var int
     */
    public $redirectCount = 5;


    public $responseConfig = [
        'class' => Response::class
    ];

    protected $transportConfig = [
        'class' => Transport::class,
        'threadCount' => 2
    ];

    /**
     * @var string
     */
    private $_downloadedUrl;

    /**
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function download($url, $headers = [], $options = [])
    {

        if ($this->getRemoteSize($url) === 0) {
            $response = $this->getPreResponse($url);
            $exception = new Exception('No content found with code ' . $response->getStatusCode(),
                $response->getStatusCode());
            $exception->response = $response;
            throw $exception;
        }

        if (!$this->isAcceptRanges($url)) {
            $this->threadCount = 1;
        }

        $this->_downloadedUrl = $url;

        $this->trigger(self::EVENT_BEFORE_DOWNLOAD, new DownloadEvent([
            'preResponse' => $this->getPreResponse($url)]));

        $this->setTransport($this->transportConfig);

        $request = $this->createRequest();
        $this->beforeSend($request);
        /** @var Response|\yii\httpclient\Response $response */
        $response = $request
            ->setMethod('GET')
            ->setUrl(ArrayHelper::getValue($this->requestConfig, 'url', $url))
            ->addHeaders($headers)
            ->addOptions($options)
            ->send();

        $this->afterSend($request, $response);

        $this->trigger(self::EVENT_AFTER_DOWNLOAD, new DownloadEvent([
            'preResponse' => $this->getPreResponse($url)]));

        return $response;

    }


    /**
     * @param string $url
     * @return int
     */
    public function getRemoteSize($url)
    {

        $preResponse = $this->getPreResponse($url);

        if (!StringHelper::startsWith($preResponse->getStatusCode(), '2')) {
            return 0;
        }

        return (integer)$preResponse->headers->get('content-length', 0);

    }


    /**
     * @param $url
     * @return bool
     */
    public function isAcceptRanges($url)
    {
        $preResponse = $this->getPreResponse($url);

        return $preResponse->headers->get('accept-ranges', '') === 'bytes';
    }

    /**
     * @return int
     */
    public function getThreadCount()
    {
        return $this->transportConfig['threadCount'];
    }

    /**
     * @param $value
     * @return string int
     */
    public function setThreadCount($value)
    {
        return $this->transportConfig['threadCount'] = $value;
    }


    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->initDownloader();
        parent::init();

    }

    /**
     * @throws \yii\base\Exception
     */
    protected function initDownloader()
    {
        FileHelper::createDirectory(\Yii::getAlias($this->tmpAlias));
    }


    /**
     * @param string $url
     * @return string string
     */
    public function getUrlHash($url)
    {
        return sha1($url);
    }

    /**
     * @param $url
     * @param int $count
     * @return \yii\httpclient\Response
     */
    protected function getPreResponse($url, $count = 0)
    {

        $count++;

        if ($count > $this->redirectCount) {
            throw new Exception('Too many redirects: ' . $this->redirectCount);
        }

        $hash = $this->getUrlHash($url);

        if (isset($this->_preResponses[$hash])) {
            $preResponse = $this->_preResponses[$hash];
        } else {
            $this->requestConfig = ArrayHelper::merge($this->requestConfig, ['url' => $url]);

            $client = \Yii::createObject([
                'class' => BaseClient::class,
                'transport' => CurlTransport::class,
                'requestConfig' => $this->requestConfig
            ]);

            $preResponse = $this->_preResponses[$hash] = $client->head($url)->send();

            $this->responseConfig['baseFileName'] = $this->getBaseFileName($preResponse, $url);
        }

        if ($this->followLocation && in_array($preResponse->getStatusCode(), ['301', '302'])) {
            return $this->getPreResponse($preResponse->headers->get('location'), $count);
        }

        return $this->_preResponses[$hash];

    }

    /**
     * @param \yii\httpclient\Response $response
     * @param $url
     * @return string
     */
    protected function getBaseFileName($response, $url)
    {
        $fileName = '';
        if ($cd = $response->headers->get('content-disposition', false)) {
            if (preg_match('/filename=\"(.*?)\"/', $cd, $m)) {
                $fileName = $m[1];
            }
        }

        if (empty($fileName)) {
            $parts = parse_url($url);
            $fileName = basename($parts['path']);

            $parts = pathinfo($fileName);

            if (empty($parts['extension'])) {
                $extensions = FileHelper::getExtensionsByMimeType($response->headers->get('content-type'));
                if (count($extensions) > 0) {
                    $fileName .= '.' . $extensions[0];
                }
            }
        }


        return $fileName;
    }

    /**
     * @return string
     */
    public function getDownloadedUrl()
    {
        return $this->_downloadedUrl;
    }


}