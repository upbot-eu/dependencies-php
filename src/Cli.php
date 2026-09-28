<?php
namespace Upbot\Dependencies;

use Dotenv\Dotenv;
use RuntimeException;
use Throwable;

final class Cli
{
    public static function main(array $args): int
    {
        try {
            $options = [];
            $action = null;
            for ($i = 0; $i < count($args); $i++) {
                $arg = $args[$i];
                if (in_array($arg, ['--help', '-h'], true)) {
                    echo "Usage: upbot init|doctor|report [--project-dir /app] [--dotenv /private/.env.upbot]\n";
                    return 0;
                }
                if (in_array($arg, ['--project-dir', '--dotenv'], true) && isset($args[$i + 1]) && !str_starts_with($args[$i + 1], '--')) $options[$arg] = $args[++$i];
                elseif ($action === null && in_array($arg, ['init', 'doctor', 'report'], true)) $action = $arg;
                else throw new RuntimeException('Invalid arguments. Use --help.');
            }
            $action = $action ?? 'doctor';
            $directory = self::absolute($options['--project-dir'] ?? getcwd(), getcwd());
            $envPath = self::absolute($options['--dotenv'] ?? '.env.upbot', $directory);
            if ($action === 'init') {
                $file = @fopen($envPath, 'x');
                if (!$file) throw new RuntimeException('Cannot create config; check directory and whether it already exists. Existing files are left unchanged.');
                if (DIRECTORY_SEPARATOR !== '\\' && !chmod($envPath, 0600)) { fclose($file); unlink($envPath); throw new RuntimeException('Cannot secure config file.'); }
                $template = "# Keep this file private and outside the public web root.\nUPBOT_TOKEN=replace_with_monitor_token\nUPBOT_ENDPOINT=" . Reporter::DEFAULT_ENDPOINT . "\n# UPBOT_RELEASE=deploy-identifier\n# UPBOT_PRIVATE_PACKAGES=company/internal,@company/private\n";
                $written = fwrite($file, $template);
                fclose($file);
                if ($written !== strlen($template)) { unlink($envPath); throw new RuntimeException('Cannot write complete config.'); }
                echo "Created .env configuration (mode 600). Set UPBOT_TOKEN, then run upbot doctor and upbot report. See README for cron setup.\n";
                return 0;
            }
            if (!file_exists($envPath) && !isset($options['--dotenv'])) $envPath = $directory . '/.env';
            $env = [];
            if (file_exists($envPath)) {
                $contents = @file_get_contents($envPath);
                if ($contents === false) throw new RuntimeException('Cannot read env file.');
                try { $env = Dotenv::parse($contents); } catch (Throwable $error) { throw new RuntimeException('Invalid env file.'); }
            } elseif (isset($options['--dotenv'])) throw new RuntimeException('Cannot read env file.');
            $value = fn ($key) => getenv($key) !== false ? getenv($key) : ($env[$key] ?? null);
            $reporter = new Reporter([
                'endpoint' => $value('UPBOT_ENDPOINT') ?: Reporter::DEFAULT_ENDPOINT,
                'token' => $value('UPBOT_TOKEN'),
                'projectDir' => isset($options['--project-dir']) ? $directory : self::absolute($value('UPBOT_PROJECT_DIR') ?: '.', $directory),
                'release' => $value('UPBOT_RELEASE') ?: null,
                'privatePackages' => array_values(array_filter(array_map('trim', explode(',', $value('UPBOT_PRIVATE_PACKAGES') ?: '')), fn ($name) => $name !== '')),
            ]);
            $count = $reporter->run($action);
            echo $action === 'doctor' ? "UpBot: token and connection OK; $count dependency entries readable. No inventory sent.\n" : "UpBot: $count dependency entries accepted.\n";
            return 0;
        } catch (Throwable $error) {
            fwrite(STDERR, 'UpBot: ' . ($error instanceof RuntimeException ? $error->getMessage() : 'Cannot read valid configuration or lockfiles.') . "\n");
            return 1;
        }
    }

    private static function absolute(string $path, string $base): string
    {
        return str_starts_with($path, '/') || preg_match('~^[a-zA-Z]:[\\\\/]~', $path) ? $path : $base . '/' . $path;
    }
}
