<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 17.04.19
 * Time: 12:12
 */

namespace somov\lfd;


use yii\httpclient\Message;

class Response extends Message
{
    /**
     * @var Client
     */
    public $client;

    /**
     * @var string
     */
    protected $baseFileName;

    public $readLength = 100000;

    /**
     * @return string
     */
    protected function getFileName()
    {
        return \Yii::getAlias($this->client->tmpAlias . '/' . $this->baseFileName);
    }

    /**
     * @param string $name
     */
    public function setBaseFileName($name)
    {
        $this->baseFileName = $name;
    }

    /**
     * @return Block[]|mixed $blocks
     */
    public function getContent()
    {
        return parent::getContent();
    }

    /** Downloaded file name
     * @return mixed|string
     */
    public function getData()
    {
        $filename = $this->getFileName();

        $handle = fopen($filename, 'w');

        $blocks = $this->getContent();
        try {
            foreach ($blocks as $block) {
                fclose($block->getFileResource());
                $fr = fopen($block->getPartFileName(), 'r');
                while (!feof($fr)) {
                    fwrite($handle, fread($fr, $this->readLength));
                }
                fclose($fr);
                unlink($block->getPartFileName());
            }
        } finally {
            fclose($handle);
        }

        return $filename;
    }
}