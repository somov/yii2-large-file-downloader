<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.03.20
 * Time: 9:32
 */

namespace somov\lfd;


use yii\base\Event;

/**
 * Class DownloadEvent
 * @package somov\lfd
 */
class DownloadEvent extends Event
{
    /** 
     * @var Client 
     * 
     */
    public $sender;

    /**
     * @var \yii\httpclient\Response
     */
    public $preResponse;
}