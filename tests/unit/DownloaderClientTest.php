<?php


/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.04.19
 * Time: 18:50
 */
class DownloaderClientTest extends \Codeception\TestCase\Test
{

    /**
     * @return \somov\lfd\Client
     * @throws \yii\base\InvalidConfigException
     */
    protected function getDownloaderClient()
    {
        return Yii::createObject([
            'class' => \somov\lfd\Client::class,
            'threadCount' => 10
        ]);
    }


    private function initLog()
    {
        Yii::setLogger(Yii::createObject(\yii\log\Logger::class));
        Yii::$app->log->setLogger(Yii::getLogger());
        // log something
        Yii::getLogger()->flush();
    }

    public function getUrlsProviderDownload()
    {
        return [
            ['http://techslides.com/demos/sample-videos/small.mp4', 5],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4', 15],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_5mb.mp4', 20]
        ];
    }

    /**
     * @dataProvider getUrlsProviderDownload
     * @param $url
     * @param $theadCount
     */
    public function testDownload($url, $theadCount)
    {
        $this->initLog();

        $client = $this->getDownloaderClient();
        $client->threadCount = $theadCount;

        $size = $client->getRemoteSize($url);

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event) use (&$p) {
            $p = $event->percent;
        });

        $this->assertSame($size, filesize($client->download($url)->data));
        $this->assertGreaterThan(98, $p);

    }


    public function getUrlsProviderResume()
    {
        return [
            ['http://techslides.com/demos/sample-videos/small.mp4', 5, \yii\base\Exception::class],
            ['http://techslides.com/demos/sample-videos/small.mp4', 5, null],
        ];
    }

    /**
     * @dataProvider getUrlsProviderResume
     * @param $url
     * @param $theadCount
     * @param $exception
     */
    public function testDownloadResume($url, $theadCount, $exception)
    {
        $this->initLog();


        if (isset($exception)) {
            $this->expectException($exception);
        }

        $client = $this->getDownloaderClient();
        $client->threadCount = $theadCount;

        $size = $client->getRemoteSize($url);

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event) use (&$p, $exception) {
            $p = $event->percent;

            if (isset($exception) && $p > 50) {
                throw new $exception;
            }

        });

        $this->assertSame($size, filesize($client->download($url)->data));


        $this->assertGreaterThan(98, $p);

    }


}