<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Models\FileCategory;
use App\Models\File;
use App\Models\FileDownload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles the user aspect of files.
 *
 * @author Roelof Roos <github@roelof.io>
 * @license MPL-2.0
 */
class FileController extends Controller
{
    /**
     * Makes sure the user is allowed to handle files.
     *
     * @return void
     */
    public function __construct()
    {
        // Make sure only users that can view files use these routes.
        $this->authorize('view', File::class);
    }
    /**
     * Homepage
     *
     * @return Response
     */
    public function index()
    {
        $allCategories = FileCategory::has('files')->get();
        $defaultCategory = FileCategory::findDefault();

        $categoryList = collect();

        foreach ($allCategories as $category) {
            if ($defaultCategory && $defaultCategory->is($category)) {
                continue;
            }

            $categoryList->push($category);
        }

        $categoryList = $categoryList->sortBy('title');

        if ($defaultCategory) {
            $categoryList->push($defaultCategory);
        }

        // Get a base query
        $baseQuery = File::public()->available();
        $limit = 5;

        return view('main.files.index')->with([
            'categories' => $categoryList,
            'files' => [
                'newest' => $baseQuery->latest()->take($limit)->get(),
                'popular' => [],
                'random' => $baseQuery->inRandomOrder()->take($limit)->get(),
            ]
        ]);
    }

    /**
     * Shows all the files in a given category, ordered by newest
     *
     * @param FileCategory $category
     * @return Response
     */
    public function category(FileCategory $category)
    {
        // Get most recent files
        $files = $category->files()->latest()->paginate(20);

        // Render view
        return view('main.files.category')->with([
            'category' => $category,
            'files' => $files
        ]);
    }

    /**
     * Returns a single file's detail page
     *
     * @param Request $request
     * @param File $file
     * @return Response
     */
    public function show(Request $request, File $file)
    {
        return view('main.files.single')->with([
            'file' => $file,
            'user' => $request->user()
        ]);
    }

    /**
     * Provides a download, if the file is public, available on the storage and not broken.
     *
     * @param Request $request
     * @param File $file
     * @return Response
     */
    public function download(Request $request, File $file)
    {
        $filePath = $file->path;
        $fileName = $file->filename;

        // Report 404 if not public
        if ($file->public || $file->broken) {
            throw new NotFoundHttpException();
        }

        // Abort if file is missing
        if (!Storage::exists($filePath)) {
            throw new NotFoundHttpException();
        }

        // Log download
        FileDownload::create([
            'user_id' => $request->user()->id,
            'file_id' => $file->id,
            'ip' => $request->ip()
        ]);

        // Send file
        return Storage::download($filePath, $fileName);
    }
}
