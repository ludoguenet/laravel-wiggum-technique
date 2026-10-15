<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DocumentSeeder extends Seeder
{
    /**
     * Seed the internal knowledge base from the Markdown files in database/seeders/documents.
     */
    public function run(): void
    {
        foreach (File::files(__DIR__.'/documents') as $file) {
            $body = $file->getContents();

            Document::updateOrCreate(['slug' => $file->getFilenameWithoutExtension()], [
                'title' => str($body)->before("\n")->after('# ')->trim()->toString(),
                'body' => $body,
                'is_confidential' => $file->getFilenameWithoutExtension() === 'secrets-rotation',
            ]);
        }
    }
}
