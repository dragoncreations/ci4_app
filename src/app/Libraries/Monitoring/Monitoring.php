<?php

namespace App\Libraries\Monitoring;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use App\Libraries\Monitoring\Strategies\CoastersChannelStrategy;
use Exception;

class Monitoring
{
    /**
     * Environment constants
     */
    public const string ENV_PRODUCTION = 'production';

    public const string ENV_DEVELOPMENT = 'development';

    /**
     * Channel constants
     */
    public const string CHANNEL_COASTERS = 'coasters';

    private $redis;

    private string $channel;

    private Statistics $statistics;

    /**
     * Constructor to initialize the Redis client and set the channel.
     *
     * @param string $environment The environment (e.g., production, development).
     * @param string $channel The channel to subscribe to.
     */
    public function __construct(string $environment = self::ENV_PRODUCTION, string $channel = self::CHANNEL_COASTERS)
    {
        $this->redis = \Config\Services::redis();
        $this->setChannel($environment, $channel);
        $this->statistics = new Statistics();
    }

    /**
     * Subscribes to the specified channel.
     */
    public function subscribe(): void
    {
        $this->redis->subscribe($this->channel)->then(function () {
            echo 'Now subscribed to channel ' . $this->channel . PHP_EOL;
        }, function (Exception $e) {
            $this->redis->close();
            echo 'Unable to subscribe: ' . $e->getMessage() . PHP_EOL;
        });
    }

    /**
     * Displays monitoring data
     */
    public function display(): void
    {
        $this->redis->on('message', function (string $channel, string $message) {
            if (0 === strpos($channel, self::CHANNEL_COASTERS)) {
                $this->statistics->setStrategy(new CoastersChannelStrategy($message));
            }

            $this->statistics->display();
        });
    }

    /**
     * Automatically re-subscribe to channel on connection issues
     */
    public function unsubscribe(): void
    {
        $this->redis->on('unsubscribe', function (string $channel) {
            echo 'Unsubscribed from ' . $channel . PHP_EOL;

            Loop::addPeriodicTimer(2.0, function (TimerInterface $timer) use ($channel) {
                $this->redis->subscribe($channel)->then(function () use ($timer) {
                    echo 'Now subscribed again' . PHP_EOL;
                    Loop::cancelTimer($timer);
                }, function (Exception $e) {
                    echo 'Unable to subscribe again: ' . $e->getMessage() . PHP_EOL;
                });
            });
        });
    }

    private function setChannel(string $environment, string $channel): void
    {
        // Validate environment
        if (!in_array($environment, ['production', 'development', 'testing'])) {
            throw new Exception('Invalid environment specified');
        }

        // Set the channel with environment prefix
        $this->channel = $channel . "_" . $environment;
    }
}
