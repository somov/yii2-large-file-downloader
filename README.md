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

        $client->on(\somov\lfd\Client::EVENT_PROGRESS, function ($event){
            /** @var \somov\lfd\ProgressEvent $event */
            echo  $event->percent;
        });

        //Download file   
        $fileName = $client->download("https://sample-videos.com/video123/mp4/720/big_buck_bunny_720p_1mb.mp4")->data;

        $size = filesize($fileName); 
        
        echo $size;
``` 

Usage with command controller 
```

Configure the command in your main application configuration:
```
```php
    'controllerMap' => [
            'downloader' => '\somov\lfd\CommandController'
        ],
```

Once the extension is installed and configured, simply use it on your command line

```
yii downloader  https://speed.hetzner.de/1GB.bin -c 20

```

![screen](https://i.ibb.co/PwpX6Vf/Screenshot-from-2019-05-07-01-56-57.png)
