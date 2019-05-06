<?php
/**
 * Created by PhpStorm.
 * User: Nikolay Somov somov.nn@gmal.com
 * Date: 06.05.19
 * Time: 23:32
 */

namespace somov\lfd;


use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

class CommandController extends Controller
{
    /**
     * @var string
     */
    public $defaultAction = 'download';

    /**
     * @var int
     */
    public $threadCount = 5;

    /**
     * @var boolean
     */
    public $resumeDownload = true;


    /**
     * @var string
     */
    public $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36';


    /**
     * Download file
     * @param string $url
     * @param string|null $file
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDownload($url, $file = null)
    {
        $client = \Yii::createObject([
            'class' => Client::class,
            'threadCount' => $this->threadCount,
            'resumeDownload' => $this->resumeDownload,
            'requestConfig' => [
                'headers' => [
                    'User-Agent' => $this->userAgent
                ]
            ],
            'on ' . Client::EVENT_PROGRESS => function ($event) {
                /**@var ProgressEvent $event */

                $f = \Yii::$app->formatter;

                $text = strtr('{      d}/{      t} Speed: {      s} Remaining time {                    r}', [
                    '{      d}' => $f->asShortSize(round($event->done), 0),
                    '{      t}' => $f->asShortSize(round($event->total), 0),
                    '{      s}' => $f->asShortSize($event->getAverageSpeed(), 0),
                    '{                    r}' => $f->asDuration($event->getTimeRemaining(), ':', '')
                ]);

                Console::startProgress($event->done, $event->total, $text);
            }
        ]);

        $file = \Yii::getAlias($file);

        try {
            $response = $client->download($url);
        } catch (\Exception $exception) {
            $this->stderr('Unexpected error ' . $exception->getMessage() . PHP_EOL,  Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (empty($file)) {
            $file = Console::prompt('Enter file name', ['default' => $response->getBaseFileName()]);
        }

        if (file_exists($file)) {
            if ($this->confirm('File exists, replace?')) {
                unlink($file);
            } else {
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        rename($response->data, $file);

        return ExitCode::OK;
    }

    /**
     * @param string $actionID
     * @return array|string[]
     */
    public function options($actionID)
    {
        return ArrayHelper::merge(parent::options($actionID), [
            'threadCount',
            'resumeDownload',
            'userAgent'
        ]);
    }

    /**
     * @return array
     */
    public function optionAliases()
    {
        return ArrayHelper::merge(parent::optionAliases(), [
            'c' => 'threadCount',
            'r' => 'resumeDownload',
            'u' => 'userAgent'
        ]);
    }


}