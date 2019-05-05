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
            'threadCount' => 20,
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
            ['http://demo.borland.com/testsite/downloads/downloadfile.php?file=Data1MB.dat&cd=attachment+filename', 'c35cc7d8d91728a0cb052831bc4ef372'],
            ['https://tinyurl.com/yxukwuq7', '8e53463838adc859873bbb1a172e1ab1'],
            ['http://techslides.com/demos/sample-videos/small.mp4', 'a3ac7ddabb263c2d00b73e8177d15c8d'],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4', 'd55bddf8d62910879ed9f605522149a8'],
            ['https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_5mb.mp4', '7e245fc2483742414604ce7e67c13111']
        ];
    }

    /**
     * @dataProvider getUrlsProviderDownload
     * @param $url
     */
    public function testDownload($url, $hash)
    {
        $this->initLog();

        $client = $this->getDownloaderClient();

        $size = $client->getRemoteSize($url);

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event) use (&$p) {
            $p = $event->percent;
        });

        $file = $client->download($url)->data;

        $this->assertSame($hash, md5_file($file));

        $this->assertSame($size, filesize($file) );

        $this->assertGreaterThan(98, $p);

    }


}