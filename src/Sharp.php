<?php declare(strict_types=1);

namespace Sharp;

use Sharp\Compiler\Compiler;
use Sharp\Contract\LoaderInterface;
use Sharp\Loader\{FileLoader, MemoryLoader, NamespaceLoader};
use Sharp\Runtime\Component\{ComponentRegistry, ComponentRenderer};
use Sharp\Runtime\Directive\DirectiveRegistry;
use Sharp\Runtime\Environment;
use Sharp\Runtime\Layout\LayoutManager;
use Sharp\Support\Cache\FileCache;
use Sharp\Support\Config;

final class Sharp
{
    /** File extension used for all Sharp template files (e.g. {@see Sharp::EXTENSION}). */
    public const EXTENSION = '.sp';

    private Config            $config;
    private FileCache         $cache;
    private DirectiveRegistry $directives;
    private ComponentRegistry $components;
    private Compiler          $compiler;
    private Environment       $environment;
    private LayoutManager     $layoutManager;

    /** Explicit override set via setProduction(). Null means "not set — fall back to config". */
    private ?bool $productionOverride = null;

    public function __construct(string $rootDir = null)
    {
        $this->config     = Config::load($rootDir ?? getcwd());
        $this->cache      = new FileCache(
            $this->config->getCachePath(),
            $this->config->getGraphPath(),
            $this->config->getAstPath(),
        );
        $this->directives  = new DirectiveRegistry();
        $this->components  = new ComponentRegistry(
            $this->config->getViewPath() . DIRECTORY_SEPARATOR . 'components',
        );
        $this->layoutManager = new LayoutManager();

        $this->compiler = new Compiler(
            $this->cache,
            $this->directives,
            $this->components,
            $this->config,
            new MemoryLoader(),
            new NamespaceLoader(),
            new FileLoader($this->config->getViewPath()),
        );

        $this->environment = new Environment(
            $this->layoutManager,
            new ComponentRenderer($this->components),
            $this->directives,
            \Closure::fromCallable([$this, 'executeTemplate']),
            \Closure::fromCallable([$this->compiler, 'compile']),
            \Closure::fromCallable([$this->compiler, 'compileFilePath']),
        );
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Set whether the engine runs in production mode.
     *
     * - `false` (default): dev mode — injects `data-sharp-src` annotations into rendered HTML
     *   and writes `.ast` source-map files to `.sharp/ast/`. Use during local development.
     * - `true`: production mode — no annotations, no `.ast` files written. Use when deploying.
     *
     * Priority chain (highest → lowest):
     *   1. This method (`setProduction()`)
     *   2. `"devMode"` field in `sharp.config.json`
     *   3. Default: dev mode on (`false`)
     */
    public function setProduction(bool $isProduction): static
    {
        $this->productionOverride = $isProduction;
        return $this;
    }

    /**
     * Render a view and return the HTML string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $this->compiler->setDevMode($this->isDevMode());
        $this->layoutManager->reset();

        $path   = $this->compiler->compile($view);
        $output = $this->executeTemplate($path, $data);

        // Layout inheritance loop
        while ($this->layoutManager->hasParent()) {
            $parentKey  = $this->layoutManager->consumeParent();
            $parentPath = $this->compiler->compile($parentKey);
            $output     = $this->executeTemplate($parentPath, $data);
        }

        return $output;
    }

    /**
     * Register a custom directive.
     *
     * @param callable(string ...$args): string $handler  Returns a PHP code string.
     *
     * Example:
     *   $sharp->directive('money', fn($e) => "<?php echo number_format({$e}, 2); ?>");
     */
    public function directive(string $name, callable $handler): self
    {
        $this->directives->register($name, $handler);
        return $this;
    }

    /**
     * Register a component.
     * $target is either an absolute file path (Sharp::EXTENSION) or a fully-qualified class name.
     */
    public function component(string $name, string $target): self
    {
        $this->components->register($name, $target);
        return $this;
    }

    /**
     * Add a custom loader with the highest priority.
     */
    public function addLoader(LoaderInterface $loader): self
    {
        $this->compiler->addLoader($loader);
        return $this;
    }

    /**
     * Add a namespace mapping.
     * After calling this, 'admin::dashboard' resolves to {path}/dashboard (Sharp::EXTENSION).
     */
    public function namespace(string $ns, string $path): self
    {
        $this->compiler->addLoader(new NamespaceLoader([$ns => $path]));
        return $this;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    /**
     * Resolve the effective dev-mode flag using the priority chain:
     *   setProduction() override → config devMode → default (dev mode on).
     */
    private function isDevMode(): bool
    {
        if ($this->productionOverride !== null) {
            return !$this->productionOverride;
        }

        $configDevMode = $this->config->getDevMode();
        if ($configDevMode !== null) {
            return $configDevMode;
        }

        return true; // default: dev mode on
    }

    // ─── Template execution ──────────────────────────────────────────────────

    private function executeTemplate(string $compiledPath, array $data): string
    {
        $__env = $this->environment;
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $compiledPath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->environment->cleanupSections();
            throw $e;
        }
    }
}
