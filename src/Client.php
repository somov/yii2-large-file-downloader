<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.04.19
 * Time: 18:49
 */

namespace somov\lfd;


use yii\helpers\FileHelper;
use yii\httpclient\Client as BaseClient;
use yii\httpclient\CurlTransport;
use yii\httpclient\Request;

/**
 * Class Client
 * @package somov\lfd
 */
class Client extends BaseClient
{
    const EVENT_PROGRESS = 'progress';


    /** @var  \yii\httpclient\Response */
    private $_preResponse;

    /**
     * @var int
     */
    public $threadCount = 2;

    /**
     * @var bool
     */
    public $resumeDownload = true;

    /**
     * @var string
     */
    public $tmpAlias = '@runtime/downloads';


    public $responseConfig = [
        'class' => Response::class
    ];

    /**
     * @var  string
     */
    private $_url;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->initDownloader();

    }

    /**
     * @throws \yii\base\Exception
     */
    protected function initDownloader()
    {
        $this->setTransport([
            'class' => Transport::class
        ]);

        FileHelper::createDirectory(\Yii::getAlias($this->tmpAlias));
    }


    /**
     * @param Request|array $request
     * @return Client
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function preRequest($request)
    {

        if (is_array($request)) {
            $this->requestConfig = $request;
            $request = $this->createRequest();
        } elseif (isset($this->_preResponse) && $request->url === $this->_url) {
            return $this;
        }

        $client = \Yii::createObject([
            'class' => BaseClient::class,
            'transport' => CurlTransport::class,
        ]);

        $url = $request->getFullUrl();

        $this->responseConfig['baseFileName'] = basename($url);

        $this->_preResponse = $client->head($url)->send();

        return $this;

    }

    /**
     * @return int
     */
    public function getRemoteSize()
    {
        if (empty($this->_preResponse)) {
            return 0;
        }
        return (integer)$this->_preResponse->headers->get('content-length', 0);
    }

    /**
     * @return bool
     */
    public function isAcceptRanges()
    {
        if (empty($this->_preResponse)) {
            return false;
        }
        return $this->_preResponse->headers->get('accept-ranges', '') === 'bytes';
    }


    /**
     * @return Request
     * @throws \yii\base\InvalidConfigException
     */
    public function createRequest()
    {
        $request = parent::createRequest();
        $this->_url = $request->url;
        return $request;
    }

    /**
     * @return\yii\httpclient\Response|null
     */
    public function getPreResponse()
    {
        return $this->_preResponse;
    }

}