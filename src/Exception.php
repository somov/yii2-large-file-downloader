<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 04.05.19
 * Time: 22:48
 */

namespace somov\lfd;


/**
 * Class Exception
 * @package somov\lfd
 */
class Exception extends \yii\base\Exception
{

    /**
     * @var \yii\httpclient\Response
     */
    public $response;

    public function getName()
    {
        return 'Downloader exception';
    }
}