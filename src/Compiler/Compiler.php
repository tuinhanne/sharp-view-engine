<?php declare(strict_types=1);

namespace Sharp\Compiler;

use Sharp\Compiler\Lexer\Lexer;
use Sharp\Compiler\Parser\Parser;
use Sharp\Compiler\Pipeline\{CodeGeneration, DependencyGraph, OptimizePass, SourceMapBuilder};
use Sharp\Contract\LoaderInterface;
use Sharp\Runtime\Component\ComponentRegistry;
use Sharp\Runtime\Directive\DirectiveRegistry;
use Sharp\Security\AstValidator;
use Sharp\Sharp;
use Sharp\Support\Cache\FileCache;
use Sharp\Support\Config;

final class Compiler
{
    /** @var LoaderInterface[] */
    private array $loaders;

    private bool $devMode = false;

    public function __construct(
        private readonly FileCache         $cache,
        private readonly DirectiveRegistry $directives,
        private readonly ComponentRegistry $components,
        private readonly Config            $config,
        LoaderInterface ...$loaders,
    ) {
        $this->loaders = $loaders;
    }

    public function setDevMode(bool $devMode): void
    {
        $this->devMode = $devMode;
    }

    /**
     * Compile the view and return the path to the compiled PHP file.
     */
    public function compile(string $viewKey): string
    {
        $loader = $this->resolveLoader($viewKey);
        $mtime  = $loader->getLastModified($viewKey);
        $key    = $loader->getKey($viewKey);

        // Check dependency graph for invalidation
        $graphRecord = $this->cache->readGraph($key);
        if ($graphRecord !== null) {
            $mtimes   = array_values($graphRecord['mtimes'] ?? []);
            $maxMtime = empty($mtimes) ? $mtime : max($mtime, ...$mtimes);
            if ($this->cache->has($key, $maxMtime)) {
                return $this->cache->getPath($key);
            }
        } elseif ($this->cache->has($key, $mtime)) {
            return $this->cache->getPath($key);
        }

        return $this->doCompile($viewKey, $loader, $key);
    }

    private function doCompile(string $viewKey, LoaderInterface $loader, string $key): string
    {
        $source = $loader->load($viewKey);

        $tokens = (new Lexer())->tokenize($source, $viewKey);
        $ast    = (new Parser())->parse($tokens);

        $absoluteSpPath = $this->devMode
            ? ($this->resolveViewToFilePath($viewKey) ?? '')
            : '';

        $ctx = new CompilationContext(
            $this->directives,
            $this->components,
            $this->config,
            $viewKey,
            fn(string $key) => $this->resolveViewToFilePath($key),
            $this->devMode,
            $absoluteSpPath,
        );

        // Dependency graph
        $depGraph = new DependencyGraph();
        $depGraph->process($ast->children, $ctx);
        $this->cache->writeGraph($key, $depGraph->toArray());

        // AST validation
        (new AstValidator())->process($ast->children, $ctx);

        // Optimize
        $ast->children = (new OptimizePass())->process($ast->children, $ctx);

        // Code generation — with source map tracking in dev mode
        if ($this->devMode) {
            $builder = new SourceMapBuilder();
            $php     = $builder->generate($ast, $ctx);
            $this->cache->writeAst($key, $builder->getAst($absoluteSpPath, $viewKey));
        } else {
            $php = (new CodeGeneration())->generate($ast, $ctx);
        }

        return $this->cache->write($key, $php);
    }

    private function resolveLoader(string $viewKey): LoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($viewKey)) {
                return $loader;
            }
        }

        throw new \Sharp\Exception\RenderException(
            "No loader found for view: [{$viewKey}]"
        );
    }

    public function addLoader(LoaderInterface $loader): void
    {
        array_unshift($this->loaders, $loader); // prepend = higher priority
    }

    /**
     * Compile an absolute Sharp::EXTENSION file path (used for component rendering).
     * Behaves like compile() but uses the file path as the cache key.
     */
    public function compileFilePath(string $absolutePath): string
    {
        $mtime = file_exists($absolutePath) ? (int) filemtime($absolutePath) : 0;
        $key   = 'fp:' . $absolutePath;

        if ($this->cache->has($key, $mtime)) {
            return $this->cache->getPath($key);
        }

        $source = @file_get_contents($absolutePath);
        if ($source === false) {
            throw new \Sharp\Exception\RenderException("Component file not readable: {$absolutePath}");
        }

        $viewName = basename($absolutePath, Sharp::EXTENSION);
        $tokens   = (new Lexer())->tokenize($source, $viewName);
        $ast      = (new Parser())->parse($tokens);
        $ctx      = new CompilationContext(
            $this->directives,
            $this->components,
            $this->config,
            $viewName,
            null,
            $this->devMode,
            $this->devMode ? $absolutePath : '',
        );

        $depGraph = new DependencyGraph();
        $depGraph->process($ast->children, $ctx);
        $this->cache->writeGraph($key, $depGraph->toArray());

        (new AstValidator())->process($ast->children, $ctx);
        $ast->children = (new OptimizePass())->process($ast->children, $ctx);

        if ($this->devMode) {
            $builder = new SourceMapBuilder();
            $php     = $builder->generate($ast, $ctx);
            $this->cache->writeAst($key, $builder->getAst($absolutePath, $viewName));
        } else {
            $php = (new CodeGeneration())->generate($ast, $ctx);
        }

        return $this->cache->write($key, $php);
    }

    /**
     * Resolve a view key to its absolute file path, or null for non-file-backed loaders.
     * Used by DependencyGraph to track mtimes for #extends / #include dependencies.
     */
    private function resolveViewToFilePath(string $viewKey): ?string
    {
        foreach ($this->loaders as $loader) {
            if (!$loader->supports($viewKey)) continue;
            if (method_exists($loader, 'getFilePath')) {
                $path = $loader->getFilePath($viewKey);
                return (file_exists($path)) ? $path : null;
            }
            return null; // e.g. MemoryLoader — no physical file
        }
        return null;
    }
}
