<?php

declare(strict_types=1);

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Media\Media;

test('central media visibility hydrates as an enum and serializes to its documented numeric code', function (int $code, MediaVisibilityEnum $visibility): void {
    $media = new Media;
    $media->setRawAttributes(['visibility' => $code]);

    expect($media->visibility)->toBe($visibility);
    expect($media->toArray()['visibility'])->toBe($code);
})->with([
    'public' => [1, MediaVisibilityEnum::PUBLIC],
    'private' => [2, MediaVisibilityEnum::PRIVATE],
]);

test('assigning a media visibility enum retains the numeric database value', function (MediaVisibilityEnum $visibility, int $code): void {
    $media = new Media;
    $media->visibility = $visibility;

    expect($media->getAttributes()['visibility'])->toBe($code);
    expect($media->visibility)->toBe($visibility);
})->with([
    'public' => [MediaVisibilityEnum::PUBLIC, 1],
    'private' => [MediaVisibilityEnum::PRIVATE, 2],
]);

test('central media rejects an undocumented visibility code', function (int $code): void {
    $media = new Media;

    expect(fn () => $media->visibility = $code)->toThrow(ValueError::class);
})->with([0, 3]);
