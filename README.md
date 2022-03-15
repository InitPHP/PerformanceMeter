# PerformanceMeter

A helpful library that allows you to measure and compare the runtime of the code you write with PHP.

[![Latest Stable Version](http://poser.pugx.org/initphp/performancemeter/v)](https://packagist.org/packages/initphp/performancemeter) [![Total Downloads](http://poser.pugx.org/initphp/performancemeter/downloads)](https://packagist.org/packages/initphp/performancemeter) [![Latest Unstable Version](http://poser.pugx.org/initphp/performancemeter/v/unstable)](https://packagist.org/packages/initphp/performancemeter) [![License](http://poser.pugx.org/initphp/performancemeter/license)](https://packagist.org/packages/initphp/performancemeter) [![PHP Version Require](http://poser.pugx.org/initphp/performancemeter/require/php)](https://packagist.org/packages/initphp/performancemeter)

## Installation

```
composer require initphp/performancemeter
```

This is a library consisting of a single file and a single class. You can choose to manually include the `src/PerformanceMeter.php` file in the project. However, I recommend Composer to manage patches and updates more easily.

## Usage

```php
require_once "../vendor/autoload.php";

use InitPHP\PerformanceMeter\PerformanceMeter;

PerformanceMeter::setPointer('main');
for($i = 0; $i <= 1000; $i++){
    usleep(10);
}
PerformanceMeter::setPointer('mainEnd');

echo PerformanceMeter::elapsedTime('main', 'mainEnd', 3) . ' seconds passed ';
echo PerformanceMeter::memoryUsage('main', 'mainend', 2) . ' memory used.';
// Output : "15.204 seconds passed 0.77KB memory used."
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
