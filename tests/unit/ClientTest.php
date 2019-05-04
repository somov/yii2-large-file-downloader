<?php


/**
 * Created by PhpStorm.
 * User: web
 * Date: 15.04.19
 * Time: 18:50
 */
class ClientTest extends \Codeception\TestCase\Test
{
    /**
     * @var \somov\lfd\Client
     */
    private static $_c;

    /**
     * @return \somov\lfd\Client
     * @throws \yii\base\InvalidConfigException
     */
    protected function getDownloaderClient()
    {
        if (isset(self::$_c)) {
            return self::$_c;
        }
        self::$_c = Yii::createObject([
            'class' => \somov\lfd\Client::class,
            'threadCount' => 10,
            'requestConfig' => [
                'headers'=> [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36',
                ]
            ]
        ]);
        return self::$_c;
    }


    private function initLog()
    {
        Yii::setLogger(Yii::createObject(\yii\log\Logger::class));
        Yii::$app->log->setLogger(Yii::getLogger());
        Yii::getLogger()->flush();
    }

    public function getUrlsProviderDownload()
    {
        return [
            ['http://techslides.com/demos/sample-videos/small.mp4'],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4'],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_5mb.mp4']
        ];
    }

    /**
     * @dataProvider getUrlsProviderDownload
     * @param $url
     */
    public function testDownload($url)
    {
        $this->initLog();

        $client = $this->getDownloaderClient();

        $size = $client->getRemoteSize($url);

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event) use (&$p) {
            $p = $event->percent;
        });

        $this->assertSame($size, filesize($client->download($url)->data));

        $this->assertGreaterThan(98, $p);

    }


}