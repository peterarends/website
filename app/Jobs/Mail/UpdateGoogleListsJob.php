<?php

declare(strict_types=1);

namespace App\Jobs\Mail;

use App\Contracts\Mail\MailList;
use App\Contracts\Mail\MailListHandler;
use App\Helpers\Arr;
use App\Helpers\Str;
use App\Models\EmailList;
use App\Services\Mail\GoogleMailListService;
use App\Services\Mail\GooglePermissionFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateGoogleListsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const NO_CHANGE = [
        'aliquando',
        'm-power',
        'proximus',
    ];
    private const NO_ALIAS_CHANGE = [];
    private const NO_MEMBER_CHANGE = [
        'bestuur'
    ];

    protected string $email;
    protected string $name;
    protected ?array $aliases;
    protected array $members;

    public function __construct(string $email, string $name, ?array $aliases, array $members)
    {
        $this->email = $email;
        $this->name = $name;
        $this->aliases = $aliases;
        $this->members = $members;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle(MailListHandler $handler)
    {
        // Get list
        $list = $this->getEmailList($handler);

        // Get change flags
        $mailHandle = Str::beforeLast($list->getEmail(), '@');
        $updateAny = !\in_array($mailHandle, self::NO_CHANGE);
        $updateAliases = !\in_array($mailHandle, self::NO_ALIAS_CHANGE);
        $updateMembers = !\in_array($mailHandle, self::NO_MEMBER_CHANGE);

        // Update model
        $this->updateModel($list);

        // Update aliases
        if ($this->aliases !== null && $updateAny && $updateAliases) {
            $this->updateAliases($list);
        }

        // Update members
        if ($updateAny && $updateMembers) {
            $this->updateMembers($list);
        }

        $hasChanges = !(empty($list->getChangedAliases()) && empty($list->getChangedEmails()));

        // Commit changes
        if ($hasChanges) {
            $handler->save($list);
        }

        // Update permissions
        if ($handler instanceof GoogleMailListService) {
            // Build permissions
            $perms = GooglePermissionFactory::make()
                ->build();

            // Apply changes
            echo "Applying new security policy\n";
            // $handler->applyPermissions($list, $perms);
        }

        // Update model
        if ($hasChanges) {
            $this->updateModel($this->getEmailList($handler));
        }
    }

    /**
     * Finds or creates list
     * @param MailListHandler $handler
     * @return MailList
     */
    public function getEmailList(MailListHandler $handler)
    {
        // Get existing
        $list = $handler->getList($this->email);
        if ($list) {
            echo "Found existing {$this->email}\n";
            return $list;
        }

        // Make new
        echo "Creating new {$this->email}\n";
        return $handler->createList($this->email, $this->name);
    }

    /**
     * Applies updates to the model
     * @param MailList $list
     * @return void
     */
    public function updateModel(MailList $list): void
    {
        // Get model
        $model = EmailList::firstOrNew(['email' => $list->getEmail()]);

        // Build aliases
        $aliases = $list->listAliases();
        sort($aliases);

        // Build members
        $members = collect($list->listEmails())
            ->map(static fn ($val) => [
                'email' => $val[0],
                'role' => $val[1] === MailList::ROLE_ADMIN ? 'admin' : 'user'
            ])
            ->toArray();

        // Assign
        $model->service_id = $list->getServiceId();
        $model->name = $this->name;
        $model->aliases = $aliases;
        $model->members = $members;

        // Save
        $model->save();
    }

    /**
     * Updates the aliases on the list
     * @param MailList $list
     * @return void
     */
    private function updateAliases(MailList $list): void
    {
        // Speed up search
            $wantedAliases = \array_flip($this->aliases);
            $existingAliases = [];

            // Remove extra aliases
        foreach ($list->listAliases() as $alias) {
            // Skip if ok
            if (\array_key_exists($alias, $wantedAliases)) {
                $existingAliases[$alias] = true;
                continue;
            }

            // Remove if excessive
            echo "Would remove {$alias}\n";
            $list->deleteAlias($alias);
        }

            // Add missing aliases
        foreach ($this->aliases as $alias) {
            // Skip if exists
            if (\array_key_exists($alias, $existingAliases)) {
                echo "Found existing {$alias}\n";
                continue;
            }

            // Add missing
            echo "Would add {$alias}\n";
            $list->addAlias($alias);
        }
    }

    /**
     * Updates all users on this list, except those who appear to be forwarders
     * @param MailList $list
     * @return void
     * @throws BindingResolutionException
     */
    private function updateMembers(MailList $list): void
    {
        $wantedMembers = \array_combine(Arr::pluck($this->members, 0), $this->members);
        $existingMembers = [];

        // Remove extra aliases
        foreach ($list->listEmails() as [$email, $role]) {
            // Add if found
            if (\array_key_exists($email, $wantedMembers)) {
                $existingMembers[$email] = $role;
                continue;
            }

            // Don't remove internal members
            $domain = Str::afterLast($email, '@');
            if (\in_array($domain, \config('services.google.domains'))) {
                echo "Leaving {$email} as-is, seems internal\n";
                $existingMembers[$email] = $role;
                continue;
            }

            // Remove if excess
            echo "Would remove {$email}\n";
            $list->removeEmail($email);
            continue;
        }

        // Add missing and invalid
        foreach ($this->members as [$email, $role]) {
            // Add missing
            if (!\array_key_exists($email, $existingMembers)) {
                echo "Would add {$email}\n";
                $list->addEmail($email, $role);
                continue;
            }

            // Continue if up-to-date
            if ($existingMembers[$email] === $role) {
                continue;
            }

            // Update role
            echo "Would update {$email} from {$existingMembers[$email]} to {$role}\n";
            $list->updateEmail($email, $role);
        }
    }
}
