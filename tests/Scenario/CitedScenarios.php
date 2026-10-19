<?php

declare(strict_types=1);

namespace DeviceViewBundle\Tests\Scenario;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Finds the scenarios that tests cite.
 *
 * A test cites a scenario with a PHPUnit group named after the scenario ID, on the test
 * method or on the test class: #[Group('SW-03')]. Groups that are not shaped like an ID are
 * ignored.
 */
final class CitedScenarios
{
    /**
     * Reads the citations of every test class (a file whose name ends with Test.php) under
     * the directory.
     *
     * @param string $directory the root of the PSR-4 namespace
     * @param string $namespace the namespace of that root
     *
     * @return array<string, list<string>> scenario ID => citing classes or Class::method
     */
    public static function inDirectory(string $directory, string $namespace): array
    {
        $classes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($directory) + 1, -\strlen('.php'));
            $class = $namespace.'\\'.str_replace(\DIRECTORY_SEPARATOR, '\\', $relativePath);
            if (!class_exists($class)) {
                throw new \UnexpectedValueException(\sprintf('The test file %s does not declare the class %s.', $file->getPathname(), $class));
            }

            $classes[] = $class;
        }
        sort($classes);

        return self::inClasses($classes);
    }

    /**
     * @param list<class-string> $classes
     *
     * @return array<string, list<string>> scenario ID => citing classes or Class::method
     */
    public static function inClasses(array $classes): array
    {
        $cited = [];
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            foreach (self::ids($reflection) as $id) {
                $cited[$id][] = $class;
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_starts_with($method->getName(), 'test') && [] === $method->getAttributes(Test::class)) {
                    continue;
                }

                foreach (self::ids($method) as $id) {
                    $cited[$id][] = $class.'::'.$method->getName();
                }
            }
        }

        return $cited;
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $element
     *
     * @return list<string>
     */
    private static function ids(\ReflectionClass|\ReflectionMethod $element): array
    {
        $ids = [];
        foreach ($element->getAttributes(Group::class) as $attribute) {
            $group = $attribute->newInstance()->name();
            if (1 === preg_match('/^'.ScenarioCatalogue::ID_PATTERN.'$/', $group)) {
                $ids[] = $group;
            }
        }

        return $ids;
    }
}
