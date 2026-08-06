<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Policy Scope Delegation
|--------------------------------------------------------------------------
|
| A Policy's decision must derive entirely from the acting user, the
| target, and the stored grant — never from a boolean the caller supplies,
| which would let a call site silently opt itself out of the scope check
| ScopedPolicy exists to make uniform. Reflection over every class in
| App\Policies, not the arch() DSL, since this is a parameter-signature
| question the DSL has no primitive for.
|
*/

test('no policy method accepts a caller-supplied boolean parameter', function (): void {
    $root = dirname(__DIR__, 2);

    $files = (new Finder)->files()->in($root.'/app/Policies')->name('*.php');

    $offenders = [];

    foreach ($files as $file) {
        $class = 'App\\Policies\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            $file->getRelativePathname()
        );

        if (! class_exists($class) && ! interface_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && $type->getName() === 'bool') {
                    $offenders[] = "{$class}::{$method->getName()}(\${$parameter->getName()})";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
