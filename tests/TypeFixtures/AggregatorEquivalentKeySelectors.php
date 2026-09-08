<?php

declare(strict_types=1);

namespace Itera\Tests\TypeFixtures;

use Itera\Map;
use Itera\Sequence;

use function Itera\Aggregator\associate;

final class AggregatorEquivalentKeySelectors
{
    /** @return Map<int, AssociatedMethodUser> */
    public function inferArrowMethodReturn(): Map
    {
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(static fn($user) => $user->key()));
    }

    /** @return Map<int, AssociatedMethodUser> */
    public function preserveArrowReturnDeclaration(): Map
    {
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(
            static fn($user): int => $user->key(),
        ));
    }

    /**
     * @return Map<int, AssociatedMethodUser>
     * @mago-expect lint:prefer-arrow-function
     */
    public function preserveClosureReturnDeclaration(): Map
    {
        // A traditional closure keeps this fixture distinct from the arrow-function cases.
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(static function ($user): int {
            return $user->key();
        }));
    }

    /**
     * @return Map<int, AssociatedMethodUser>
     * @mago-expect lint:prefer-first-class-callable
     */
    public function preserveOuterParameterCapture(): Map
    {
        $user = new AssociatedStringMethodUser('caller');

        // A nested arrow is required to exercise the callback parameter's lexical capture.
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(
            static fn($user) => (static fn() => $user->key())(),
        ));
    }

    /** @return Map<string, AssociatedMethodUser> */
    public function preserveNestedParameterShadowing(): Map
    {
        return Sequence::from([new AssociatedMethodUser(
            1,
        )])->aggregate(associate(static fn($user) => (static fn($user) => $user->key())(
            new AssociatedStringMethodUser('nested'),
        )));
    }

    /** @return Map<string, AssociatedMethodUser> */
    public function preserveCapturedParameterReassignment(): Map
    {
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(
            static fn($user) => (static function () use ($user) {
                $user = new AssociatedStringMethodUser('assigned');

                return $user->key();
            })(),
        ));
    }

    /** @return Map<string, AssociatedMethodUser> */
    public function preserveByReferenceParameterReassignment(): Map
    {
        return Sequence::from([new AssociatedMethodUser(1)])->aggregate(associate(
            static fn($user) => (static function () use (&$user) {
                $user = new AssociatedStringMethodUser('assigned by reference');

                return $user->key();
            })(),
        ));
    }
}
