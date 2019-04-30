<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\FileCategory;

/**
 * Deletes a category, assigning all child items to the lost+found directory.
 *
 * @author Roelof Roos <github@roelof.io>
 */
class FileCategoryDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * File category to be deleted
     *
     * @var FileCategory
     */
    protected $category;

    /**
     * Create a new job instance.
     *
     * @param FileCategory $category
     * @return void
     */
    public function __construct(FileCategory $category)
    {
        $this->categrory = $category;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Get category and lost-and-found category
        $category = $this->category;
        $lostAndFound = FileCategory::findLostAndFound();

        // Re-assign category to lost and found
        FileCategory::where(['parent' => $category->id])
            ->update(['parent' => $lostAndFound->id]);

        // Re-assign all nodes to lost and found
        File::where(['parent' => $category->id])
            ->update(['parent' => $lostAndFound->id]);

        // Delete node
        $category->delete();
    }
}