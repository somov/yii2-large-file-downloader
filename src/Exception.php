<?php
/**
 * Created by PhpStorm.
 * User: web
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
    public function getName()
    {
        return 'Downloader exception';
    }
}