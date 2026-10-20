<?php

declare(strict_types=1);

namespace DeviceViewBundle\View;

/**
 * The names of the views (device_view.views.names): the tokens used in URLs, the remember
 * storage and headers, and the names the facade gives and takes.
 */
final readonly class ViewNames
{
    /** @var array<value-of<DeviceType>, string> canonical name => configured name */
    private array $names;

    /** @var array<array-key, DeviceType> configured name => view */
    private array $views;

    /**
     * @param array<string, string> $names configured name by canonical name; a view left out keeps its canonical name
     */
    public function __construct(array $names = [])
    {
        $all = [];
        $views = [];
        foreach (DeviceType::cases() as $view) {
            $name = $names[$view->value] ?? $view->value;
            $all[$view->value] = $name;
            $views[$name] = $view;
        }

        $this->names = $all;
        $this->views = $views;
    }

    /**
     * The configured name of the view.
     */
    public function nameOf(DeviceType $view): string
    {
        return $this->names[$view->value];
    }

    /**
     * The configured names, mobile, tablet then desktop.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values($this->names);
    }

    /**
     * Reads a view token from a request input (URL, remember storage, header).
     *
     * The token is accepted only when it is a string which, trimmed and lower-cased, is one of
     * the configured names. Anything else (an array, an unknown word, an empty value) gives null.
     */
    public function parse(mixed $token): ?DeviceType
    {
        if (!\is_string($token)) {
            return null;
        }

        return $this->views[strtolower(trim($token))] ?? null;
    }

    /**
     * The view of a name given by the application's code: exactly one of the configured names.
     *
     * @throws \InvalidArgumentException when the name is not a configured name
     */
    public function fromName(string $name): DeviceType
    {
        return $this->views[$name] ?? throw new \InvalidArgumentException(\sprintf('Unknown device view "%s". Expected one of: %s.', $name, implode(', ', $this->names)));
    }
}
