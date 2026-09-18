<?php

use App\Models\Book;
use Illuminate\Support\Carbon;

test('a book can be created via its factory', function () {
    $book = Book::factory()->create();

    expect(Book::find($book->id))->not->toBeNull();
});

test('published_at is cast to a date', function () {
    $book = Book::factory()->create(['published_at' => '2024-01-15']);

    expect($book->published_at)->toBeInstanceOf(Carbon::class);
});
