<?php declare(strict_types=1);

namespace Sharp\Runtime\Layout;

use Sharp\Exception\RenderException;

final class LayoutManager
{
    /**
     * Unique sentinel inserted by #parent when the parent section's content is not yet known.
     * Replaced with actual parent content when the parent layout stops its same-named section.
     */
    private const PARENT_PLACEHOLDER = "\x00SHARP_PARENT\x00";

    private ?string $parentLayout = null;

    /** @var array<string, string> sectionName → captured HTML */
    private array $sections = [];

    private SectionStack $stack;

    /** @var array<string, string[]> stackName → list of pushed/prepended items */
    private array $stacks = [];

    /** Output buffer for #push / #prepend blocks */
    private SectionStack $pushBuffer;

    /** @var bool[] tracks whether each active push level is prepend mode */
    private array $pushModeStack = [];

    public function __construct()
    {
        $this->stack      = new SectionStack();
        $this->pushBuffer = new SectionStack();
    }

    public function reset(): void
    {
        $this->parentLayout  = null;
        $this->sections      = [];
        $this->stacks        = [];
        $this->pushModeStack = [];
        $this->stack         = new SectionStack();
        $this->pushBuffer    = new SectionStack();
    }

    // ─── Called from compiled templates ─────────────────────────────────────

    public function extends(string $layout): void
    {
        $this->parentLayout = $layout;
    }

    public function startSection(string $name): void
    {
        $this->stack->push($name);
    }

    public function stopSection(): void
    {
        $name    = $this->stack->current();
        $content = $this->stack->pop();

        if ($name === null) {
            throw new RenderException('stopSection() called without a matching startSection()');
        }

        if (!isset($this->sections[$name])) {
            // First capture (usually from the child template): store as-is.
            $this->sections[$name] = $content;
        } elseif (str_contains($this->sections[$name], self::PARENT_PLACEHOLDER)) {
            // Child's section contained #parent — substitute the placeholder with
            // the parent layout's version of this section now that it is available.
            $this->sections[$name] = str_replace(
                self::PARENT_PLACEHOLDER,
                $content,
                $this->sections[$name],
            );
        }
        // Otherwise keep the child's version unchanged (parent tried to redefine without #parent).
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function yieldParentContent(): string
    {
        $current = $this->stack->current();
        if ($current === null) {
            throw new RenderException('#parent used outside a #section block');
        }
        // If the section already has parent content (multi-level inheritance), return it.
        // Otherwise insert a sentinel that will be replaced after the parent layout runs.
        return $this->sections[$current] ?? self::PARENT_PLACEHOLDER;
    }

    // ─── Stacks (#push / #prepend / #stack) ─────────────────────────────────

    public function startPush(string $name): void
    {
        $this->pushBuffer->push($name);
        $this->pushModeStack[] = false;
    }

    public function stopPush(): void
    {
        $name      = $this->pushBuffer->current();
        $content   = $this->pushBuffer->pop();
        $isPrepend = array_pop($this->pushModeStack);

        if ($name === null) {
            throw new RenderException('stopPush() called without a matching startPush()');
        }

        if ($isPrepend) {
            if (!isset($this->stacks[$name])) {
                $this->stacks[$name] = [];
            }
            array_unshift($this->stacks[$name], $content);
        } else {
            $this->stacks[$name][] = $content;
        }
    }

    public function startPrepend(string $name): void
    {
        $this->pushBuffer->push($name);
        $this->pushModeStack[] = true;
    }

    public function stopPrepend(): void
    {
        $this->stopPush();
    }

    public function yieldStack(string $name): string
    {
        return implode('', $this->stacks[$name] ?? []);
    }

    // ─── Called from Sharp::render() loop ───────────────────────────────────

    public function hasParent(): bool
    {
        return $this->parentLayout !== null;
    }

    public function consumeParent(): string
    {
        $layout             = $this->parentLayout;
        $this->parentLayout = null;
        return $layout;
    }

    /**
     * Flush all dangling ob_start() calls from unclosed #section blocks.
     * Should be called from exception handlers in Sharp::executeTemplate().
     */
    public function cleanupSections(): void
    {
        $this->stack->cleanup();
        $this->pushBuffer->cleanup();
    }
}
