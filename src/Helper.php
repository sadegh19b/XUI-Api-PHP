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

    public static function generateLink(ConfigBuilder $config, string $hostDomain, ?string $email, string $customRemark = ''): string
    {
        $configObject = $config->getObject(false);
        $client = $config->getClient($email);
        $hostDomain = strtok(clean_domain($hostDomain), ':');
        $remark = ($customRemark !== '') ? $customRemark : $configObject->remark;

        $uniqueId = isset($configObject->id)
            ? $client['id']
            : $config->getDefaultClientId();

        if (isset($configObject->id)) {
            $path = match ($configObject->streamSettings->network) {
                'tcp' => $configObject->streamSettings->tcpSettings->header->request->path[0] ?? '',
                'ws' => $configObject->streamSettings->wsSettings->path ?? '',
            };

            $host = match ($configObject->streamSettings->network) {
                'tcp' => $configObject->streamSettings->tcpSettings->header->request->headers->Host[0] ?? '',
                'ws' => $configObject->streamSettings->wsSettings->headers->Host ?? '',
            };

            $headerType = match ($configObject->streamSettings->network) {
                'tcp' => $configObject->streamSettings->tcpSettings->header->type ?? '',
                'ws' => $configObject->streamSettings->wsSettings->header->type ?? '',
            };

            $vlessStreamSettings = match ($configObject->streamSettings->network) {
                'tcp' => "&path={$path}&host={$host}&headerType={$headerType}",
                'ws' => "&path={$path}&host={$host}",
            };
        }

        return match ($configObject->protocol) {
            'vless' => sprintf(
                '%s://%s@%s:%s?type=%s&security=%s%s#%s',
                $configObject->protocol,
                $uniqueId,
                $hostDomain,
                $configObject->port,
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
                        'add' => $hostDomain,
                        'port' => $configObject->port,
                        'id' => $uniqueId,
                        'aid' => $client['alterId'] ?? 0,
                        'net' => $configObject->streamSettings->network,
                        'type' => $headerType ?? 'none',
                        'host' => $host ?? '',
                        'path' => $path ?? '',
                        'tls' => $configObject->streamSettings->security
                    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                )
            )
        };
    }

    public static function calculateExpiryTimeInDays(int $value): float|int
    {
        return ($value !== 0) ? floor(microtime(true) * 1000) + (864000 * $value * 100) : 0;
    }

    public static function calculateVolumeSizeInGB(int $value): float|int
    {
        return ($value !== 0) ? $value * self::OneGigaBytes : 0;
    }

    public static function convertBytesToGB(int $bytes): float
    {
        return $bytes / ((10 ** 9) ** 1);
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