<?php
/**
 * Created by PhpStorm.
 * User: web
 * Date: 17.04.19
 * Time: 16:20
 */

namespace somov\lfd;


use yii\base\Event;

/**
 * Class ProgressEvent
 * @package somov\lfd
 *
 */
class ProgressEvent extends Event
{
    /**
     * @var integer
     */
    public $percent;

    /**
     * @var integer
     */
    public $done;

    /**
     * @var integer
     */
    public $total;

    /**
     * @var Thread[]
     */
    public $threads;
}