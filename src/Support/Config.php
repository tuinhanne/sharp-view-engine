<?php declare(strict_types=1);

namespace Sharp\Support;

use Sharp\Exception\ConfigException;

final class Config
{
    private const DEFAULTS = [
        'viewPath' => 'templates/',
        'sandbox'  => true,
    ];

    private string $viewPath;
    private bool $sandbox;
    private string $rootDir;
    private ?bool $devMode;

    private function __construct(string $rootDir, array $data)
    {
        $this->rootDir  = rtrim($rootDir, '/\\');
        $this->viewPath = $this->resolvePath($data['viewPath'] ?? self::DEFAULTS['viewPath']);
        $this->sandbox  = (bool) ($data['sandbox'] ?? self::DEFAULTS['sandbox']);
        $this->devMode  = array_key_exists('devMode', $data) ? (bool) $data['devMode'] : null;
    }

    public static function load(string $rootDir = null): self
    {
        $rootDir    = rtrim($rootDir ?? self::resolveProjectRoot(), '/\\');
        $configFile = $rootDir . DIRECTORY_SEPARATOR . 'sharp.config.json';

        if (!file_exists($configFile)) {
            self::createDefault($configFile);
            return new self($rootDir, self::DEFAULTS);
        }

        $raw  = file_get_contents($configFile);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new ConfigException(
                "Invalid JSON in sharp.config.json: " . json_last_error_msg()
            );
        }

        return new self($rootDir, array_merge(self::DEFAULTS, $data));
    }

    /**
     * Resolve project root by walking up from this file's real location
     * until a directory containing composer.json is found.
     * Works correctly when Sharp is installed as a regular Composer package
     * (copied into vendor/), where __DIR__ resolves to the host project's
     * vendor directory — never a symlink or junction.
     */
    private static function resolveProjectRoot(): string
    {
        $sep = DIRECTORY_SEPARATOR;
        $dir = __DIR__;
        while (true) {
            if (
                file_exists($dir . $sep . 'vendor' . $sep . 'autoload.php')
                && strpos($dir, $sep . 'vendor' . $sep) === false
            ) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return getcwd();
    }

    private static function createDefault(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $config = array_merge(self::DEFAULTS, ['devMode' => true]);
        $json   = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($path, $json);
    }

    private function resolvePath(string $path): string
    {
        // Absolute path: starts with / (Unix) or has a drive letter (Windows)
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path)) {
            return rtrim($path, '/\\');
        }
        return $this->rootDir . DIRECTORY_SEPARATOR . rtrim($path, '/\\');
    }

    public function getRootDir(): string  { return $this->rootDir; }
    public function getViewPath(): string { return $this->viewPath; }
    public function getCachePath(): string { return $this->rootDir . DIRECTORY_SEPARATOR . '.sharp' . DIRECTORY_SEPARATOR . 'compiled'; }
    public function getGraphPath(): string { return $this->rootDir . DIRECTORY_SEPARATOR . '.sharp' . DIRECTORY_SEPARATOR . 'graph'; }
    public function getAstPath(): string  { return $this->rootDir . DIRECTORY_SEPARATOR . '.sharp' . DIRECTORY_SEPARATOR . 'ast'; }
    public function isSandboxed(): bool   { return $this->sandbox; }
    /** Returns the devMode value from config, or null if the field is absent. */
    public function getDevMode(): ?bool   { return $this->devMode; }
}
