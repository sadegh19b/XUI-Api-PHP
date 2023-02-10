<?php

namespace XUI;

class Helper
{
    protected const OneGigaBytes =  1073741824;

    /**
     * @throws \Exception
     */
    public static function guid4($data = null): string
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) === 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param  ConfigBuilder  $config
     * @param  array  $hostData  e.g. ['address' => '8.8.8.8', 'port' => '2020', 'host' => 'p1.test.com']
     * @param  string|null  $email
     * @param  string  $customRemark
     *
     * @return string
     * @throws \Exception
     */
    public static function generateLink(ConfigBuilder $config, array $hostData, ?string $email, string $customRemark = ''): string
    {
        if (! isset($hostData['address'])) {
            throw new \Exception('The hostData argument `address` index is missing!');
        }

        $configObject = $config->getObject(false);
        $client = $config->getClient($email);
        $address = strtok(clean_domain($hostData['address']), ':');
        $remark = ($customRemark !== '') ? $customRemark : $configObject->remark;

        $uniqueId = isset($configObject->id)
            ? $client['id']
            : $config->getDefaultClientId();

        if (isset($configObject->id)) {
            $path = match ($configObject->streamSettings->network) {
                'tcp' => $configObject->streamSettings->tcpSettings->header->request->path[0] ?? '/',
                'ws' => $configObject->streamSettings->wsSettings->path ?? '/',
            };

            $host = $hostData['host'] ?? null;
            if (empty($host)) {
                $host = match ($configObject->streamSettings->network) {
                    'tcp' => $configObject->streamSettings->tcpSettings->header->request->headers->Host[0] ?? '',
                    'ws' => $configObject->streamSettings->wsSettings->headers->Host ?? '',
                };
            }

            $headerType = match ($configObject->streamSettings->network) {
                'tcp' => $configObject->streamSettings->tcpSettings->header->type ?? 'none',
                'ws' => $configObject->streamSettings->wsSettings->header->type ?? 'none',
            };

            $vlessStreamSettings = match ($configObject->streamSettings->network) {
                'tcp' => "&path={$path}&host={$host}&sni={$host}&headerType={$headerType}",
                'ws' => "&path={$path}&host={$host}&sni={$host}",
            };
        }

        return match ($configObject->protocol) {
            'vless' => sprintf(
                '%s://%s@%s:%s?type=%s&security=%s%s#%s',
                $configObject->protocol,
                $uniqueId,
                $address,
                $hostData['port'] ?? $configObject->port,
                $configObject->streamSettings->network,
                $configObject->streamSettings->security,
                $vlessStreamSettings ?? '',
                $remark
            ),
            'vmess' => sprintf(
                '%s://%s',
                $configObject->protocol,
                base64_encode(
                    json_encode([
                        'v' => '2',
                        'ps' => $remark,
                        'add' => $address,
                        'port' => $hostData['port'] ?? $configObject->port,
                        'id' => $uniqueId,
                        'aid' => $client['alterId'] ?? 0,
                        'net' => $configObject->streamSettings->network,
                        'type' => $headerType ?? 'none',
                        'sni' => $hostData['host'] ?? $host ?? '',
                        'host' => $hostData['host'] ?? $host ?? '',
                        "scy" => "auto",
                        'path' => $path ?? '/',
                        'tls' => $configObject->streamSettings->security
                    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                )
            )
        };
    }

    public static function calculateExpiryTimeInDays(int $days): float|int
    {
        return ($days !== 0) ? floor(microtime(true) * 1000) + (864000 * $days * 100) : 0;
    }

    public static function calculateVolumeSizeInGB(int $gigabytes): float|int
    {
        return ($gigabytes !== 0) ? $gigabytes * self::OneGigaBytes : 0;
    }

    public static function convertBytesToGB(int $bytes): float
    {
        return ($bytes !== 0) ? $bytes / self::OneGigaBytes : 0;
    }

    public static function formatSizeUnits(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).$units[$i];
    }
}