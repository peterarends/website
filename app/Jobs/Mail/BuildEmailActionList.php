<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Contracts\ConscriboServiceContract;
use App\Contracts\Mail\MailList;
use App\Helpers\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildEmailActionList implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const EMAIL_REMAP = [
        'lhw@gumbo-millennium.nl' => 'lhc@gumbo-millennium.nl',
    ];

    public static function test()
    {
        $inst = new self();
        \app()->call([$inst, 'handle']);
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(ConscriboServiceContract $conscribo)
    {
        // Get all roles
        $roles = $conscribo->getResource('role', [], ['code', 'naam', 'leden', 'e_mailadres']);

        // Map "leden" to an array
        // 1) Get the 'leden' properties and split it on comma's followed by a digit (values are "1: user, 2: user")
        // 3) Sort by member ID by casting to a number
        $roles = $roles->map(static function (&$role) {
            $memberList = preg_split('/\,\s*(?=\d+\:)/', $role['leden'] ?? '');
            $role['leden'] = collect($memberList)
                ->each('trim')
                ->filter()
                ->sort(static fn ($a, $b) => intval($a) <=> intval($b))
                ->toArray();
            return $role;
        });

        // Get all unique users on all roles
        // 1) Get the 'leden' array and collapse the nested array
        // 2) Remove the duplicates
        // 3) Cast to array
        $userIds = $roles
            ->pluck('leden')
            ->collapse()
            ->flip()
            ->keys()
            ->toArray();

        // Get users from Conscribo
        $userResource = $conscribo->getResource(
            'user',
            [['selector', '~', $userIds]],
            ['selector', 'email']
        );

        // Map emails by selector, remove empties and lowercase email
        $emails = $userResource
            ->pluck('email', 'selector')
            ->filter()
            ->map(static fn ($val) => Str::lower(trim($val)));

        // Map new models
        $jobList = collect();
        foreach ($roles as $role) {
            // Build member list
            $jobMembers = $emails
                ->only($role['leden'])
                ->values()
                ->map(static fn ($val) => [$val, MailList::ROLE_NORMAL])
                ->toArray();

            // Build job
            $job = [
                'email' => Str::lower($role['e_mailadres']),
                'name' => $role['naam'],
                'aliases' => null,
                'members' => $jobMembers
            ];

            if (!empty(self::EMAIL_REMAP[$job['email']])) {
                $job['email'] = self::EMAIL_REMAP[$job['email']];
            }

            // Add job
            $jobList->push($job);
        }

        // Get safe domains
        $validDomains = \config('services.google.domains', []);

        // Start a job for each email
        foreach ($jobList as $job) {
            $domain = Str::afterLast($job['email'], '@');
            if (!\in_array($domain, $validDomains)) {
                echo "Skipping not-whitelisted {$domain} for {$job['email']}\n";
                continue;
            }

            UpdateGoogleListsJob::dispatchNow(
                $job['email'],
                $job['name'],
                $job['aliases'],
                $job['members']
            );
        }
    }
}
