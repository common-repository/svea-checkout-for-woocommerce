<?php
/**
 * Configuration for development
 *
 * @category Class
 * @package  Svea\Instore
 * @author   Alexander Widén
 */
namespace Svea\Instore;

/**
 * Configuration Class for development
 *
 * @category Class
 * @package  Svea\Instore
 * @author   Alexander Widén
 */
class DevConfiguration extends Configuration
{
    private static $defaultConfiguration;

    /**
     * The host
     *
     * @var string
     */
    protected $host = 'https://webpayinstoreapistage.svea.com';

    /**
     * User agent of the HTTP request, set to "PHP-Swagger" by default
     *
     * @var string
     */
    protected $userAgent = 'PHP-Instore-Dev/1.0.0';

    /**
     * Gets the default configuration instance
     *
     * @return Configuration
     */
    public static function getDefaultConfiguration()
    {
        if (self::$defaultConfiguration === null) {
            self::$defaultConfiguration = new DevConfiguration();
        }

        return self::$defaultConfiguration;
    }

}
