<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.04.19
 * Time: 18:49
 */

namespace somov\lfd;


use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\httpclient\Client as BaseClient;
use yii\httpclient\CurlTransport;
use yii\httpclient\Request;

/**
 * Class Client
 * @property int threadCount
 * @package somov\lfd
 */
class Client extends BaseClient
{
    const EVENT_PROGRESS = 'progress';


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
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return \yii\httpclient\Response
     */
    public function download($url, $headers = [], $options = [])
    {

        if ($this->getRemoteSize($url) === 0) {
            new \RuntimeException('Not content found at url ' . $url);
        }

        if (!$this->isAcceptRanges($url)) {
            $this->threadCount = 1;
        }

        return $request = $this->createRequest()
            ->setMethod('GET')
            ->setUrl(ArrayHelper::getValue($this->requestConfig, 'url', $url))
            ->addHeaders($headers)
            ->addOptions($options)
            ->send();

    }


    /**
     * @param string $url
     * @return int
     */
    public function getRemoteSize($url)
    {

        $preResponse = $this->getPreResponse($url);

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
        $this->setTransport($this->transportConfig);

        FileHelper::createDirectory(\Yii::getAlias($this->tmpAlias));
    }


    /**
     * @param Request $request
     * @return \yii\httpclient\Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function preRequest($request)
    {
        $client = \Yii::createObject([
            'class' => BaseClient::class,
            'transport' => CurlTransport::class,
            'requestConfig' => $this->requestConfig
        ]);

        $this->responseConfig['baseFileName'] = basename($request->url);

        return $client->head($request->url)->send();

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
            $preResponse = $this->_preResponses[$hash] = $this->preRequest($this->createRequest());
        }

        if ($this->followLocation && in_array($preResponse->getStatusCode(), ['301', '302'])) {
            return $this->getPreResponse($preResponse->headers->get('location'), $count);
        }

        return $this->_preResponses[$hash];

    }


}