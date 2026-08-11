<?php

declare(strict_types=1);

namespace Inertify\Form;

use Attribute;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Http\Request;
use ReflectionNamedType;
use ReflectionParameter;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Validate implements ContextualAttribute
{
    public static function resolve(
        self $attribute,
        Container $container,
        ReflectionParameter $parameter,
    ): Form {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new BindingResolutionException('The #[Validate] attribute requires a Form-typed parameter.');
        }

        $class = $type->getName();

        if ($class !== Form::class && ! is_subclass_of($class, Form::class)) {
            throw new BindingResolutionException("The #[Validate] attribute cannot resolve [{$class}] because it is not a Form.");
        }

        /** @var Form $form */
        $form = $container->make($class);
        /** @var Request $request */
        $request = $container->make('request');

        $form->setRequest($request)->validate();

        return $form;
    }
}
