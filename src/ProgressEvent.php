<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 17.04.19
 * Time: 16:20
 */

namespace somov\lfd;


use yii\base\Event;

/**
 * Class ProgressEvent
 * @package somov\lfd
 *
 * @property-read $percent
 * @property-read $done
 * @property-read $total
 * @property-read $threads
 */
class ProgressEvent extends Event
{
    /**
     * @var integer
     */
    private $_percent = 0;

    /**
     * @var integer
     */
    private $_done = 0;

    /**
     * @var  int
     */
    private $_downloaded = 0;

    /**
     * @var integer
     */
    private $_total = 0;

    /**
     * @var Thread[]
     */
    private $_threads;

    /**
     * @var float
     */
    private $_averageSpeed;

    /**
     * @var float
     */
    private $_currentSpeed;

    /**
     * @var float
     */
    private $_timeRemaining;


    /**
     * ProgressEvent constructor.
     * @param Transport $transport
     * @param resource $resource
     * @param integer $downloaded
     * @param array $config
     */
    public function __construct(Transport $transport, $resource, $downloaded, array $config = [])
    {
        $this->_threads = $transport->threads;

        $this->calcProgress($resource, $downloaded);

        $this->calcSpeed($transport);

        parent::__construct($config);
    }


    /**
     * @param resource $resource
     * @param integer $downloaded
     */
    protected function calcProgress($resource, $downloaded)
    {

        foreach ($this->_threads as $thread) {
            if ($thread->getCurlResource() === $resource) {
                $thread->setDownloaded($downloaded);
            }
            $this->_downloaded += $thread->getDownloaded();
            $this->_done += $thread->getDownloaded() + $thread->getExists();
            $this->_total += $thread->length;
        }

        $this->_percent = (integer)round(($this->_done * 100 / $this->_total));

    }

    protected function calcSpeed(Transport $transport)
    {
        $this->_averageSpeed = $this->_downloaded / (microtime(true) - $transport->getStartTime());

        $this->_currentSpeed = ($this->done - $transport->getPreviousDownloadedSize()) / (microtime(true)
                - $transport->getPreviousDownloadedTime());

        if ($this->_averageSpeed > 0) {
            $this->_timeRemaining = ($this->done - $this->total) / $this->_averageSpeed;
        }

    }


    /**
     * @return int
     */
    public function getPercent()
    {
        return $this->_percent;
    }

    /**
     * @return int
     */
    public function getDone()
    {
        return $this->_done;
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->_total;
    }

    /**
     * @return Thread[]
     */
    public function getThreads()
    {
        return $this->_threads;
    }

    /**
     * @return float
     */
    public function getAverageSpeed()
    {
        return $this->_averageSpeed;
    }

    /**
     * @return float
     */
    public function getCurrentSpeed()
    {
        return $this->_currentSpeed;
    }

    /**
     * @return float
     */
    public function getTimeRemaining()
    {
        return $this->_timeRemaining;
    }



}