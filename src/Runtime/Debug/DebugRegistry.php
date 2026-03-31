<?php declare(strict_types=1);

namespace Sharp\Runtime\Debug;

/**
 * Collects component render data during a single request in dev mode.
 * Singleton — call reset() at the start of each Sharp::render() call.
 *
 * Only active when devMode = true.
 * In production (setProduction(true) or config devMode: false), this class is never used.
 */
final class DebugRegistry
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> Keyed by component ID */
    private array $components = [];

    /** Stack of component IDs currently being rendered (for parent-child tracking) */
    private array $renderStack = [];

    /**
     * Parallel stack to renderStack: each entry is whether the component file was a cache hit.
     * Pushed by Compiler::compileFilePath() (file-backed components).
     * Popped by the generated code after renderComponent() returns.
     */
    private array $cacheHitStack = [];

    private int $idCounter = 0;

    // ─── Singleton ───────────────────────────────────────────────────────────

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    // ─── Component lifecycle ─────────────────────────────────────────────────

    /**
     * Called from generated PHP code BEFORE renderComponent().
     * Pushes a new component onto the render stack.
     *
     * @param array<string, mixed> $props     Runtime props passed to the component.
     * @param string[]             $slotNames Keys of the slots array.
     * @return string The new component instance ID (e.g. "uc_1").
     */
    public function pushComponent(
        string $name,
        string $spPath,
        int $line,
        array $props,
        array $slotNames,
    ): string {
        $parentId = !empty($this->renderStack) ? end($this->renderStack) : null;
        $id       = $this->generateId($name);

        $this->components[$id] = [
            'id'        => $id,
            'name'      => $name,
            'file'      => $spPath,
            'line'      => $line,
            'props'     => $this->serializeProps($props),
            'parentId'  => $parentId,
            'renderMs'  => 0.0,
            'cacheHit'  => false,
            'slotNames' => $slotNames,
        ];

        $this->renderStack[] = $id;

        return $id;
    }

    /**
     * Called from generated PHP code AFTER renderComponent() returns.
     * Updates the component record with render time and cache hit status.
     */
    public function popComponent(string $id, float $renderMs, bool $cacheHit): void
    {
        if (isset($this->components[$id])) {
            $this->components[$id]['renderMs'] = round($renderMs, 3);
            $this->components[$id]['cacheHit'] = $cacheHit;
        }

        // Pop off render stack
        $pos = array_search($id, $this->renderStack, true);
        if ($pos !== false) {
            array_splice($this->renderStack, (int) $pos, 1);
        }
    }

    // ─── Cache hit tracking ──────────────────────────────────────────────────

    /**
     * Called by Compiler::compileFilePath() to report whether the compiled PHP
     * file was already cached (true) or freshly compiled (false).
     * Must be called once per file-backed component render, before popCacheHit().
     */
    public function pushCacheHit(bool $hit): void
    {
        $this->cacheHitStack[] = $hit;
    }

    /**
     * Called from generated PHP code after renderComponent() to read the cache hit flag.
     * Returns false if the stack is empty (e.g. class-backed components).
     */
    public function popCacheHit(): bool
    {
        return (bool) array_pop($this->cacheHitStack);
    }

    // ─── Output ──────────────────────────────────────────────────────────────

    /**
     * Returns an inline <script> tag that sets window.__sharpDebugData.
     * Inject this before </body> in the rendered HTML.
     */
    public function getScriptTag(): string
    {
        $components = array_values($this->components);
        $cacheHits  = count(array_filter($components, fn($c) => $c['cacheHit']));

        $data = [
            'version'    => 2,
            'detectedAt' => (int) (microtime(true) * 1000),
            'components' => $components,
            'stats'      => [
                'totalComponents' => count($components),
                'cacheHits'       => $cacheHits,
                'cacheMisses'     => count($components) - $cacheHits,
                'totalRenderMs'   => round(
                    array_sum(array_column($components, 'renderMs')),
                    3,
                ),
            ],
        ];

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return "\n<script id=\"__SHARP_DEBUG__\">\nwindow.__sharpDebugData = {$json};\n</script>\n";
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Generate a short ID like "uc_1" from "user-card".
     * Takes the first character of each kebab-case segment.
     */
    private function generateId(string $componentName): string
    {
        $prefix = implode('', array_map(
            fn(string $part) => $part[0] ?? 'x',
            explode('-', $componentName),
        ));

        $this->idCounter++;

        return strtolower($prefix) . '_' . $this->idCounter;
    }

    /**
     * Serialize props for JSON output.
     * Scalars are kept as-is; arrays and objects are replaced with descriptors.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private function serializeProps(array $props): array
    {
        $out = [];
        foreach ($props as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            } elseif (is_array($value)) {
                $out[$key] = '[array(' . count($value) . ')]';
            } else {
                $out[$key] = '[' . get_debug_type($value) . ']';
            }
        }
        return $out;
    }
}
