<?php

namespace App\Libraries\Monitoring\Strategies\DataStore;

use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use App\Libraries\Monitoring\Strategies\RedisChannel\CoastersStrategy;
use App\Libraries\Monitoring\Strategies\DataStore\DataStoreStrategy;
use App\Libraries\Monitoring\RedisChannel;
use Exception;

class RedisStrategy implements DataStoreStrategy
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

    private string $channelFullName;

    private string $environment;

    private $redis;

    /**
     * Constructor
     *
     * @param string $environment The environment (e.g., production, development).
     * @param string $channel The channel to subscribe to.
     */
    public function __construct(string $environment = self::ENV_PRODUCTION, string $channel = self::CHANNEL_COASTERS)
    {
        $this->environment = $environment;
        $this->setChannelFullName($environment, $channel);
        $this->redis = \Config\Services::redis();
    }

    /**
     * Displays monitoring data
     */
    public function do(): void
    {
        $this->subscribe();
        $this->listen();
        $this->unsubscribe();
    }

    /**
     * Subscribes to the specified channel.
     */
    private function subscribe(): void
    {
        $this->redis->subscribe($this->channelFullName)->then(function () {
            echo 'Now subscribed to channel ' . $this->channelFullName . PHP_EOL;
        }, function (Exception $e) {
            $this->redis->close();
            echo 'Unable to subscribe: ' . $e->getMessage() . PHP_EOL;
        });
    }

    private function listen(): void
    {
        $this->redis->on('message', function (string $channel, string $message) {
            $redisChannel = new RedisChannel();

            if (0 === strpos($channel, self::CHANNEL_COASTERS)) {
                $redisChannel->setStrategy(new CoastersStrategy($message, $this->environment));
            }

            $redisChannel->run();
        });
    }

    /**
     * Automatically re-subscribe to channel on connection issues
     */
    private function unsubscribe(): void
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

    private function setChannelFullName(string $environment, string $channel): void
    {
        // Validate environment
        if (!in_array($environment, ['production', 'development', 'testing'])) {
            throw new Exception('Invalid environment specified');
        }

        // Set the channel with environment prefix
        $this->channelFullName = $channel . "_" . $environment;
    }
}
