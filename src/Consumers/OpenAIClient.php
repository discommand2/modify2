<?php

namespace RPurinton\modify2\Consumers;

use Bunny\{Channel, Message};
use React\EventLoop\LoopInterface;
use RPurinton\modify2\{Locales, Log, Error, MySQL};
use RPurinton\modify2\RabbitMQ\{Consumer, Sync};

class OpenAIClient
{
    private ?int $discord_id = null;
    private ?Log $log = null;
    private ?LoopInterface $loop = null;
    private ?Consumer $mq = null;
    private ?Sync $sync = null;
    private ?MySQL $sql = null;
    private ?string $openai_token = null;
    private $ai = null;
    private ?array $locales = null;

    public function __construct(private array $config)
    {
        $this->validateConfig($config);
        $this->log = $config['log'];
        $this->loop = $config['loop'];
        $this->mq = $config['mq'];
        $this->sync = $config['sync'];
        $this->sql = $config['sql'];
        $this->log->debug('OpenAIClient::construct');
    }

    private function validateConfig(array $config): bool
    {
        $requiredKeys = [
            'log' => 'RPurinton\modify2\Log',
            'loop' => 'React\EventLoop\LoopInterface',
            'mq' => 'RPurinton\modify2\RabbitMQ\Consumer',
            'sync' => 'RPurinton\modify2\RabbitMQ\Sync',
            'sql' => 'RPurinton\modify2\MySQL'
        ];
        foreach ($requiredKeys as $key => $class) {
            if (!array_key_exists($key, $config)) throw new Error('missing required key ' . $key);
            if (!is_a($config[$key], $class)) throw new Error('invalid type for ' . $key);
        }
        return true;
    }

    public function init(): bool
    {
        $this->log->debug('OpenAIClient::init');
        $this->locales = Locales::get();
        $this->discord_id = $this->getId();
        $this->openai_token = $this->getOpenAIToken();
        $sharing_queue = 'openai';
        $private_queue = $this->log->getName();
        $this->sync->queueDeclare($sharing_queue, false) or throw new Error('failed to declare private queue');
        $this->sync->queueDeclare($private_queue, true) or throw new Error('failed to declare private queue');
        $this->mq->consume($sharing_queue, $this->callback(...)) or throw new Error('failed to connect to sharing queue');
        $this->mq->consume($private_queue, $this->callback(...)) or throw new Error('failed to connect to private queue');
        return true;
    }

    public function callback(Message $message, Channel $channel): bool
    {
        $this->log->debug('callback', [$message->content]);
        $this->route(json_decode($message->content, true)) or throw new Error('failed to route message');
        $channel->ack($message);
        return true;
    }

    private function route(array $content): bool
    {
        $this->log->debug('route', [$content['t']]);
        if ($content['op'] === 11) return $this->heartbeat($content);
        switch ($content['t']) {
            case 'MESSAGE_CREATE':
                return $this->messageCreate($content['d']);
        }
        return true;
    }

    private function heartbeat(array $content): bool
    {
        $this->log->debug('heartbeat', [$content]);
        $this->sql->query('SELECT 1'); // keep MySQL connection alive
        $this->sync->publish('discord', $content) or throw new Error('failed to publish message to discord');
        return true;
    }

    private function getId(): string
    {
        $result = $this->sql->query('SELECT `discord_id` FROM `discord_tokens` LIMIT 1');
        if ($result === false) throw new Error('failed to get discord_id');
        if ($result->num_rows === 0) throw new Error('no discord_id found');
        $row = $result->fetch_assoc();
        $id = $row['discord_id'] ?? null;
        $this->validateId($id) or throw new Error('invalid discord_id');
        return $id;
    }

    private function validateId(int|string $id): bool
    {
        $this->log->debug('validateId', ['id' => $id, 'type' => gettype($id)]);
        if (!is_numeric($id)) throw new Error('id is not numeric');
        if ($id < 0) throw new Error('id is negative');
        return true;
    }

    private function getOpenAIToken(): string
    {
        $result = $this->sql->query('SELECT `api_key` FROM `openai_api_keys` LIMIT 1');
        if ($result === false) throw new Error('failed to get openai_api_key');
        if ($result->num_rows === 0) throw new Error('no openai_api_key found');
        $row = $result->fetch_assoc();
        $api_key = $row['api_key'] ?? null;
        $this->validateApiKey($api_key) or throw new Error('invalid openai_api_key');
        return $api_key;
    }

    private function validateApiKey(mixed $api_key): bool
    {
        $this->log->debug('validateId', ['id' => $api_key, 'type' => gettype($api_key)]);
        return preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key) === 1;
    }


    private function messageCreate(array $data): bool
    {
        $this->log->debug('messageCreate', ['data' => $data]);
        if ($data['author']['id'] == $this->discord_id) return true; // ignore messages from self
        $eval = $this->evaluate($data['content']);
        $this->log->debug('messageCreate', ['eval' => $eval]);
        return true;
    }

    private function evaluate(string $text): array
    {
        return json_decode(file_get_contents('https://api.openai.com/v1/moderations', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->openai_token,
                    'Content-Type: application/json'
                ]),
                'content' => json_encode(array('input' => $text))
            ]
        ])), true);
    }

    private function log_message(int $channel_id, string $content): bool
    {
        $this->sync->publish('discord', [
            'op' => 0, // DISPATCH
            't' => 'MESSAGE_CREATE',
            'd' => [
                'content' => $content,
                'channel_id' => $channel_id,
            ]
        ]) or throw new Error('failed to publish message to discord');
        return true;
    }

    public function __destruct()
    {
        $this->log->debug('OpenAIClient::__destruct');
    }
}
