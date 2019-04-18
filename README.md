Yii2 Large file downloader
==================================

It is yii2 extension based on [yii2-httpclient](https://github.com/yiisoft/yii2-httpclient)

Download file in multiple streams. With the ability to resume and monitor progress.


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist somov/yii2-large-file-downloader "~1.0"
```

or add

```
"somov/yii2-large-file-downloader": "~1.0"
```

to the require section of your `composer.json` file.


Usage
-----
```php

        /** @var \somov\lfd\Client $client */
        $client = Yii::createObject([
            'class' => \somov\lfd\Client::class,
            //'threadCount' => 5,
            //'resumeDownload' =>  true,
            //'tmpAlias' => '@runtime/downloads' 
        ]);

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event) use (&$p) {
            /** @var \somov\lfd\ProgressEvent $p */
            $p = $event->percent;
        });

        //Download file   
        $fileName = $client->get("https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4")
            ->send()
            ->data;

        $size = filesize($fileName); 
        
        echo $size;
