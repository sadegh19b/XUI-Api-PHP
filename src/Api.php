<?php

namespace XUI;

use GuzzleHttp;

class Api
{
    protected string $hostDomain;
    protected string $hostUrl;
    private string $username;
    private string $password;

    private ConfigBuilder $configBuilder;
    private GuzzleHttp\Client $guzzleHttp;

    private int $inboundId;

    private bool $responseStatus;
    private string $responseMessage;

    private string $cookiePath = BASE_PATH.'/storage/app/x-ui-cookie.json';

    protected array $endpoints = [
        'login' => '/login',
        'inbounds' => '/xui/API/inbounds',
        'addInbound' => '/xui/API/inbounds/add',
        'getInbound' => '/xui/API/inbounds/get/{id}',
        'updateInbound' => '/xui/API/inbounds/update/{id}',
        'deleteInbound' => '/xui/API/inbounds/del/{id}',
    ];

    protected array $endpointsMethodMap = [
        'login' => 'POST',
        'inbounds' => 'GET',
        'addInbound' => 'POST',
        'getInbound' => 'GET',
        'updateInbound' => 'POST',
        'deleteInbound' => 'POST',
    ];

    public function __construct(string $host, string $username, string $password)
    {
        $this->hostDomain = strtok(clean_domain($host), ':');
        $this->hostUrl = $host;
        $this->username = $username;
        $this->password = $password;

        $this->configBuilder = (new ConfigBuilder);
        $this->guzzleHttp = (new GuzzleHttp\Client);

        $this->init();
    }

    public function getInbounds(): array
    {
        return $this->request('inbounds');
    }

    public function getInbound(int $id): array
    {
        $this->setInboundId($id);

        return $this->request('getInbound');
    }

    public function addInbound(
        string $remark,
        string $protocol,
        int $port,
        string $network = 'ws',
        string $security = 'tls',
        int $limitSize = 0,
        int $limitDays = 0
    ): array
    {
        $config = $this->configBuilder
            ->reset()
            ->setSecurity($security, $this->hostDomain)
            ->setNetwork($network)
            ->setProtocol($protocol)
            ->setRemark($remark)
            ->setPort($port)
            ->setTotal($limitSize)
            ->setExpiryTime($limitDays, true);

        return $this->request('addInbound', $config->getArray());
    }

    public function addInboundByConfig(ConfigBuilder $config): array
    {
        return $this->request('addInbound', $config->getArray());
    }

    public function editInbound(
        int $id,
        string $remark,
        string $protocol,
        int $port,
        string $network = 'ws',
        string $security = 'tls',
        int $limitSize = 0,
        int $limitDays = 0
    ): array
    {
        $this->setInboundId($id);
        $inbound = $this->getInbound($id);

        $config = $this->configBuilder
            ->load($inbound)
            ->setSecurity($security, $this->hostDomain)
            ->setNetwork($network)
            ->setProtocol($protocol)
            ->setRemark($remark)
            ->setPort($port)
            ->setTotal($limitSize)
            ->setExpiryTime($limitDays, true);

        return $this->request('updateInbound', $config->getArray());
    }

    public function editInboundByConfig(int $id, ConfigBuilder $config): array
    {
        $this->setInboundId($id);
        $_config = $config->getArray();

        if (!isset($_config['id']) || $_config['id'] !== $id) {
            throw new \RuntimeException('Invalid Inbound ID or Configuration Provided!');
        }

        return $this->request('updateInbound', $_config);
    }

    public function removeInbound(int $id): bool
    {
        $this->setInboundId($id);

        return $this->request('deleteInbound');
    }

    public function addClient(
        int $inboundId,
        string $email,
        int $limitIp = 0,
        int $limitSize = 0,
        int $limitDays = 0,
        bool $isReplaceDefaultClient = false
    ): array
    {
        $this->setInboundId($inboundId);
        $inbound = $this->getInbound($inboundId);
        $config = $this->configBuilder->load($inbound);

        if ($isReplaceDefaultClient && $config->getDefaultClient()['email'] === '') {
            $config->setDefaultClient($email, $limitIp, $limitSize, $limitDays);
        } else {
            if (! empty($config->getClient($email))) {
                $email = time().'_'.$email;
            }

            $config->addClient($email, $limitIp, $limitSize, $limitDays);
        }

        return $this->request('updateInbound', $config->getArray());
    }

    public function editClient(
        int $inboundId,
        string $email,
        int $limitIp = 0,
        int $limitSize = 0,
        int $limitDays = 0,
        string $newEmail = null,
    ): array
    {
        $this->setInboundId($inboundId);
        $inbound = $this->getInbound($inboundId);

        $config = $this->configBuilder
            ->load($inbound)
            ->updateClient($email, $limitIp, $limitSize, $limitDays, $newEmail);

        return $this->request('updateInbound', $config->getArray());
    }

    public function removeClient(int $inboundId, string $email): bool
    {
        $this->setInboundId($inboundId);
        $inbound = $this->getInbound($inboundId);

        $config = $this->configBuilder
            ->load($inbound)
            ->removeClient($email);

        if (count($config->getClients()) === 0) {
            $config->addClient('', 0, 0, 0);
        }

        return $this->request('updateInbound', $config->getArray());
    }

    private function login(): void
    {
        $body = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        $this->request('login', $body);
    }

    /**
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    private function request(string $path, array $params = []): mixed
    {
        if (! array_key_exists($path, $this->endpoints)) {
            throw new \RuntimeException('Invalid request path!');
        }

        $request = $this->guzzleHttp->request(
            $this->getMethod($path),
            $this->getEndpoint($path),
            array_merge(
                [
                    'cookies' => $this->getCookie(),
                    //'http_errors' => false,
                    'headers' => [
                        //'User-Agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
                        'Content-Type' => $this->getContentType($path),
                    ]
                ],
                $this->getRequestParams($path, $params)
            )
        );

        $response = json_decode($request->getBody()->getContents(), true);

        $this->responseMessage = $response['msg'] ?? '';
        $this->responseStatus = $response['success'] ?? false;

        return ($path === 'deleteInbound')
            ? $this->responseStatus
            : $response['obj'] ?? [];
    }

    public function isResponseSuccess(): bool
    {
        return $this->responseStatus ?? false;
    }

    public function getResponseMessage(): string
    {
        return $this->responseMessage ?? '';
    }

    private function setInboundId(int $id): void
    {
        $this->inboundId = $id;
    }

    private function getInboundId(): int
    {
        return $this->inboundId;
    }

    public function setCookie($path): static
    {
        $this->cookiePath = $path;

        return $this;
    }

    private function getCookie(): GuzzleHttp\Cookie\FileCookieJar
    {
        return new GuzzleHttp\Cookie\FileCookieJar($this->cookiePath);
    }

    private function init(): void
    {
        if (!file_exists($this->cookiePath) || empty(file_get_contents($this->cookiePath))) {
            file_put_contents($this->cookiePath, '[]');

            $this->login();

            return;
        }

        $cookieData = json_decode(file_get_contents($this->cookiePath), true)[0];

        if ($cookieData['Domain'] !== $this->hostDomain || time() > $cookieData['Expires']) {
            $this->login();
        }
    }

    private function getMethod(string $name): string
    {
        return $this->endpointsMethodMap[$name];
    }

    private function getEndpoint(string $name): string
    {
        $endpoint = $this->endpoints[$name];

        if (in_array($name, ['getInbound', 'updateInbound', 'deleteInbound'])) {
            $endpoint = str_replace('{id}', $this->getInboundId(), $this->endpoints[$name]);
        }

        return generate_url(rtrim($this->hostUrl, '/'), true).$endpoint;
    }

    private function getContentType(string $name): string
    {
        $type = 'application/json';

        if (in_array($name, ['login', 'inbounds', 'deleteInbound'])) {
            $type = 'application/x-www-form-urlencoded';
        }

        return $type;
    }

    private function getRequestParams(string $name, array $params): array
    {
        if (in_array($name, ['addInbound', 'updateInbound'])) {
            return ['json' => $params];
        }

        return ['form_params' => $params];
    }
}