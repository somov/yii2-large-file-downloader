<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.03.20
 * Time: 8:18
 */

namespace somov\lfd;

use yii\base\Behavior;
use yii\helpers\ArrayHelper;
use yii\i18n\Formatter;


/**
 * Class ClientProgressBehavior
 * @package sampleData\classes
 */
class ClientProgressBehavior extends Behavior implements ClientProgressBehaviorInterface
{
    /**
     * @var Client
     */
    public $owner;


    /**
     * @var string
     */
    public $text = 'Downloading {d}/{t}, with speed {s}, tr {l}';

    /**
     * @var string|Formatter
     */
    public $formatter = 'formatter';

    /**
     * @var string
     */
    public $tempFile = 'progress.tmp';

    /**
     * @var integer
     */
    private $progress = -1;

    /**
     * @var int
     */
    public $sizeDecimals = 2;


    /**
     * @return array
     */
    public function events()
    {
        return [
            Client::EVENT_PROGRESS => '_writeProgress',
            Client::EVENT_BEFORE_DOWNLOAD => '_beforeDownload',
            Client::EVENT_AFTER_DOWNLOAD => '_afterDownload'
        ];
    }

    /**
     *
     */
    public function _beforeDownload()
    {
        $file = $this->getFile();
        if (!file_exists($file)) {
            touch($file);
        }
    }

    public function _afterDownload()
    {
        $file = $this->getFile();

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * @param ProgressEvent $event
     */
    public function _writeProgress($event)
    {

        if ($this->progress === $event->getPercent()) {
            return;
        }

        $this->progress = $event->getPercent();

        $key = $this->owner->getUrlHash($this->owner->getDownloadedUrl());

        $data[$key] = [
            'progress' => $this->progress,
            'url' => $this->owner->getDownloadedUrl(),
            'done' => $event->getDone(),
            'total' => $event->getTotal(),
            'speed' => $event->getAverageSpeed(),
            'remaining' => $event->getTimeRemaining()
        ];

        if (!empty($this->text)) {
            $formatter = $this->getFormatter();
            $data[$key]['text'] = strtr($this->text, [
                '{d}' => $formatter->asShortSize($event->getDone(), $this->sizeDecimals),
                '{t}' => $formatter->asShortSize($event->getTotal(), $this->sizeDecimals),
                '{s}' => $formatter->asShortSize($event->getAverageSpeed(), $this->sizeDecimals),
                '{l}' => $formatter->asDuration($event->getTimeRemaining())
            ]);
        }

        $data = serialize(array_merge([$key => $this->readProgress($event->sender->getDownloadedUrl())], $data));

        $file = $this->getFile();

        $fO = new \SplFileObject($file, "w");
        try {
            if ($fO->flock(LOCK_EX)) {
                $fO->ftruncate(0);
                $fO->fwrite($data);
                $fO->flock(LOCK_UN);
            }
        } finally {
            unset($fO);
        }
    }


    /**
     * @return Formatter
     * @throws \yii\base\InvalidConfigException
     */
    protected function getFormatter()
    {
        if ($this->formatter instanceof Formatter) {
            return $this->formatter;
        }

        if ($this->formatter = \Yii::$app->get($this->formatter, false)) {
            return $this->formatter;
        }
        $this->formatter = \Yii::createObject($this->formatter);

        return $this->formatter;
    }

    /**
     * @return string
     */
    protected function getFile()
    {
        return \Yii::getAlias($this->owner->tmpAlias) . DIRECTORY_SEPARATOR . $this->tempFile;
    }

    /**
     * @param string $url
     * @return array
     */
    public function readProgress($url = null)
    {

        $file = $this->getFile();

        $default = [
            'text' => '',
            'progress' => 0,
            'url' => ''
        ];

        if (!file_exists($file)) {
            return $default;
        }

        try {
            $file = new \SplFileObject($file, "r+");
            $length = $file->getSize();

            if ($data = $file->fread($length)) {
                $data = unserialize($data);
                if (isset($url)) {
                    return ArrayHelper::getValue($data, $this->owner->getUrlHash($url), $default);
                }
                return reset($data);
            }
        } catch (\Exception $exception) {
            return $default;
        }

        return $default;
    }
}