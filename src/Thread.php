<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 15.04.19
 * Time: 20:47
 */

namespace somov\lfd;


use yii\base\ArrayableTrait;
use yii\base\BaseObject;
use yii\httpclient\Request;

/**
 * @property-read  integer $start
 * @property-read  integer $end
 * @property-read  integer $length
 * @property-read integer $index
 */
class Thread extends BaseObject
{
    use ArrayableTrait;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var Response
     */
    public $response;

    /**
     * @var integer
     */
    private $_start;

    /**
     * @var integer
     */
    private $_end;

    /**
     * @var integer
     */
    private $_length;

    /**
     * @var integer
     */
    private $_index;

    /**
     * @var string
     */
    private $_hash;

    /**
     * @var integer
     */
    private $_downloaded = 0;

    /**
     * @var int
     */
    private $_exists = 0;

    /**
     * @var  Client
     */
    private $_client;


    /**
     * @var resource
     */
    private $_fileResource;

    /**
     * @var resource
     */
    private $_curlResource;


    /**
     * @return array
     */
    public function fields()
    {
        return ['start', 'end', 'length', 'index'];
    }

    /**
     *
     */
    public function __destruct()
    {
        if (is_resource($this->_fileResource)) {
            fclose($this->_fileResource);
        }
    }

    /**
     * @param Client $client
     * @param string $url
     * @param integer $threadCount
     * @return self[]
     */
    public static function calculate(Client $client, $url, $threadCount)
    {
        /** @var self[] $threads */
        $threads = [];

        $index = -1;

        $remoteSize = $client->getRemoteSize($url);

        if ($remoteSize == 0)
            return [];

        $multiSize = floor($remoteSize / $threadCount);

        $s = 0;
        while ($s <= $remoteSize) {
            $thread = new self();
            $thread->_client = $client;
            $thread->_start = $s;
            $thread->_index = ++$index;
            $s += $multiSize;
            if ($s >= $remoteSize) {
                $thread->_end = $remoteSize;
            } else {
                $thread->_end = $s;
            }
            $thread->_length = $thread->_end - $thread->_start;
            $thread->_hash = sha1(implode($url, $thread->toArray()));
            $s++;

            $file = $thread->getPartFileName();

            if (file_exists($file) && $client->isAcceptRanges($url)) {
                if ($client->resumeDownload) {
                    $thread->_exists = filesize($file);
                    if ($thread->_exists < $thread->_length) {
                        $thread->_start += $thread->_exists;
                    }
                } else {
                    unlink($file);
                }
            }

            $threads[] = $thread;
        }

        return $threads;
    }

    /**
     * @return mixed
     */
    public function getStart()
    {
        return $this->_start;
    }

    /**
     * @return mixed
     */
    public function getEnd()
    {
        return $this->_end;
    }

    /**
     * @return mixed
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * @return string
     */
    public function getPartFileName()
    {
        return \Yii::getAlias($this->_client->tmpAlias . '/' . $this->_hash);
    }

    /**
     * @return resource
     */
    public function getFileResource()
    {
        if (is_resource($this->_fileResource)) {
            return $this->_fileResource;
        }
        $this->_fileResource = fopen($this->getPartFileName(), 'a+');

        return $this->_fileResource;
    }

    /**
     * @return resource
     */
    public function getCurlResource()
    {
        return $this->_curlResource;
    }

    /**
     * @param resource $resource
     */
    public function setCurlResource($resource)
    {
        $this->_curlResource = $resource;
    }


    /**
     * @return string
     */
    public function getHash()
    {
        return $this->_hash;
    }


    /**
     * @param $value
     */
    public function setDownloaded($value)
    {
        $this->_downloaded = $value;
    }

    /**
     * @return int
     */
    public function getDownloaded()
    {
        return $this->_downloaded;
    }

    /**
     * @return int
     */
    public function getExists()
    {
        return $this->_exists;
    }

    /**
     * @return int
     */
    public function getPercent()
    {
        return (integer) round(($this->_downloaded + $this->_exists) *  100 / $this->getLength(), 0);
    }


}