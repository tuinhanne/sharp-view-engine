<?php declare(strict_types=1);

namespace Sharp\Runtime;

use Sharp\Runtime\Layout\LayoutManager;
use Sharp\Runtime\Component\ComponentRenderer;
use Sharp\Runtime\Directive\DirectiveRegistry;
use Sharp\Exception\RenderException;

/**
 * The $__env object injected into every compiled template.
 * Acts as the bridge between compiled PHP output and Sharp's runtime systems.
 */
final class Environment
{
    public function __construct(
        private readonly LayoutManager     $layoutManager,
        private readonly ComponentRenderer $componentRenderer,
        private readonly DirectiveRegistry $directiveRegistry,
        /** Callable: (string $compiledPath, array $data): string */
        private readonly \Closure          $templateExecutor,
        /** Callable: (string $viewKey): string  — returns compiled path */
        private readonly \Closure          $compiler,
        /** Callable: (string $absoluteFilePath): string  — compile a Sharp::EXTENSION file path directly */
        private readonly \Closure          $fileCompiler,
    ) {}

    // ─── Layout ──────────────────────────────────────────────────────────────

    public function extends(string $layout): void
    {
        $this->layoutManager->extends($layout);
    }

    public function startSection(string $name): void
    {
        $this->layoutManager->startSection($name);
    }

    public function stopSection(): void
    {
        $this->layoutManager->stopSection();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->layoutManager->yieldSection($name, $default);
    }

    public function yieldParentContent(): string
    {
        return $this->layoutManager->yieldParentContent();
    }

    // ─── Stacks ───────────────────────────────────────────────────────────────

    public function startPush(string $name): void
    {
        $this->layoutManager->startPush($name);
    }

    public function stopPush(): void
    {
        $this->layoutManager->stopPush();
    }

    public function startPrepend(string $name): void
    {
        $this->layoutManager->startPrepend($name);
    }

    public function stopPrepend(): void
    {
        $this->layoutManager->stopPrepend();
    }

    public function yieldStack(string $name): string
    {
        return $this->layoutManager->yieldStack($name);
    }

    // ─── Includes ────────────────────────────────────────────────────────────

    public function renderInclude(string $view, array $data): string
    {
        unset($data['__env'], $data['__i']);

        $compiledPath = ($this->compiler)($view);
        return ($this->templateExecutor)($compiledPath, $data);
    }

    /**
     * Render a view only if it can be resolved — silently skip if it does not exist.
     *
     * @param array<string, mixed> $data
     */
    public function renderIncludeIf(string $view, array $data): string
    {
        unset($data['__env'], $data['__i']);

        try {
            $compiledPath = ($this->compiler)($view);
            return ($this->templateExecutor)($compiledPath, $data);
        } catch (RenderException) {
            return '';
        }
    }

    /**
     * Render the first view in $views that can be resolved.
     * Throws RenderException if none of the views exist.
     *
     * @param string[] $views
     * @param array<string, mixed> $data
     */
    public function renderIncludeFirst(array $views, array $data): string
    {
        unset($data['__env'], $data['__i']);

        foreach ($views as $view) {
            try {
                $compiledPath = ($this->compiler)($view);
                return ($this->templateExecutor)($compiledPath, $data);
            } catch (RenderException) {
                // View not found — try the next one
                continue;
            }
        }

        throw new RenderException(
            'None of the views in #includeFirst could be resolved: [' . implode(', ', $views) . ']',
        );
    }

    // ─── Components ──────────────────────────────────────────────────────────

    public function renderComponent(string $name, array $props, array $slots): string
    {
        return $this->componentRenderer->render(
            $name,
            $props,
            $slots,
            function (string $filePath, array $data): string {
                // File-backed components pass a raw Sharp::EXTENSION path; compile it first.
                $compiledPath = ($this->fileCompiler)($filePath);
                return ($this->templateExecutor)($compiledPath, $data);
            },
        );
    }

    // ─── Directives ──────────────────────────────────────────────────────────

    // (Directives are inlined at compile time — this method is here as a fallback
    //  in case a future runtime-directive mode is needed.)
    public function callDirective(string $name, array $args): string
    {
        throw new RenderException(
            "Directive #{$name} was not resolved at compile time.",
        );
    }

    // ─── Cleanup ─────────────────────────────────────────────────────────────

    /**
     * Flush any dangling ob_start() calls opened by #section blocks.
     * Must be called when an exception occurs inside executeTemplate().
     */
    public function cleanupSections(): void
    {
        $this->layoutManager->cleanupSections();
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function getLayoutManager(): LayoutManager
    {
        return $this->layoutManager;
    }
}
