<?php

namespace XUI;

class ConfigBuilder
{
    private array $config;

    protected array $default = [
        'up' => 0,
        'down' => 0,
        'total' => 0,
        'remark' => '',
        'enable' => true,
        'expiryTime' => 0,
        'listen' => '',
        'port' => 0,
        'protocol' => '',
        'settings' => [
            'clients' => [],
        ],
        'streamSettings' => [
            'network' => '',
            'security' => '',
        ],
        'sniffing' => [
            'enabled' => true,
            'destOverride' => [
                'http',
                'tls',
            ],
        ],
    ];

    public function __construct(string $protocol = 'vmess')
    {
        $this->reset()
            ->setProtocol($protocol)
            ->setRemark($protocol.'-'.time());
    }

    private function init(): void
    {
        $this->setProtocol('vmess')
            ->setNetwork('ws')
            ->setSecurity('none')
            ->setRemark('vmess-'.time())
            ->setPort(random_int(1000, 65535))
            ->addClient('', 0, 0, 0);
    }

    public function reset(): static
    {
        $this->config = $this->default;

        $this->init();

        return $this;
    }

    public function load(string|array|object $config): static
    {
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        if (is_object($config)) {
            $config = (array) $config;
        }

        $this->config = $config;

        $this->sanitizeLoadConfig();

        return $this;
    }

    public function getArray(bool $sanitize = true): array
    {
        if ($sanitize) {
            $this->sanitizeGetConfig();
        }

        return $this->config;
    }

    public function getObject(bool $sanitize = true): object
    {
        if ($sanitize) {
            $this->sanitizeGetConfig();
        }

        return json_decode(json_encode($this->config));
    }

    public function getJson(bool $sanitize = true): bool|string
    {
        if ($sanitize) {
            $this->sanitizeGetConfig();
        }

        return json_encode($this->config);
    }

    private function sanitizeLoadConfig(): void
    {
        $this->config['settings'] = json_decode($this->config['settings'], true);
        $this->config['streamSettings'] = json_decode($this->config['streamSettings'], true);
        $this->config['sniffing'] = json_decode($this->config['sniffing'], true);

        if (empty($this->config['streamSettings']['wsSettings']['headers'])) {
            $this->config['streamSettings']['wsSettings']['headers'] = (object) [];
        }
    }

    private function sanitizeGetConfig(): void
    {
        $this->config['settings'] = json_encode($this->config['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->config['streamSettings'] = json_encode($this->config['streamSettings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->config['sniffing'] = json_encode($this->config['sniffing'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function setNetwork(string $value): static
    {
        if (! in_array($value, ['ws', 'tcp'])) {
            throw new \InvalidArgumentException(
                'Invalid type argument value. Only `ws` and `tcp` are supported.'
            );
        }

        if ($value === 'ws') {
            $this->config['streamSettings']['wsSettings'] = [
                'acceptProxyProtocol' => false,
                'path' => '/',
                'headers' => (object) [],
            ];

            unset($this->config['streamSettings']['tcpSettings']);
        }

        if ($value === 'tcp') {
            $this->config['streamSettings']['tcpSettings'] = [
                'acceptProxyProtocol' => false,
                'header' => ['type' => 'none'],
            ];

            unset($this->config['streamSettings']['wsSettings']);
        }

        $this->config['streamSettings']['network'] = $value;

        return $this;
    }

    public function setSecurity(
        string $type,
        string $serverName = null,
        string $certificateFile = '/root/cert.crt',
        string $keyFile = '/root/private.key'
    ): static
    {
        if (! in_array($type, ['none', 'tls'])) {
            throw new \InvalidArgumentException(
                'Invalid type argument value. Only `none` and `tls` are supported.'
            );
        }

        if ($type === 'tls' && is_null($serverName)) {
            throw new \InvalidArgumentException(
                'Invalid serverName argument value. It can\'t be null.'
            );
        }

        if ($type === 'tls') {
            $this->config['streamSettings']['tlsSettings'] = [
                'serverName' => $serverName,
                'certificates' => [
                    [
                        'certificateFile' => $certificateFile,
                        'keyFile' => $keyFile,
                    ],
                ],
                'alpn' => [],
            ];
        } else {
            unset($this->config['streamSettings']['tlsSettings']);
        }

        $this->config['streamSettings']['security'] = $type;

        return $this;
    }

    public function resetDefaultClientId(): static
    {
        $this->config['settings']['clients'][0]['id'] = Helper::guid4();

        return $this;
    }

    public function getDefaultClientId(): string
    {
        return $this->config['settings']['clients'][0]['id'];
    }

    public function getClientId(string|null $email): string
    {
        $clientId = '';

        foreach ($this->config['settings']['clients'] as $key => $client) {
            if ($client['email'] === $email) {
                $clientId = $this->config['settings']['clients'][$key]['id'];
            }
        }

        return $clientId;
    }

    public function setDefaultClient(string $email, int $limitIp, int $limitSize, int $limitDays): static
    {
        $this->config['settings']['clients'][0] = $this->createClient($email, $limitIp, $limitSize, $limitDays);

        return $this;
    }

    public function getDefaultClient(): array
    {
        return $this->config['settings']['clients'][0];
    }

    public function getClient(?string $email): array
    {
        $_client = [];

        foreach ($this->config['settings']['clients'] as $key => $client) {
            if ($client['email'] === $email) {
                $_client = $this->config['settings']['clients'][$key];
            }
        }

        return $_client;
    }

    public function getClients(): array
    {
        return $this->config['settings']['clients'];
    }

    private function createClient(string $email, int $limitIp, int $limitSize, int $limitDays): array
    {
        $clientData = [
            'id' => Helper::guid4(),
            'email' => $email,
            'limitIp' => $limitIp,
            'totalGB' => Helper::calculateVolumeSizeInGB($limitSize),
            'expiryTime' => Helper::calculateExpiryTimeInDays($limitDays),
        ];

        if ($this->config['protocol'] === 'vless') {
            $clientData['flow'] = 'xtls-rprx-direct';
        }

        if ($this->config['protocol'] === 'vmess') {
            $clientData['alterId'] = 0;
        }

        return $clientData;
    }

    public function addClient(string $email, int $limitIp, int $limitSize, int $limitDays): static
    {
        $this->config['settings']['clients'][] = $this->createClient($email, $limitIp, $limitSize, $limitDays);

        return $this;
    }

    public function updateClient(string $accessEmail, int $limitIp, int $limitSize, int $limitDays, ?string $newEmail = null): static
    {
        foreach ($this->config['settings']['clients'] as $key => $client) {
            if ($client['email'] === $accessEmail) {
                $this->config['settings']['clients'][$key]['email'] = is_null($newEmail) ? $accessEmail : $newEmail;
                $this->config['settings']['clients'][$key]['limitIp'] = $limitIp;
                $this->config['settings']['clients'][$key]['totalGB'] = Helper::calculateVolumeSizeInGB($limitSize);
                $this->config['settings']['clients'][$key]['expiryTime'] = Helper::calculateExpiryTimeInDays($limitDays);
            }
        }

        return $this;
    }

    public function removeClient(string $email): static
    {
        $this->config['settings']['clients'] = array_values(
            array_filter(
                $this->config['settings']['clients'],
                static fn($client) => $client['email'] !== $email
            )
        );

        return $this;
    }

    public function setClients(array $value): static
    {
        $this->config['settings']['clients'] = $value;

        return $this;
    }

    public function setUp(int $value): static
    {
        $this->config['up'] = $value;

        return $this;
    }

    public function setDown(int $value): static
    {
        $this->config['down'] = $value;

        return $this;
    }

    public function setTotal(int $value): static
    {
        $this->config['total'] = Helper::calculateVolumeSizeInGB($value);

        return $this;
    }

    public function setRemark(string $value): static
    {
        $this->config['remark'] = $value;

        return $this;
    }

    public function setEnable(bool $value): static
    {
        $this->config['enable'] = $value;

        return $this;
    }

    public function setExpiryTime(int $value, bool $inDays = false): static
    {
        $this->config['expiryTime'] = $inDays
            ? Helper::calculateExpiryTimeInDays($value)
            : $value;

        return $this;
    }

    public function setListen($value): static
    {
        $this->config['listen'] = $value;

        return $this;
    }

    public function setPort(int $value): static
    {
        if ($value < 1 || $value > 65535) {
            throw new \InvalidArgumentException(
                'Invalid port number argument value. Port number only can between 1 to 65535.'
            );
        }

        $this->config['port'] = $value;

        return $this;
    }

    public function setProtocol(string $value): static
    {
        if (! in_array($value, ['vless', 'vmess'])) {
            throw new \InvalidArgumentException(
                'Invalid protocol argument value. Only `vless` and `vmess` are supported.'
            );
        }

        if ($value === 'vless') {
            $this->config['settings']['decryption'] = 'none';
            $this->config['settings']['fallbacks'] = [];

            unset($this->config['settings']['disableInsecureEncryption']);
        }

        if ($value === 'vmess') {
            $this->config['settings']['disableInsecureEncryption'] = false;

            unset($this->config['settings']['decryption'], $this->config['settings']['fallbacks']);
        }

        $this->config['protocol'] = $value;

        return $this;
    }

    public function setSettings(array $value): static
    {
        $this->config['settings'] = $value;

        return $this;
    }

    public function setStreamSettings(array $value): static
    {
        $this->config['streamSettings'] = $value;

        return $this;
    }

    public function setSniffing(array $value): static
    {
        $this->config['sniffing'] = $value;

        return $this;
    }
}