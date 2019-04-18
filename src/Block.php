<?php
/**
 * Created by PhpStorm.
 * User: web
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
class Block extends BaseObject
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
     * @return int
     */
    public function totalExists()
    {
        return $this->_exists +  $this->_downloaded;
    }

    /**
     * @param resource $resource
     * @param integer $downloaded
     * @param self[] $blocks
     * @return array
     */
    public static function progress($resource, $downloaded, array &$blocks)
    {
        $p = [
            'done' => 0,
            'total' => 0,
            'percent' => 0,
            'blocks' => $blocks
        ];

        foreach ($blocks as $block) {
            if ($block->_curlResource === $resource) {
                $block->_downloaded = $downloaded;
            }
            $p['done']  += $block->totalExists();
            $p['total']  += $block->length;
        }

        $p['percent'] = (integer) round( ($p['done'] * 100 / $p['total']));

        return $p;
    }

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
     * @return self[]
     */
    public static function calculate(Client $client, $url)
    {
        /** @var self[] $blocks */
        $blocks = [];

        $index = -1;

        $remoteSize = $client->getRemoteSize();

        if ($remoteSize == 0)
            return [];

        $multiSize = floor($remoteSize / $client->threadCount);

        $s = 0;
        while ($s <= $remoteSize) {
            $block = new self();
            $block->_client = $client;
            $block->_start = $s;
            $block->_index = ++$index;
            $s += $multiSize;
            if ($s >= $remoteSize) {
                $block->_end = $remoteSize;
            } else {
                $block->_end = $s;
            }
            $block->_length = $block->_end - $block->_start;
            $block->_hash = sha1(implode($url, $block->toArray()));
            $s++;

            if ($client->resumeDownload) {
                $file = $block->getPartFileName();
                if (file_exists($file)) {
                    $block->_exists = filesize($file);
                    if ($block->_exists < $block->_length) {
                        $block->_start += $block->_exists;
                    }
                }
            }

            $blocks[] = $block;
        }

        return $blocks;
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


}