<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function getBooks()
    {
        $books = Book::all();

        return response()->json($books->where('published_at', '!=', null)->values());
    }

    public function store(Request $request)
    {
        $book = Book::create($request->all());

        return response()->json($book, 201);
    }

    public function destroy($id)
    {
        Book::find($id)->delete();

        return response()->noContent();
    }
}
