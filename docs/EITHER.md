# `Either`

This structure represent an alternative between 2 possibles types `L` and `R` (left and right), with a bias toward the right value. Usually this is used to represent `Either<SomeError, ValidValue>`, the valid value being on the right side benefit the right bias as `map`, `flatMap` and `filter` are done on the right value.

This technique is borrowed from the functional programming world.

For the examples below we will use the given imaginary functions:

```php
use Innmind\Immutable\Either;

/**
 * @return Either<Error, User>
 */
function identify(ServerRequest $request): Either {
    if (/* some condition */) {
        return Either::right($theUser);
    }

    Either::left(new Error('User not found'));
}

/**
 * @return Either<Error, Resource>
 */
function accessResource(User $user): Either {
    if (/* check if user is allowed */) {
        return Either::right($resource);
    }

    return Either::left(new Error('User is not allowed'));
}
```

**Note**: `ServerRequest`, `User`, `Resource` and `Error` are imaginary classes.

**Important**: this structure is immutable and is only typed with [Psalm](http://psalm.dev) to keep the interface simple and jump between types more easily. So make sure to use Psalm in your code so you know if you use the structure correctly.

## `::left()`

This builds an `Either` instance with the given value in the left hand side.

```php
$either = Either::left($anyValue);
```

**Note**: usually this side is used for errors.

## `::right()`

This builds an `Either` instance with the given value in the right hand side.

```php
$either = Either::right($anyValue);
```

**Note**: usually this side is used for valid values.

## `->map()`

This will apply the map transformation on the right value if there is one, otherwise it's only a type change.

```php
/** @var Either<Error, User> */
$either = identify($serverRequest)
/** @var Either<Error, Impersonated> */
$impersonated = $either->map(fn(User $user): Impersonated => $user->impersonateAdmin());
```

## `->flatMap()`

This is similar to `->map()` but instead of returning the new right value you return a new `Either` object.

```php
/** @var Either<Error, User> */
$either = identify($serverRequest);
/** @var Either<Error, Resource> */
$resource = $either->flatMap(fn(User $user): Either => accessResource($user));
```

**Important**: the left type of an `Either` returned by the `->flatMap` cannot change as the next call on `$resource` you wouldn't know if it's the old left type or the new one.

## `->match()`

This is the only way to extract the wrapped value.

```php
/** @var Response */
$response = identify($serverRequest)
    ->flatMap(fn(User $user): Either => accessResource($user))
    ->match(
        fn(Error $error) => new Response(400, $error->message()), // here the error can be from identify or from accessResource
        fn(Resource $resource) => new Response(200, $resource->toString()),
    );
```

**Note**: `Response` is an imaginary class.

Both functions used with `->match()` **must** have the same return type. Otherwise you would have to check the type of the returned value to know what to do next, this would break the logic of this approach that frees you from writing any `if` statement.

## `->otherwise()`

This is like `->flatMap()` but is called when the instance contains a left value. The callable must return an `Either` with the same types as the previous one.

```php
/**
 * @return Either<Error, User>
 */
function identifyViaJsonPayload(ServerRequest $request): Either {
    if (/* find user from json payload */) {
        return Either::right($theUser);
    }

    return Either::left(new Error('User not found'));
}

/** @var Either<Error, User> */
$either = identify($request)
    ->otherwise(fn() => identifyViaJsonPayload($request));
```

## `->filter()`

Use this method to validate the right value when there is one. If the predicate doesn't accept the right value then it will return the value from the second callable as a left value.

```php
identify($request)
    ->filter(
        fn(User $user): bool => $user->isAllowed(),
        fn() => new Error('User is not allowed'),
    )
    ->match(
        fn(Error $error) => print($error->message()), // can be "User not found" or "User is not allowed"
        fn(User $user) => doSomething($user), // here we know the user is allowed
    );
```
