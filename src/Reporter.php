<?php
namespace Upbot\Dependencies;

use RuntimeException;

final class Reporter
{
    public const DEFAULT_ENDPOINT = 'https://app.upbot.eu/api/v1/dependencies/report';
    private array $config;

    public function __construct(array $config)
    {
        $config['endpoint'] = $config['endpoint'] ?? self::DEFAULT_ENDPOINT;
        $url = parse_url($config['endpoint']);
        if (!$url || !isset($url['host']) || (($url['scheme'] ?? '') !== 'https' && !(($url['scheme'] ?? '') === 'http' && in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true)))) throw new RuntimeException('HTTPS endpoint required.');
        foreach (['user', 'pass', 'query', 'fragment'] as $part) if (isset($url[$part])) throw new RuntimeException('Invalid endpoint.');
        if (!str_ends_with($url['path'] ?? '', '/v1/dependencies/report')) throw new RuntimeException('Endpoint must end with /v1/dependencies/report.');
        if (!preg_match('/^upbot_dep_[A-Za-z0-9_-]{43}$/', $config['token'] ?? '')) throw new RuntimeException('Invalid monitoring token. Set UPBOT_TOKEN.');
        if (!is_string($config['projectDir'] ?? null) || !$config['projectDir']) throw new RuntimeException('Invalid project directory.');
        if (isset($config['privatePackages']) && (!is_array($config['privatePackages']) || count(array_filter($config['privatePackages'], 'is_string')) !== count($config['privatePackages']))) throw new RuntimeException('privatePackages must be an array of package names.');
        $this->config = $config;
    }

    public function collectPackages(): array
    {
        $directory = rtrim($this->config['projectDir'], '/');
        $packages = [];
        $files = 0;
        $read = function ($name) use ($directory, &$files) {
            $path = $directory . '/' . $name;
            if (!file_exists($path)) return null;
            if (!is_readable($path)) throw new RuntimeException('Cannot read ' . $name);
            $text = file_get_contents($path);
            if ($text === false) throw new RuntimeException('Cannot read ' . $name);
            $files++;
            // Preserve JSON object/array distinctions before associative decoding.
            $shape = json_decode($text, false, 512, JSON_THROW_ON_ERROR);
            if (!is_object($shape)) throw new RuntimeException('Invalid ' . $name);
            if ($name === 'composer.lock' && (!isset($shape->packages) || !is_array($shape->packages) || isset($shape->{'packages-dev'}) && !is_array($shape->{'packages-dev'}))) throw new RuntimeException('Invalid composer.lock');
            if ($name === 'package-lock.json' && ((($shape->lockfileVersion ?? null) === 1 && !is_object($shape->dependencies ?? null)) || (in_array($shape->lockfileVersion ?? null, [2, 3], true) && !is_object($shape->packages ?? null)))) throw new RuntimeException('Invalid package-lock.json');
            if ($name === 'package-lock.json' && ($shape->lockfileVersion ?? null) === 1) {
                $validate = function ($entries) use (&$validate) {
                    if (!is_object($entries)) throw new RuntimeException('Invalid npm dependencies.');
                    foreach ($entries as $entry) {
                        if (!is_object($entry)) throw new RuntimeException('Invalid npm dependency entry.');
                        if (isset($entry->dependencies)) $validate($entry->dependencies);
                    }
                };
                $validate($shape->dependencies);
            }
            $value = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($value)) throw new RuntimeException('Invalid ' . $name);
            return $value;
        };
        $add = function ($ecosystem, $name, $entry, $dev, $registry = true) use (&$packages) {
            if (!$name) throw new RuntimeException('A lockfile package has no name.');
            $version = isset($entry['version']) && is_string($entry['version']) && preg_match('/^[a-zA-Z0-9.+_-]{1,100}$/', $entry['version']) ? $entry['version'] : 'unknown';
            $packages[] = ['ecosystem' => $ecosystem, 'name' => $name, 'version' => $version, 'dev' => (bool) $dev,
                'source' => $version !== 'unknown' && $registry && !in_array($name, $this->config['privatePackages'] ?? [], true) ? 'registry' : 'unverified'];
        };
        $composer = $read('composer.lock');
        if ($composer !== null) {
            if (!isset($composer['packages']) || !is_array($composer['packages']) || isset($composer['packages-dev']) && !is_array($composer['packages-dev'])) throw new RuntimeException('Invalid composer.lock');
            foreach ($composer['packages'] as $p) $add('Packagist', $p['name'], $p, false);
            foreach ($composer['packages-dev'] ?? [] as $p) $add('Packagist', $p['name'], $p, true);
        }
        $npm = $read('package-lock.json');
        $registry = fn ($p) => isset($p['resolved']) && str_starts_with($p['resolved'], 'https://registry.npmjs.org/');
        if ($npm !== null) {
            if (in_array($npm['lockfileVersion'] ?? null, [2, 3], true) && isset($npm['packages']) && is_array($npm['packages'])) {
                foreach ($npm['packages'] as $path => $p) {
                    if (!str_contains($path, 'node_modules/')) continue;
                    $parts = explode('node_modules/', $path);
                    $add('npm', $p['name'] ?? end($parts), $p, $p['dev'] ?? false, empty($p['link']) && $registry($p));
                }
            } elseif (($npm['lockfileVersion'] ?? null) === 1 && isset($npm['dependencies']) && is_array($npm['dependencies'])) {
                $walk = function ($dependencies) use (&$walk, $add, $registry) {
                    foreach ($dependencies as $name => $p) {
                        $add('npm', $name, $p, $p['dev'] ?? false, $registry($p));
                        if (isset($p['dependencies'])) {
                            if (!is_array($p['dependencies'])) throw new RuntimeException('Invalid npm dependencies.');
                            $walk($p['dependencies']);
                        }
                    }
                };
                $walk($npm['dependencies']);
            } else throw new RuntimeException('Supported npm lockfile versions: 1, 2, 3.');
        }
        if (!$files) throw new RuntimeException('No composer.lock or package-lock.json found.');
        return $packages;
    }

    public function run(string $action = 'report'): int
    {
        if (!in_array($action, ['doctor', 'report'], true)) throw new RuntimeException('Supported actions: doctor, report.');
        $packages = $this->collectPackages();
        $body = json_encode(['packages' => $packages, 'release' => $this->config['release'] ?? null], JSON_THROW_ON_ERROR);
        if (count($packages) > 2000 || strlen($body) > 1024 * 1024) throw new RuntimeException('Inventory exceeds UpBot limits (2000 packages / 1 MiB).');
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension required.');
        $endpoint = $action === 'doctor' ? preg_replace('~/report$~', '/verify', $this->config['endpoint']) : $this->config['endpoint'];
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->config['token']]]);
        if ($action === 'report') curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body]);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($result === false) throw new RuntimeException('UpBot connection failed (check endpoint, TLS and network).');
        if ($action === 'doctor') {
            if ($status !== 204) throw new RuntimeException('UpBot HTTP ' . $status . '; token verification failed.');
        } else {
            if ($status !== 202) throw new RuntimeException('UpBot HTTP ' . $status . '; report not accepted.');
            $response = json_decode($result, true);
            if (($response['accepted'] ?? null) !== true) throw new RuntimeException('UpBot did not confirm report acceptance.');
        }
        return count($packages);
    }
}
